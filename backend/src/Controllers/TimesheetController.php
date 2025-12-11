<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use App\Core\Constants\Status;
use PDO;

/**
 * タイムシート管理コントローラー
 */
class TimesheetController
{
    /**
     * タイムシート一覧取得
     * GET /timesheets
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 20));
        $offset = ($page - 1) * $limit;

        $employeeId = $request->getQuery('employee_id');
        $status = $request->getQuery('status');
        $periodFrom = $request->getQuery('period_from');
        $periodTo = $request->getQuery('period_to');

        $pdo = Database::getInstance();

        $where = ["t.tenant_id = :tenant_id"];
        $params = ['tenant_id' => $tenantId];

        if ($employeeId) {
            $where[] = "t.employee_id = :employee_id";
            $params['employee_id'] = $employeeId;
        }

        // Employeeの場合は自分のみ
        if ($role === Role::EMPLOYEE) {
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE user_id = :user_id AND tenant_id = :tenant_id
            ");
            $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
            $employee = $stmt->fetch();
            if ($employee) {
                $where[] = "t.employee_id = :employee_id";
                $params['employee_id'] = $employee['id'];
            } else {
                $where[] = "1=0"; // 該当なし
            }
        }

        if ($status) {
            $where[] = "t.status = :status";
            $params['status'] = $status;
        }

        if ($periodFrom) {
            $where[] = "t.period_to >= :period_from";
            $params['period_from'] = $periodFrom;
        }

        if ($periodTo) {
            $where[] = "t.period_from <= :period_to";
            $params['period_to'] = $periodTo;
        }

        $whereClause = implode(' AND ', $where);

        // 総件数取得
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM timesheets t
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // データ取得
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.tenant_id,
                t.employee_id,
                ep.employee_code,
                ep.name as employee_name,
                t.period_from,
                t.period_to,
                t.status,
                t.totals,
                t.submitted_at,
                t.approved_by,
                u.name as approver_name,
                t.approved_at,
                t.rejection_reason,
                t.created_at,
                t.updated_at
            FROM timesheets t
            JOIN employee_profiles ep ON t.employee_id = ep.id
            LEFT JOIN users u ON t.approved_by = u.id
            WHERE $whereClause
            ORDER BY t.period_from DESC, t.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        $stmt->execute($params);
        $timesheets = $stmt->fetchAll();

        // JSONBフィールドをデコード
        foreach ($timesheets as &$timesheet) {
            if ($timesheet['totals']) {
                $timesheet['totals'] = is_string($timesheet['totals']) ? json_decode($timesheet['totals'], true) : $timesheet['totals'];
            }
        }

        $response->paginated($timesheets, $total, $page, $limit);
    }

    /**
     * タイムシート詳細取得
     * GET /timesheets/{id}
     */
    public function show(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $pdo = Database::getInstance();

        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.tenant_id,
                t.employee_id,
                ep.employee_code,
                ep.name as employee_name,
                t.period_from,
                t.period_to,
                t.status,
                t.totals,
                t.submitted_at,
                t.approved_by,
                u.name as approver_name,
                t.approved_at,
                t.rejection_reason,
                t.created_at,
                t.updated_at
            FROM timesheets t
            JOIN employee_profiles ep ON t.employee_id = ep.id
            LEFT JOIN users u ON t.approved_by = u.id
            WHERE t.id = :id AND t.tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $timesheet = $stmt->fetch();

        if (!$timesheet) {
            $response->error('NOT_FOUND', 'タイムシートが見つかりません', [], 404);
            return;
        }

        // Employeeの場合は自分のみ
        if ($role === Role::EMPLOYEE) {
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE user_id = :user_id AND id = :employee_id
            ");
            $stmt->execute(['user_id' => $userId, 'employee_id' => $timesheet['employee_id']]);
            if (!$stmt->fetch()) {
                $response->error('FORBIDDEN', 'このタイムシートにアクセスする権限がありません', [], 403);
                return;
            }
        }

        // JSONBフィールドをデコード
        if ($timesheet['totals']) {
            $timesheet['totals'] = is_string($timesheet['totals']) ? json_decode($timesheet['totals'], true) : $timesheet['totals'];
        }

        $response->success($timesheet);
    }

    /**
     * タイムシート申請
     * POST /timesheets/{id}/submit
     */
    public function submit(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $pdo = Database::getInstance();

        // タイムシートを取得
        $stmt = $pdo->prepare("
            SELECT t.*, ep.user_id as employee_user_id
            FROM timesheets t
            JOIN employee_profiles ep ON t.employee_id = ep.id
            WHERE t.id = :id AND t.tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $timesheet = $stmt->fetch();

        if (!$timesheet) {
            $response->error('NOT_FOUND', 'タイムシートが見つかりません', [], 404);
            return;
        }

        // Employeeの場合は自分のみ
        if ($role === Role::EMPLOYEE) {
            if ($timesheet['employee_user_id'] !== $userId) {
                $response->error('FORBIDDEN', 'このタイムシートを申請する権限がありません', [], 403);
                return;
            }
        }

        // 状態チェック
        if ($timesheet['status'] !== Status::TIMESHEET_DRAFT) {
            $response->error('VALIDATION_ERROR', '下書き状態のタイムシートのみ申請できます', [], 400);
            return;
        }

        try {
            $pdo->beginTransaction();

            // タイムシートを申請中に更新
            $stmt = $pdo->prepare("
                UPDATE timesheets 
                SET status = :status_submitted,
                    submitted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'status_submitted' => Status::TIMESHEET_SUBMITTED,
            ]);

            $pdo->commit();

            // 更新したタイムシートを取得
            $stmt = $pdo->prepare("
                SELECT 
                    t.id,
                    t.tenant_id,
                    t.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    t.period_from,
                    t.period_to,
                    t.status,
                    t.totals,
                    t.submitted_at,
                    t.approved_by,
                    u.name as approver_name,
                    t.approved_at,
                    t.rejection_reason,
                    t.created_at,
                    t.updated_at
                FROM timesheets t
                JOIN employee_profiles ep ON t.employee_id = ep.id
                LEFT JOIN users u ON t.approved_by = u.id
                WHERE t.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $updatedTimesheet = $stmt->fetch();

            // JSONBフィールドをデコード
            if ($updatedTimesheet['totals']) {
                $updatedTimesheet['totals'] = is_string($updatedTimesheet['totals']) ? json_decode($updatedTimesheet['totals'], true) : $updatedTimesheet['totals'];
            }

            // 監査ログ記録
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'timesheet.submit', 'timesheet', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode(['status' => Status::TIMESHEET_DRAFT]),
                'after' => json_encode(['status' => Status::TIMESHEET_SUBMITTED]),
            ]);

            $response->success($updatedTimesheet, 'タイムシートを申請しました', 200);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'タイムシートの申請に失敗しました', $e, [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'タイムシートの申請に失敗しました', $e, [], 500);
        }
    }

    /**
     * タイムシート承認
     * POST /timesheets/{id}/approve
     */
    public function approve(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $comment = $request->getBody('comment');

        // 権限チェック
        if (!in_array($role, ['CompanyAdmin', 'Manager', 'Professional'])) {
            $response->error('FORBIDDEN', 'タイムシートを承認する権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        // タイムシートを取得
        $stmt = $pdo->prepare("
            SELECT * FROM timesheets
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $timesheet = $stmt->fetch();

        if (!$timesheet) {
            $response->error('NOT_FOUND', 'タイムシートが見つかりません', [], 404);
            return;
        }

        // 状態チェック
        if ($timesheet['status'] !== Status::TIMESHEET_SUBMITTED) {
            $response->error('VALIDATION_ERROR', '申請中のタイムシートのみ承認できます', [], 400);
            return;
        }

        try {
            $pdo->beginTransaction();

            // タイムシートを承認済みに更新
            $stmt = $pdo->prepare("
                UPDATE timesheets 
                SET status = :status_approved,
                    approved_by = :approved_by,
                    approved_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'approved_by' => $userId,
                'status_approved' => Status::TIMESHEET_APPROVED,
            ]);

            $pdo->commit();

            // 更新したタイムシートを取得
            $stmt = $pdo->prepare("
                SELECT 
                    t.id,
                    t.tenant_id,
                    t.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    t.period_from,
                    t.period_to,
                    t.status,
                    t.totals,
                    t.submitted_at,
                    t.approved_by,
                    u.name as approver_name,
                    t.approved_at,
                    t.rejection_reason,
                    t.created_at,
                    t.updated_at
                FROM timesheets t
                JOIN employee_profiles ep ON t.employee_id = ep.id
                LEFT JOIN users u ON t.approved_by = u.id
                WHERE t.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $approvedTimesheet = $stmt->fetch();

            // JSONBフィールドをデコード
            if ($approvedTimesheet['totals']) {
                $approvedTimesheet['totals'] = is_string($approvedTimesheet['totals']) ? json_decode($approvedTimesheet['totals'], true) : $approvedTimesheet['totals'];
            }

            // 監査ログ記録
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'timesheet.approve', 'timesheet', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode(['status' => Status::TIMESHEET_SUBMITTED]),
                'after' => json_encode([
                    'status' => Status::TIMESHEET_APPROVED,
                    'approved_by' => $userId,
                ]),
            ]);

            $response->success($approvedTimesheet, 'タイムシートを承認しました', 200);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'タイムシートの承認に失敗しました', $e, [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'タイムシートの承認に失敗しました', $e, [], 500);
        }
    }

    /**
     * タイムシート差戻し
     * POST /timesheets/{id}/reject
     */
    public function reject(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $rejectionReason = $request->getBody('rejection_reason');

        // バリデーション
        if (empty($rejectionReason)) {
            $response->error('VALIDATION_ERROR', '差戻し理由は必須です', [], 400);
            return;
        }

        // 権限チェック
        if (!in_array($role, ['CompanyAdmin', 'Manager', 'Professional'])) {
            $response->error('FORBIDDEN', 'タイムシートを差戻しする権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        // タイムシートを取得
        $stmt = $pdo->prepare("
            SELECT * FROM timesheets
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $timesheet = $stmt->fetch();

        if (!$timesheet) {
            $response->error('NOT_FOUND', 'タイムシートが見つかりません', [], 404);
            return;
        }

        // 状態チェック
        if ($timesheet['status'] !== Status::TIMESHEET_SUBMITTED) {
            $response->error('VALIDATION_ERROR', '申請中のタイムシートのみ差戻しできます', [], 400);
            return;
        }

        try {
            $pdo->beginTransaction();

            // タイムシートを差戻しに更新
            $stmt = $pdo->prepare("
                UPDATE timesheets 
                SET status = :status_rejected,
                    rejection_reason = :rejection_reason,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'rejection_reason' => $rejectionReason,
                'status_rejected' => Status::TIMESHEET_REJECTED,
            ]);

            $pdo->commit();

            // 更新したタイムシートを取得
            $stmt = $pdo->prepare("
                SELECT 
                    t.id,
                    t.tenant_id,
                    t.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    t.period_from,
                    t.period_to,
                    t.status,
                    t.totals,
                    t.submitted_at,
                    t.approved_by,
                    u.name as approver_name,
                    t.approved_at,
                    t.rejection_reason,
                    t.created_at,
                    t.updated_at
                FROM timesheets t
                JOIN employee_profiles ep ON t.employee_id = ep.id
                LEFT JOIN users u ON t.approved_by = u.id
                WHERE t.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $rejectedTimesheet = $stmt->fetch();

            // JSONBフィールドをデコード
            if ($rejectedTimesheet['totals']) {
                $rejectedTimesheet['totals'] = is_string($rejectedTimesheet['totals']) ? json_decode($rejectedTimesheet['totals'], true) : $rejectedTimesheet['totals'];
            }

            // 監査ログ記録
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'timesheet.reject', 'timesheet', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode(['status' => Status::TIMESHEET_SUBMITTED]),
                'after' => json_encode([
                    'status' => Status::TIMESHEET_REJECTED,
                    'rejection_reason' => $rejectionReason,
                ]),
            ]);

            $response->success($rejectedTimesheet, 'タイムシートを差戻ししました', 200);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'タイムシートの差戻しに失敗しました', $e, [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'タイムシートの差戻しに失敗しました', $e, [], 500);
        }
    }
}

