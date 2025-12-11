<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use PDO;

/**
 * テナント管理コントローラー
 */
class TenantController
{
    /**
     * テナント一覧取得
     * GET /tenants
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $pdo = Database::getInstance();

        // SystemAdminの場合は全テナントを取得
        if ($role === Role::SYSTEM_ADMIN) {
            $stmt = $pdo->prepare("
                SELECT id, name, timezone, locale, status, logo_path, created_at, updated_at
                FROM tenants
                WHERE deleted_at IS NULL
                ORDER BY created_at DESC
            ");
            $stmt->execute();
        } else {
            // それ以外は自分のテナントのみ
            $stmt = $pdo->prepare("
                SELECT id, name, timezone, locale, status, logo_path, created_at, updated_at
                FROM tenants
                WHERE id = :tenant_id AND deleted_at IS NULL
            ");
            $stmt->execute(['tenant_id' => $tenantId]);
        }

        $tenants = $stmt->fetchAll();

        $response->success($tenants);
    }

    /**
     * テナント詳細取得
     * GET /tenants/{id}
     */
    public function show(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');

        // 権限チェック（SystemAdminまたは自分のテナントのみ）
        if ($role !== 'SystemAdmin' && $id !== $tenantId) {
            $response->error('FORBIDDEN', 'このテナントにアクセスする権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT id, name, timezone, locale, status, logo_path, created_at, updated_at
            FROM tenants
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            $response->error('NOT_FOUND', 'テナントが見つかりません', [], 404);
            return;
        }

        $response->success($tenant);
    }

    /**
     * テナント更新
     * PUT /tenants/{id}
     */
    public function update(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック（CompanyAdmin以上のみ）
        if ($role !== Role::SYSTEM_ADMIN && $role !== Role::COMPANY_ADMIN) {
            $response->error('FORBIDDEN', 'テナントを更新する権限がありません', [], 403);
            return;
        }

        if ($role !== 'SystemAdmin' && $id !== $tenantId) {
            $response->error('FORBIDDEN', 'このテナントを更新する権限がありません', [], 403);
            return;
        }

        $name = $request->getBody('name');
        $timezone = $request->getBody('timezone');
        $locale = $request->getBody('locale');

        $pdo = Database::getInstance();

        // 現在の値を取得（監査ログ用）
        $stmt = $pdo->prepare("
            SELECT name, timezone, locale, status
            FROM tenants
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        $current = $stmt->fetch();

        if (!$current) {
            $response->error('NOT_FOUND', 'テナントが見つかりません', [], 404);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 更新
            $updateFields = [];
            $params = ['id' => $id];

            if ($name !== null) {
                $updateFields[] = "name = :name";
                $params['name'] = $name;
            }
            if ($timezone !== null) {
                $updateFields[] = "timezone = :timezone";
                $params['timezone'] = $timezone;
            }
            if ($locale !== null) {
                $updateFields[] = "locale = :locale";
                $params['locale'] = $locale;
            }

            if (empty($updateFields)) {
                $pdo->rollBack();
                $response->error('VALIDATION_ERROR', '更新する項目がありません', [], 400);
                return;
            }

            $sql = "UPDATE tenants SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'tenant.update', 'tenant', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $id,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode($current),
                'after' => json_encode(array_merge($current, array_filter([
                    'name' => $name,
                    'timezone' => $timezone,
                    'locale' => $locale,
                ], function($v) { return $v !== null; }))),
            ]);

            $pdo->commit();

            // 更新後のデータを取得
            $stmt = $pdo->prepare("
                SELECT id, name, timezone, locale, status, logo_path, created_at, updated_at
                FROM tenants
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
            $tenant = $stmt->fetch();

            $response->success($tenant, 'テナントを更新しました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'テナントの更新に失敗しました', $e, [], 500);
        }
    }
}

