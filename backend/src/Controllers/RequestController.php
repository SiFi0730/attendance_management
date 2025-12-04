<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * 申請管理コントローラー
 */
class RequestController
{
    /**
     * 申請作成
     * POST /requests
     */
    public function create(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $type = $request->getBody('type');
        $targetDate = $request->getBody('target_date');
        $payload = $request->getBody('payload');
        $reason = $request->getBody('reason');
        $attachmentPaths = $request->getBody('attachment_paths');

        // バリデーション
        if (empty($type) || empty($targetDate) || empty($reason)) {
            $response->error('VALIDATION_ERROR', '申請種類、対象日、申請理由は必須です', [], 400);
            return;
        }

        // 申請種類の検証
        $validTypes = ['edit', 'overtime', 'leave', 'others'];
        if (!in_array($type, $validTypes)) {
            $response->error('VALIDATION_ERROR', '無効な申請種類です。edit, overtime, leave, others のいずれかを指定してください', [], 400);
            return;
        }

        // 日付の検証
        $targetDateTimestamp = strtotime($targetDate);
        if ($targetDateTimestamp === false) {
            $response->error('VALIDATION_ERROR', '無効な日付形式です', [], 400);
            return;
        }

        // payloadの検証（JSON形式）
        if ($payload && !is_array($payload)) {
            $response->error('VALIDATION_ERROR', '申請内容はJSON形式で指定してください', [], 400);
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
            $response->error('VALIDATION_ERROR', '在籍中の従業員のみ申請できます', [], 400);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 申請を作成（IDはデータベースのデフォルト値を使用）
            $stmt = $pdo->prepare("
                INSERT INTO requests (
                    tenant_id, employee_id, type, target_date, payload, 
                    status, attachment_paths, reason, created_at, updated_at
                ) VALUES (
                    :tenant_id, :employee_id, :type, :target_date, :payload,
                    'pending', :attachment_paths, :reason, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'employee_id' => $employee['id'],
                'type' => $type,
                'target_date' => $targetDate,
                'payload' => json_encode($payload ?: []),
                'attachment_paths' => $attachmentPaths ? json_encode($attachmentPaths) : null,
                'reason' => $reason,
            ]);
            $result = $stmt->fetch();
            $requestId = $result['id'];

            $pdo->commit();

            // 作成した申請を取得
            $stmt = $pdo->prepare("
                SELECT 
                    r.id,
                    r.tenant_id,
                    r.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    r.type,
                    r.target_date,
                    r.payload,
                    r.status,
                    r.attachment_paths,
                    r.approver_id,
                    u.name as approver_name,
                    r.decided_at,
                    r.reason,
                    r.rejection_reason,
                    r.created_at,
                    r.updated_at
                FROM requests r
                JOIN employee_profiles ep ON r.employee_id = ep.id
                LEFT JOIN users u ON r.approver_id = u.id
                WHERE r.id = :id
            ");
            $stmt->execute(['id' => $requestId]);
            $createdRequest = $stmt->fetch();

            // 監査ログ記録
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'request.create', 'request', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $requestId,
                'after' => json_encode([
                    'type' => $type,
                    'target_date' => $targetDate,
                ])
            ]);

            $response->success($createdRequest, '申請を作成しました', 201);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '申請の作成に失敗しました: ' . $e->getMessage(), [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '申請の作成に失敗しました: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * 申請一覧取得
     * GET /requests
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
        $where = ["r.tenant_id = :tenant_id"];
        $params = ['tenant_id' => $tenantId];

        // Employeeの場合は自分の申請のみ
        if ($role === 'Employee') {
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE user_id = :user_id AND tenant_id = :tenant_id AND deleted_at IS NULL
            ");
            $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
            $employee = $stmt->fetch();
            if ($employee) {
                $where[] = "r.employee_id = :employee_id";
                $params['employee_id'] = $employee['id'];
            } else {
                // 従業員プロファイルが存在しない場合は空の結果を返す
                $response->paginated([], 0, $page, $limit);
                return;
            }
        }

        // 従業員IDフィルタ
        $employeeId = $request->getQuery('employee_id');
        if ($employeeId) {
            $where[] = "r.employee_id = :filter_employee_id";
            $params['filter_employee_id'] = $employeeId;
        }

        // 申請種類フィルタ
        $type = $request->getQuery('type');
        if ($type) {
            $validTypes = ['edit', 'overtime', 'leave', 'others'];
            if (in_array($type, $validTypes)) {
                $where[] = "r.type = :type";
                $params['type'] = $type;
            }
        }

        // ステータスフィルタ
        $status = $request->getQuery('status');
        if ($status) {
            $validStatuses = ['pending', 'approved', 'rejected'];
            if (in_array($status, $validStatuses)) {
                $where[] = "r.status = :status";
                $params['status'] = $status;
            }
        }

        // 対象日フィルタ
        $targetDate = $request->getQuery('target_date');
        if ($targetDate) {
            $where[] = "r.target_date = :target_date";
            $params['target_date'] = $targetDate;
        }

        $whereClause = implode(' AND ', $where);

        // 総件数取得
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM requests r
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // データ取得
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.tenant_id,
                r.employee_id,
                ep.employee_code,
                ep.name as employee_name,
                r.type,
                r.target_date,
                r.payload,
                r.status,
                r.attachment_paths,
                r.approver_id,
                u.name as approver_name,
                r.decided_at,
                r.reason,
                r.rejection_reason,
                r.created_at,
                r.updated_at
            FROM requests r
            JOIN employee_profiles ep ON r.employee_id = ep.id
            LEFT JOIN users u ON r.approver_id = u.id
            WHERE $whereClause
            ORDER BY r.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        $response->paginated($requests, $total, $page, $limit);
    }

    /**
     * 申請詳細取得
     * GET /requests/{id}
     */
    public function show(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.tenant_id,
                r.employee_id,
                ep.employee_code,
                ep.name as employee_name,
                r.type,
                r.target_date,
                r.payload,
                r.status,
                r.attachment_paths,
                r.approver_id,
                u.name as approver_name,
                r.decided_at,
                r.reason,
                r.rejection_reason,
                r.created_at,
                r.updated_at
            FROM requests r
            JOIN employee_profiles ep ON r.employee_id = ep.id
            LEFT JOIN users u ON r.approver_id = u.id
            WHERE r.id = :id AND r.tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $requestData = $stmt->fetch();

        if (!$requestData) {
            $response->error('NOT_FOUND', '申請が見つかりません', [], 404);
            return;
        }

        // Employeeの場合は自分の申請のみアクセス可能
        if ($role === 'Employee') {
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE user_id = :user_id AND id = :employee_id
            ");
            $stmt->execute(['user_id' => $userId, 'employee_id' => $requestData['employee_id']]);
            if (!$stmt->fetch()) {
                $response->error('FORBIDDEN', 'この申請にアクセスする権限がありません', [], 403);
                return;
            }
        }

        $response->success($requestData);
    }

    /**
     * 申請承認
     * POST /requests/{id}/approve
     */
    public function approve(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, ['SystemAdmin', 'CompanyAdmin', 'Manager', 'Professional'])) {
            $response->error('FORBIDDEN', '承認の権限がありません', [], 403);
            return;
        }

        $comment = $request->getBody('comment');

        $pdo = Database::getInstance();

        // 申請を取得
        $stmt = $pdo->prepare("
            SELECT r.*, ep.user_id as employee_user_id
            FROM requests r
            JOIN employee_profiles ep ON r.employee_id = ep.id
            WHERE r.id = :id AND r.tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $requestData = $stmt->fetch();

        if (!$requestData) {
            $response->error('NOT_FOUND', '申請が見つかりません', [], 404);
            return;
        }

        if ($requestData['status'] !== 'pending') {
            $response->error('VALIDATION_ERROR', '申請中の申請のみ承認できます', [], 400);
            return;
        }

        // Managerの場合は配下の従業員のみ
        if ($role === 'Manager') {
            // TODO: 部署階層を考慮した配下従業員のチェック
            // 現時点では全従業員を許可
        }

        try {
            $pdo->beginTransaction();

            // 申請を承認
            $stmt = $pdo->prepare("
                UPDATE requests 
                SET status = 'approved',
                    approver_id = :approver_id,
                    decided_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'approver_id' => $userId,
            ]);

            $pdo->commit();

            // 更新した申請を取得
            $stmt = $pdo->prepare("
                SELECT 
                    r.id,
                    r.tenant_id,
                    r.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    r.type,
                    r.target_date,
                    r.payload,
                    r.status,
                    r.attachment_paths,
                    r.approver_id,
                    u.name as approver_name,
                    r.decided_at,
                    r.reason,
                    r.rejection_reason,
                    r.created_at,
                    r.updated_at
                FROM requests r
                JOIN employee_profiles ep ON r.employee_id = ep.id
                LEFT JOIN users u ON r.approver_id = u.id
                WHERE r.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $approvedRequest = $stmt->fetch();

            // 監査ログ記録
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'request.approve', 'request', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode(['status' => 'pending']),
                'after' => json_encode([
                    'status' => 'approved',
                    'approver_id' => $userId,
                ])
            ]);

            $response->success($approvedRequest, '申請を承認しました', 200);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '申請の承認に失敗しました: ' . $e->getMessage(), [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '申請の承認に失敗しました: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * 申請差戻し
     * POST /requests/{id}/reject
     */
    public function reject(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, ['SystemAdmin', 'CompanyAdmin', 'Manager', 'Professional'])) {
            $response->error('FORBIDDEN', '差戻しの権限がありません', [], 403);
            return;
        }

        $rejectionReason = $request->getBody('rejection_reason');

        // 差戻し理由は必須
        if (empty($rejectionReason)) {
            $response->error('VALIDATION_ERROR', '差戻し理由は必須です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // 申請を取得
        $stmt = $pdo->prepare("
            SELECT r.*, ep.user_id as employee_user_id
            FROM requests r
            JOIN employee_profiles ep ON r.employee_id = ep.id
            WHERE r.id = :id AND r.tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $requestData = $stmt->fetch();

        if (!$requestData) {
            $response->error('NOT_FOUND', '申請が見つかりません', [], 404);
            return;
        }

        if ($requestData['status'] !== 'pending') {
            $response->error('VALIDATION_ERROR', '申請中の申請のみ差戻しできます', [], 400);
            return;
        }

        // Managerの場合は配下の従業員のみ
        if ($role === 'Manager') {
            // TODO: 部署階層を考慮した配下従業員のチェック
            // 現時点では全従業員を許可
        }

        try {
            $pdo->beginTransaction();

            // 申請を差戻し
            $stmt = $pdo->prepare("
                UPDATE requests 
                SET status = 'rejected',
                    approver_id = :approver_id,
                    decided_at = CURRENT_TIMESTAMP,
                    rejection_reason = :rejection_reason,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'approver_id' => $userId,
                'rejection_reason' => $rejectionReason,
            ]);

            $pdo->commit();

            // 更新した申請を取得
            $stmt = $pdo->prepare("
                SELECT 
                    r.id,
                    r.tenant_id,
                    r.employee_id,
                    ep.employee_code,
                    ep.name as employee_name,
                    r.type,
                    r.target_date,
                    r.payload,
                    r.status,
                    r.attachment_paths,
                    r.approver_id,
                    u.name as approver_name,
                    r.decided_at,
                    r.reason,
                    r.rejection_reason,
                    r.created_at,
                    r.updated_at
                FROM requests r
                JOIN employee_profiles ep ON r.employee_id = ep.id
                LEFT JOIN users u ON r.approver_id = u.id
                WHERE r.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $rejectedRequest = $stmt->fetch();

            // 監査ログ記録
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'request.reject', 'request', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode(['status' => 'pending']),
                'after' => json_encode([
                    'status' => 'rejected',
                    'approver_id' => $userId,
                    'rejection_reason' => $rejectionReason,
                ])
            ]);

            $response->success($rejectedRequest, '申請を差戻ししました', 200);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '申請の差戻しに失敗しました: ' . $e->getMessage(), [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '申請の差戻しに失敗しました: ' . $e->getMessage(), [], 500);
        }
    }
}

