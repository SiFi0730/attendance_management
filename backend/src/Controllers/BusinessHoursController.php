<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * 営業時間管理コントローラー
 */
class BusinessHoursController
{
    /**
     * 営業時間一覧取得
     * GET /business-hours
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $scopeType = $request->getQuery('scope_type', 'company');
        $scopeId = $request->getQuery('scope_id');

        $pdo = Database::getInstance();

        $where = ["tenant_id = :tenant_id", "scope_type = :scope_type"];
        $params = ['tenant_id' => $tenantId, 'scope_type' => $scopeType];

        if ($scopeId) {
            $where[] = "scope_id = :scope_id";
            $params['scope_id'] = $scopeId;
        } else {
            $where[] = "scope_id IS NULL";
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT id, weekday, start_time, end_time, break_policy_id, created_at, updated_at
            FROM business_hours
            WHERE $whereClause
            ORDER BY weekday
        ");
        $stmt->execute($params);
        $businessHours = $stmt->fetchAll();

        $response->success($businessHours);
    }

    /**
     * 営業時間更新（一括）
     * PUT /business-hours
     */
    public function update(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, ['SystemAdmin', 'CompanyAdmin', 'Professional'])) {
            $response->error('FORBIDDEN', '営業時間を更新する権限がありません', [], 403);
            return;
        }

        $scopeType = $request->getBody('scope_type', 'company');
        $scopeId = $request->getBody('scope_id');
        $hours = $request->getBody('hours'); // [{weekday: 0, start_time: '09:00', end_time: '18:00'}, ...]

        if (!is_array($hours)) {
            $response->error('VALIDATION_ERROR', '営業時間データが不正です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // 既存の営業時間を削除
            $where = ["tenant_id = :tenant_id", "scope_type = :scope_type"];
            $params = ['tenant_id' => $tenantId, 'scope_type' => $scopeType];

            if ($scopeId) {
                $where[] = "scope_id = :scope_id";
                $params['scope_id'] = $scopeId;
            } else {
                $where[] = "scope_id IS NULL";
            }

            $whereClause = implode(' AND ', $where);
            $stmt = $pdo->prepare("DELETE FROM business_hours WHERE $whereClause");
            $stmt->execute($params);

            // 新しい営業時間を追加
            foreach ($hours as $hour) {
                if (!isset($hour['weekday']) || !isset($hour['start_time']) || !isset($hour['end_time'])) {
                    continue;
                }

                $weekday = (int)$hour['weekday'];
                if ($weekday < 0 || $weekday > 6) {
                    continue;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO business_hours (tenant_id, scope_type, scope_id, weekday, start_time, end_time)
                    VALUES (:tenant_id, :scope_type, :scope_id, :weekday, :start_time, :end_time)
                ");
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'weekday' => $weekday,
                    'start_time' => $hour['start_time'],
                    'end_time' => $hour['end_time'],
                ]);
            }

            $pdo->commit();

            // 更新後のデータを取得
            $this->index($request, $response);

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '営業時間の更新に失敗しました: ' . $e->getMessage(), [], 500);
        }
    }
}

