<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use PDO;

/**
 * 監査ログ管理コントローラー
 */
class AuditLogController
{
    /**
     * 監査ログ一覧取得
     * GET /audit-logs
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック（SystemAdmin, CompanyAdmin, Professionalのみ）
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '監査ログを閲覧する権限がありません', [], 403);
            return;
        }

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 50));
        $offset = ($page - 1) * $limit;

        $action = $request->getQuery('action');
        $entity = $request->getQuery('entity');
        $actorUserId = $request->getQuery('actor_user_id');
        $startDate = $request->getQuery('start_date');
        $endDate = $request->getQuery('end_date');

        $pdo = Database::getInstance();

        // フィルタ条件
        $where = [];
        $params = [];

        // SystemAdminの場合は全テナント、それ以外は自分のテナントのみ
        if ($role === Role::SYSTEM_ADMIN) {
            // 全テナントを対象
        } else {
            $where[] = "al.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }

        if ($action) {
            $where[] = "al.action = :action";
            $params['action'] = $action;
        }

        if ($entity) {
            $where[] = "al.entity = :entity";
            $params['entity'] = $entity;
        }

        if ($actorUserId) {
            $where[] = "al.actor_user_id = :actor_user_id";
            $params['actor_user_id'] = $actorUserId;
        }

        if ($startDate) {
            $where[] = "al.occurred_at >= :start_date::timestamp";
            $params['start_date'] = $startDate;
        }

        if ($endDate) {
            $where[] = "al.occurred_at < (:end_date::timestamp + INTERVAL '1 day')";
            $params['end_date'] = $endDate;
        }

        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);

        // 総件数取得
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM audit_logs al
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // データ取得
        $stmt = $pdo->prepare("
            SELECT 
                al.id,
                al.tenant_id,
                al.actor_user_id,
                u.name as actor_user_name,
                ep.employee_code as actor_employee_code,
                al.action,
                al.entity,
                al.entity_id,
                al.before,
                al.after,
                al.ip_address,
                al.user_agent,
                al.occurred_at
            FROM audit_logs al
            LEFT JOIN users u ON al.actor_user_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id AND ep.tenant_id = al.tenant_id
            WHERE $whereClause
            ORDER BY al.occurred_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // JSONBフィールドをデコード
        foreach ($logs as &$log) {
            if ($log['before']) {
                $log['before'] = is_string($log['before']) ? json_decode($log['before'], true) : $log['before'];
            }
            if ($log['after']) {
                $log['after'] = is_string($log['after']) ? json_decode($log['after'], true) : $log['after'];
            }
        }

        $response->paginated($logs, $total, $page, $limit);
    }

    /**
     * 監査ログ詳細取得
     * GET /audit-logs/{id}
     */
    public function show(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, ['SystemAdmin', 'CompanyAdmin', 'Professional'])) {
            $response->error('FORBIDDEN', '監査ログを閲覧する権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        $where = ["al.id = :id"];
        $params = ['id' => $id];

        // SystemAdmin以外は自分のテナントのみ
        if ($role !== 'SystemAdmin') {
            $where[] = "al.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT 
                al.id,
                al.tenant_id,
                t.name as tenant_name,
                al.actor_user_id,
                u.name as actor_user_name,
                u.email as actor_user_email,
                ep.employee_code as actor_employee_code,
                ep.name as actor_employee_name,
                al.action,
                al.entity,
                al.entity_id,
                al.before,
                al.after,
                al.ip_address,
                al.user_agent,
                al.occurred_at
            FROM audit_logs al
            LEFT JOIN tenants t ON al.tenant_id = t.id
            LEFT JOIN users u ON al.actor_user_id = u.id
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id AND ep.tenant_id = al.tenant_id
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $log = $stmt->fetch();

        if (!$log) {
            $response->error('NOT_FOUND', '監査ログが見つかりません', [], 404);
            return;
        }

        // JSONBフィールドをデコード
        if ($log['before']) {
            $log['before'] = is_string($log['before']) ? json_decode($log['before'], true) : $log['before'];
        }
        if ($log['after']) {
            $log['after'] = is_string($log['after']) ? json_decode($log['after'], true) : $log['after'];
        }

        $response->success($log);
    }
}

