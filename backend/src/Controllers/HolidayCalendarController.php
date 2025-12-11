<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use PDO;

/**
 * 祝日カレンダー管理コントローラー
 */
class HolidayCalendarController
{
    /**
     * 祝日一覧取得
     * GET /holidays
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $scopeType = $request->getQuery('scope_type', 'company');
        $scopeId = $request->getQuery('scope_id');
        $year = $request->getQuery('year');

        $pdo = Database::getInstance();

        $where = ["tenant_id = :tenant_id", "scope_type = :scope_type"];
        $params = ['tenant_id' => $tenantId, 'scope_type' => $scopeType];

        if ($scopeId) {
            $where[] = "scope_id = :scope_id";
            $params['scope_id'] = $scopeId;
        } else {
            $where[] = "scope_id IS NULL";
        }

        if ($year) {
            $where[] = "EXTRACT(YEAR FROM date) = :year";
            $params['year'] = $year;
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT id, date, name, type, created_at, updated_at
            FROM holiday_calendars
            WHERE $whereClause
            ORDER BY date
        ");
        $stmt->execute($params);
        $holidays = $stmt->fetchAll();

        $response->success($holidays);
    }

    /**
     * 祝日作成
     * POST /holidays
     */
    public function create(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '祝日を作成する権限がありません', [], 403);
            return;
        }

        $scopeType = $request->getBody('scope_type', 'company');
        $scopeId = $request->getBody('scope_id');
        $date = $request->getBody('date');
        $name = $request->getBody('name');
        $type = $request->getBody('type', 'holiday');

        // バリデーション
        if (empty($date) || empty($name)) {
            $response->error('VALIDATION_ERROR', '日付と名称は必須です', [], 400);
            return;
        }

        $validTypes = ['holiday', 'company_holiday', 'special'];
        if (!in_array($type, $validTypes)) {
            $response->error('VALIDATION_ERROR', '無効な祝日種類です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // 重複チェック
            $where = ["tenant_id = :tenant_id", "scope_type = :scope_type", "date = :date"];
            $params = ['tenant_id' => $tenantId, 'scope_type' => $scopeType, 'date' => $date];

            if ($scopeId) {
                $where[] = "scope_id = :scope_id";
                $params['scope_id'] = $scopeId;
            } else {
                $where[] = "scope_id IS NULL";
            }

            $whereClause = implode(' AND ', $where);
            $stmt = $pdo->prepare("SELECT id FROM holiday_calendars WHERE $whereClause");
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $response->error('CONFLICT', 'この日付の祝日は既に登録されています', [], 409);
                return;
            }

            // 祝日作成
            $stmt = $pdo->prepare("
                INSERT INTO holiday_calendars (tenant_id, scope_type, scope_id, date, name, type)
                VALUES (:tenant_id, :scope_type, :scope_id, :date, :name, :type)
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'date' => $date,
                'name' => $name,
                'type' => $type,
            ]);
            $holiday = $stmt->fetch();
            $holidayId = $holiday['id'];

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'holiday.create', 'holiday', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $holidayId,
                'after' => json_encode(['date' => $date, 'name' => $name, 'type' => $type]),
            ]);

            $pdo->commit();

            // 作成した祝日を取得
            $stmt = $pdo->prepare("
                SELECT id, date, name, type, created_at, updated_at
                FROM holiday_calendars
                WHERE id = :id
            ");
            $stmt->execute(['id' => $holidayId]);
            $createdHoliday = $stmt->fetch();

            $response->success($createdHoliday, '祝日を作成しました', 201);

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '祝日の作成に失敗しました', $e, [], 500);
        }
    }

    /**
     * 祝日更新
     * PUT /holidays/{id}
     */
    public function update(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '祝日を更新する権限がありません', [], 403);
            return;
        }

        $name = $request->getBody('name');
        $type = $request->getBody('type');

        $pdo = Database::getInstance();

        // 現在の値を取得
        $stmt = $pdo->prepare("
            SELECT * FROM holiday_calendars 
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();

        if (!$current) {
            $response->error('NOT_FOUND', '祝日が見つかりません', [], 404);
            return;
        }

        try {
            $pdo->beginTransaction();

            $updateFields = [];
            $params = ['id' => $id];

            if ($name !== null) {
                $updateFields[] = "name = :name";
                $params['name'] = $name;
            }
            if ($type !== null) {
                $validTypes = ['holiday', 'company_holiday', 'special'];
                if (!in_array($type, $validTypes)) {
                    $pdo->rollBack();
                    $response->error('VALIDATION_ERROR', '無効な祝日種類です', [], 400);
                    return;
                }
                $updateFields[] = "type = :type";
                $params['type'] = $type;
            }

            if (empty($updateFields)) {
                $pdo->rollBack();
                $response->error('VALIDATION_ERROR', '更新する項目がありません', [], 400);
                return;
            }

            $sql = "UPDATE holiday_calendars SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // 監査ログ
            $after = array_merge($current, array_filter($params, function($key) {
                return in_array($key, ['name', 'type']);
            }, ARRAY_FILTER_USE_KEY));

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'holiday.update', 'holiday', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode($current),
                'after' => json_encode($after),
            ]);

            $pdo->commit();

            // 更新後のデータを取得
            $stmt = $pdo->prepare("
                SELECT id, date, name, type, created_at, updated_at
                FROM holiday_calendars
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
            $holiday = $stmt->fetch();

            $response->success($holiday, '祝日を更新しました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '祝日の更新に失敗しました', $e, [], 500);
        }
    }

    /**
     * 祝日削除
     * DELETE /holidays/{id}
     */
    public function delete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '祝日を削除する権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        // 現在の値を取得
        $stmt = $pdo->prepare("
            SELECT * FROM holiday_calendars 
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();

        if (!$current) {
            $response->error('NOT_FOUND', '祝日が見つかりません', [], 404);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 削除
            $stmt = $pdo->prepare("DELETE FROM holiday_calendars WHERE id = :id");
            $stmt->execute(['id' => $id]);

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before)
                VALUES (:tenant_id, :actor_user_id, 'holiday.delete', 'holiday', :entity_id, :before)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode($current),
            ]);

            $pdo->commit();

            $response->success(null, '祝日を削除しました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '祝日の削除に失敗しました', $e, [], 500);
        }
    }
}

