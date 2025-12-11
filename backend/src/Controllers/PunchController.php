<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use App\Core\Constants\PunchType;
use App\Core\DepartmentHierarchy;
use App\Middleware\VirtualTimeMiddleware;
use PDO;

/**
 * 打刻管理コントローラー
 */
class PunchController
{
    /**
     * 打刻記録作成
     * POST /punches
     */
    public function create(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $type = $request->getBody('type');
        $occurredAt = $request->getBody('occurred_at');
        $note = $request->getBody('note');
        $device = $request->getBody('device');

        // バリデーション
        if (empty($type) || empty($occurredAt)) {
            $response->error('VALIDATION_ERROR', '打刻種類と打刻日時は必須です', [], 400);
            return;
        }

        // 打刻種類の検証
        $validTypes = ['in', 'out', 'break_in', 'break_out'];
        if (!in_array($type, $validTypes)) {
            $response->error('VALIDATION_ERROR', '無効な打刻種類です。in, out, break_in, break_out のいずれかを指定してください', [], 400);
            return;
        }

        // 日時の検証
        $occurredAtTimestamp = strtotime($occurredAt);
        if ($occurredAtTimestamp === false) {
            $response->error('VALIDATION_ERROR', '無効な日時形式です', [], 400);
            return;
        }

        // 未来日時の打刻は不可（仮想時間モード対応）
        $currentTime = VirtualTimeMiddleware::getCurrentTime($request);
        $currentTimestamp = $currentTime->getTimestamp();
        if ($occurredAtTimestamp > $currentTimestamp) {
            $response->error('VALIDATION_ERROR', '未来日時の打刻はできません', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // 従業員プロファイルを取得（user_idから）
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.employee_code, ep.name, ep.status
            FROM employee_profiles ep
            WHERE ep.user_id = :user_id 
            AND ep.tenant_id = :tenant_id 
            AND ep.deleted_at IS NULL
        ");
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            $response->error('NOT_FOUND', '従業員プロファイルが見つかりません', [], 404);
            return;
        }

        if ($employee['status'] !== 'active') {
            $response->error('VALIDATION_ERROR', '在籍中の従業員のみ打刻できます', [], 400);
            return;
        }

        $employeeId = $employee['id'];

        // 打刻検証
        $validationError = $this->validatePunch($pdo, $tenantId, $employeeId, $type, $occurredAt);
        if ($validationError !== null) {
            $response->error('VALIDATION_ERROR', $validationError, [], 400);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 打刻記録を作成
            $stmt = $pdo->prepare("
                INSERT INTO punch_records (
                    tenant_id, employee_id, type, occurred_at, 
                    source, device, note
                )
                VALUES (
                    :tenant_id, :employee_id, :type, :occurred_at,
                    'web', :device, :note
                )
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'type' => $type,
                'occurred_at' => $occurredAt,
                'device' => $device,
                'note' => $note,
            ]);
            $punchRecord = $stmt->fetch();
            $punchId = $punchRecord['id'];

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'punch.create', 'punch', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $punchId,
                'after' => json_encode([
                    'employee_id' => $employeeId,
                    'type' => $type,
                    'occurred_at' => $occurredAt,
                ]),
            ]);

            $pdo->commit();

            // 作成した打刻記録を取得
            $stmt = $pdo->prepare("
                SELECT 
                    pr.id,
                    pr.tenant_id,
                    pr.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    pr.type,
                    pr.occurred_at,
                    pr.source,
                    pr.device,
                    pr.note,
                    pr.proxy_user_id,
                    pr.proxy_reason,
                    pr.created_at,
                    pr.updated_at
                FROM punch_records pr
                JOIN employee_profiles ep ON pr.employee_id = ep.id
                WHERE pr.id = :id
            ");
            $stmt->execute(['id' => $punchId]);
            $createdPunch = $stmt->fetch();

            $response->success($createdPunch, '打刻を記録しました', 201);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            
            // 重複エラーの場合（ユニーク制約違反）
            if (strpos($e->getMessage(), 'punch_records_tenant_employee_type_occurred_unique') !== false) {
                $response->error('CONFLICT', '同じ打刻が既に記録されています', [], 409);
                return;
            }
            
            $response->errorWithException('INTERNAL_ERROR', '打刻の記録に失敗しました', $e, [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '打刻の記録に失敗しました', $e, [], 500);
        }
    }

    /**
     * 代理打刻
     * POST /punches/proxy
     */
    public function proxy(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::MANAGER, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '代理打刻の権限がありません', [], 403);
            return;
        }

        $employeeId = $request->getBody('employee_id');
        $type = $request->getBody('type');
        $occurredAt = $request->getBody('occurred_at');
        $proxyReason = $request->getBody('proxy_reason');
        $note = $request->getBody('note');
        $device = $request->getBody('device');

        // バリデーション
        if (empty($employeeId) || empty($type) || empty($occurredAt) || empty($proxyReason)) {
            $response->error('VALIDATION_ERROR', '従業員ID、打刻種類、打刻日時、代理打刻理由は必須です', [], 400);
            return;
        }

        // 打刻種類の検証
        $validTypes = ['in', 'out', 'break_in', 'break_out'];
        if (!in_array($type, $validTypes)) {
            $response->error('VALIDATION_ERROR', '無効な打刻種類です。in, out, break_in, break_out のいずれかを指定してください', [], 400);
            return;
        }

        // 日時の検証
        $occurredAtTimestamp = strtotime($occurredAt);
        if ($occurredAtTimestamp === false) {
            $response->error('VALIDATION_ERROR', '無効な日時形式です', [], 400);
            return;
        }

        // 未来日時の打刻は不可（仮想時間モード対応）
        $currentTime = VirtualTimeMiddleware::getCurrentTime($request);
        $currentTimestamp = $currentTime->getTimestamp();
        if ($occurredAtTimestamp > $currentTimestamp) {
            $response->error('VALIDATION_ERROR', '未来日時の打刻はできません', [], 400);
            return;
        }

        // 過去30日以内のみ（仮想時間モード対応）
        $thirtyDaysAgo = clone $currentTime;
        $thirtyDaysAgo->modify('-30 days');
        $thirtyDaysAgoTimestamp = $thirtyDaysAgo->getTimestamp();
        if ($occurredAtTimestamp < $thirtyDaysAgoTimestamp) {
            $response->error('VALIDATION_ERROR', '過去30日以内の打刻のみ代理打刻できます', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // 従業員プロファイルを取得
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.employee_code, ep.name, ep.status
            FROM employee_profiles ep
            WHERE ep.id = :employee_id 
            AND ep.tenant_id = :tenant_id 
            AND ep.deleted_at IS NULL
        ");
        $stmt->execute(['employee_id' => $employeeId, 'tenant_id' => $tenantId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            $response->error('NOT_FOUND', '従業員が見つかりません', [], 404);
            return;
        }

        if ($employee['status'] !== 'active') {
            $response->error('VALIDATION_ERROR', '在籍中の従業員のみ打刻できます', [], 400);
            return;
        }

        // Managerの場合は配下の従業員のみ
        if ($role === Role::MANAGER) {
            // 配下従業員の代理打刻のみ可能
            if (!DepartmentHierarchy::canManageEmployee($pdo, $tenantId, $userId, $employeeId)) {
                $response->error('FORBIDDEN', 'この従業員の代理打刻を行う権限がありません', [], 403);
                return;
            }
        }

        // 打刻検証
        $validationError = $this->validatePunch($pdo, $tenantId, $employeeId, $type, $occurredAt);
        if ($validationError !== null) {
            $response->error('VALIDATION_ERROR', $validationError, [], 400);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 代理打刻記録を作成
            $stmt = $pdo->prepare("
                INSERT INTO punch_records (
                    tenant_id, employee_id, type, occurred_at, 
                    source, device, note, proxy_user_id, proxy_reason
                )
                VALUES (
                    :tenant_id, :employee_id, :type, :occurred_at,
                    'web', :device, :note, :proxy_user_id, :proxy_reason
                )
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'type' => $type,
                'occurred_at' => $occurredAt,
                'device' => $device,
                'note' => $note,
                'proxy_user_id' => $userId,
                'proxy_reason' => $proxyReason,
            ]);
            $punchRecord = $stmt->fetch();
            $punchId = $punchRecord['id'];

            // 監査ログ（代理打刻は必ず記録）
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'punch.proxy', 'punch', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $punchId,
                'after' => json_encode([
                    'employee_id' => $employeeId,
                    'type' => $type,
                    'occurred_at' => $occurredAt,
                    'proxy_reason' => $proxyReason,
                ]),
            ]);

            $pdo->commit();

            // 作成した打刻記録を取得
            $stmt = $pdo->prepare("
                SELECT 
                    pr.id,
                    pr.tenant_id,
                    pr.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    pr.type,
                    pr.occurred_at,
                    pr.source,
                    pr.device,
                    pr.note,
                    pr.proxy_user_id,
                    u.name as proxy_user_name,
                    pr.proxy_reason,
                    pr.created_at,
                    pr.updated_at
                FROM punch_records pr
                JOIN employee_profiles ep ON pr.employee_id = ep.id
                LEFT JOIN users u ON pr.proxy_user_id = u.id
                WHERE pr.id = :id
            ");
            $stmt->execute(['id' => $punchId]);
            $createdPunch = $stmt->fetch();

            $response->success($createdPunch, '代理打刻を記録しました', 201);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            
            // 重複エラーの場合（ユニーク制約違反）
            if (strpos($e->getMessage(), 'punch_records_tenant_employee_type_occurred_unique') !== false) {
                $response->error('CONFLICT', '同じ打刻が既に記録されています', [], 409);
                return;
            }
            
            $response->errorWithException('INTERNAL_ERROR', '代理打刻の記録に失敗しました', $e, [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '代理打刻の記録に失敗しました', $e, [], 500);
        }
    }

    /**
     * 打刻一覧取得
     * GET /punches
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 20));
        $offset = ($page - 1) * $limit;

        $pdo = Database::getInstance();

        // フィルタ条件
        $where = ["pr.tenant_id = :tenant_id"];
        $params = ['tenant_id' => $tenantId];

        // 従業員IDフィルタ
        $employeeId = $request->getQuery('employee_id');
        if ($employeeId) {
            $where[] = "pr.employee_id = :employee_id";
            $params['employee_id'] = $employeeId;
        } else {
            // Employeeの場合は自分の打刻のみ
            if ($role === Role::EMPLOYEE) {
                $stmt = $pdo->prepare("
                    SELECT id FROM employee_profiles 
                    WHERE user_id = :user_id AND tenant_id = :tenant_id AND deleted_at IS NULL
                ");
                $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
                $employee = $stmt->fetch();
                if ($employee) {
                    $where[] = "pr.employee_id = :employee_id";
                    $params['employee_id'] = $employee['id'];
                } else {
                    // 従業員プロファイルが存在しない場合は空の結果を返す
                    $response->paginated([], 0, $page, $limit);
                    return;
                }
            }
        }

        // 打刻種類フィルタ
        $type = $request->getQuery('type');
        if ($type) {
            $validTypes = ['in', 'out', 'break_in', 'break_out'];
            if (in_array($type, $validTypes)) {
                $where[] = "pr.type = :type";
                $params['type'] = $type;
            }
        }

        // 日付範囲フィルタ
        $from = $request->getQuery('from');
        $to = $request->getQuery('to');
        if ($from) {
            $where[] = "pr.occurred_at >= :from";
            $params['from'] = $from;
        }
        if ($to) {
            $where[] = "pr.occurred_at <= :to";
            $params['to'] = $to;
        }

        $whereClause = implode(' AND ', $where);

        // 総件数取得
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM punch_records pr
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // データ取得
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                pr.tenant_id,
                pr.employee_id,
                ep.employee_code,
                ep.name as employee_name,
                pr.type,
                pr.occurred_at,
                pr.source,
                pr.device,
                pr.note,
                pr.proxy_user_id,
                u.name as proxy_user_name,
                pr.proxy_reason,
                pr.created_at,
                pr.updated_at
            FROM punch_records pr
            JOIN employee_profiles ep ON pr.employee_id = ep.id
            LEFT JOIN users u ON pr.proxy_user_id = u.id
            WHERE $whereClause
            ORDER BY pr.occurred_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        $stmt->execute($params);
        $punches = $stmt->fetchAll();

        $response->paginated($punches, $total, $page, $limit);
    }

    /**
     * 打刻検証
     * 
     * @param PDO $pdo データベース接続
     * @param string $tenantId テナントID
     * @param string $employeeId 従業員ID
     * @param string $type 打刻種類
     * @param string $occurredAt 打刻日時
     * @return string|null エラーメッセージ（検証OKの場合はnull）
     */
    private function validatePunch(PDO $pdo, string $tenantId, string $employeeId, string $type, string $occurredAt): ?string
    {
        // 重複チェック（冪等性）
        $stmt = $pdo->prepare("
            SELECT id FROM punch_records 
            WHERE tenant_id = :tenant_id 
            AND employee_id = :employee_id 
            AND type = :type 
            AND occurred_at = :occurred_at
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'type' => $type,
            'occurred_at' => $occurredAt,
        ]);
        if ($stmt->fetch()) {
            return '同じ打刻が既に記録されています';
        }

        // 打刻順序の検証
        $occurredAtTimestamp = strtotime($occurredAt);
        $date = date('Y-m-d', $occurredAtTimestamp);
        $nextDay = date('Y-m-d', strtotime('+1 day', $occurredAtTimestamp));

        // その日の打刻記録を取得（時系列順）
        $stmt = $pdo->prepare("
            SELECT type, occurred_at 
            FROM punch_records 
            WHERE tenant_id = :tenant_id 
            AND employee_id = :employee_id 
            AND occurred_at >= :date_start 
            AND occurred_at < :date_end
            ORDER BY occurred_at ASC
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'date_start' => $date . ' 00:00:00',
            'date_end' => $nextDay . ' 00:00:00',
        ]);
        $existingPunches = $stmt->fetchAll();

        // 打刻順序の検証
        if ($type === 'in') {
            // 出勤: その日に既に出勤記録がある場合はエラー
            foreach ($existingPunches as $punch) {
                if ($punch['type'] === 'in') {
                    return 'その日は既に出勤記録があります';
                }
            }
        } elseif ($type === 'out') {
            // 退勤: 出勤記録が存在し、かつ退勤記録がない場合のみ許可
            $hasIn = false;
            $hasOut = false;
            foreach ($existingPunches as $punch) {
                if ($punch['type'] === 'in') {
                    $hasIn = true;
                }
                if ($punch['type'] === 'out') {
                    $hasOut = true;
                }
            }
            if (!$hasIn) {
                return '出勤記録がないため退勤できません';
            }
            if ($hasOut) {
                return 'その日は既に退勤記録があります';
            }
        } elseif ($type === 'break_in') {
            // 休憩開始: 出勤記録があり、退勤記録がない場合のみ許可
            $hasIn = false;
            $hasOut = false;
            $hasBreakIn = false;
            $hasBreakOut = false;
            foreach ($existingPunches as $punch) {
                if ($punch['type'] === 'in') {
                    $hasIn = true;
                }
                if ($punch['type'] === 'out') {
                    $hasOut = true;
                }
                if ($punch['type'] === 'break_in') {
                    $hasBreakIn = true;
                }
                if ($punch['type'] === 'break_out') {
                    $hasBreakOut = true;
                }
            }
            if (!$hasIn) {
                return '出勤記録がないため休憩開始できません';
            }
            if ($hasOut) {
                return '退勤済みのため休憩開始できません';
            }
            // 最後の休憩終了より後に休憩開始がある場合のみ許可
            if ($hasBreakIn && $hasBreakOut) {
                // 最後の休憩終了時刻を確認
                $lastBreakOut = null;
                foreach ($existingPunches as $punch) {
                    if ($punch['type'] === 'break_out') {
                        $lastBreakOut = $punch['occurred_at'];
                    }
                }
                if ($lastBreakOut && strtotime($occurredAt) <= strtotime($lastBreakOut)) {
                    return '休憩終了より前に休憩開始できません';
                }
            } elseif ($hasBreakIn && !$hasBreakOut) {
                return '休憩終了記録がないため、新しい休憩開始できません';
            }
        } elseif ($type === 'break_out') {
            // 休憩終了: 休憩開始記録があり、かつ休憩終了記録がない場合のみ許可
            $hasIn = false;
            $hasOut = false;
            $hasBreakIn = false;
            $hasBreakOut = false;
            $lastBreakIn = null;
            foreach ($existingPunches as $punch) {
                if ($punch['type'] === 'in') {
                    $hasIn = true;
                }
                if ($punch['type'] === 'out') {
                    $hasOut = true;
                }
                if ($punch['type'] === 'break_in') {
                    $hasBreakIn = true;
                    $lastBreakIn = $punch['occurred_at'];
                }
                if ($punch['type'] === 'break_out') {
                    $hasBreakOut = true;
                }
            }
            if (!$hasIn) {
                return '出勤記録がないため休憩終了できません';
            }
            if ($hasOut) {
                return '退勤済みのため休憩終了できません';
            }
            if (!$hasBreakIn) {
                return '休憩開始記録がないため休憩終了できません';
            }
            // 最後の休憩開始より後に休憩終了がある場合のみ許可
            if ($lastBreakIn && strtotime($occurredAt) <= strtotime($lastBreakIn)) {
                return '休憩開始より前に休憩終了できません';
            }
            // 既に休憩終了がある場合は、最後の休憩終了より後に新しい休憩終了がある場合のみ許可
            if ($hasBreakOut) {
                $lastBreakOut = null;
                foreach ($existingPunches as $punch) {
                    if ($punch['type'] === 'break_out') {
                        $lastBreakOut = $punch['occurred_at'];
                    }
                }
                if ($lastBreakOut && strtotime($occurredAt) <= strtotime($lastBreakOut)) {
                    return '既存の休憩終了より前に新しい休憩終了できません';
                }
            }
        }

        return null;
    }
}

