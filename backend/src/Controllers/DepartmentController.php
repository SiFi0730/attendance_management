<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use PDO;

/**
 * 部署管理コントローラー
 */
class DepartmentController
{
    /**
     * 部署一覧取得
     * GET /departments
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT id, name, code, parent_id, created_at, updated_at
            FROM departments
            WHERE tenant_id = :tenant_id AND deleted_at IS NULL
            ORDER BY name
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $departments = $stmt->fetchAll();

        $response->success($departments);
    }

    /**
     * 部署詳細取得
     * GET /departments/{id}
     */
    public function show(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT id, name, code, parent_id, created_at, updated_at
            FROM departments
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $department = $stmt->fetch();

        if (!$department) {
            $response->error('NOT_FOUND', '部署が見つかりません', [], 404);
            return;
        }

        $response->success($department);
    }

    /**
     * 部署作成
     * POST /departments
     */
    public function create(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '部署を作成する権限がありません', [], 403);
            return;
        }

        $name = $request->getBody('name');
        $code = $request->getBody('code');
        $parentId = $request->getBody('parent_id');

        // バリデーション
        if (empty($name)) {
            $response->error('VALIDATION_ERROR', '部署名は必須です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // 部署コードの重複チェック（コードが指定されている場合）
            if ($code) {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE tenant_id = :tenant_id AND code = :code AND deleted_at IS NULL
                ");
                $stmt->execute(['tenant_id' => $tenantId, 'code' => $code]);
                if ($stmt->fetch()) {
                    $pdo->rollBack();
                    $response->error('CONFLICT', 'この部署コードは既に使用されています', [], 409);
                    return;
                }
            }

            // 親部署の存在確認
            if ($parentId) {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE id = :parent_id AND tenant_id = :tenant_id AND deleted_at IS NULL
                ");
                $stmt->execute(['parent_id' => $parentId, 'tenant_id' => $tenantId]);
                if (!$stmt->fetch()) {
                    $pdo->rollBack();
                    $response->error('NOT_FOUND', '親部署が見つかりません', [], 404);
                    return;
                }
            }

            // 部署作成
            $stmt = $pdo->prepare("
                INSERT INTO departments (tenant_id, name, code, parent_id)
                VALUES (:tenant_id, :name, :code, :parent_id)
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'code' => $code,
                'parent_id' => $parentId,
            ]);
            $department = $stmt->fetch();
            $departmentId = $department['id'];

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'department.create', 'department', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $departmentId,
                'after' => json_encode(['name' => $name, 'code' => $code, 'parent_id' => $parentId]),
            ]);

            $pdo->commit();

            // 作成した部署を取得
            $stmt = $pdo->prepare("
                SELECT id, name, code, parent_id, created_at, updated_at
                FROM departments
                WHERE id = :id
            ");
            $stmt->execute(['id' => $departmentId]);
            $createdDepartment = $stmt->fetch();

            $response->success($createdDepartment, '部署を作成しました', 201);

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '部署の作成に失敗しました', $e, [], 500);
        }
    }

    /**
     * 部署更新
     * PUT /departments/{id}
     */
    public function update(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '部署を更新する権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        // 現在の値を取得
        $stmt = $pdo->prepare("
            SELECT * FROM departments 
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();

        if (!$current) {
            $response->error('NOT_FOUND', '部署が見つかりません', [], 404);
            return;
        }

        $name = $request->getBody('name');
        $code = $request->getBody('code');
        $parentId = $request->getBody('parent_id');

        try {
            $pdo->beginTransaction();

            // 親部署の循環参照チェック（自分自身を親に設定できない）
            if ($parentId === $id) {
                $pdo->rollBack();
                $response->error('VALIDATION_ERROR', '自分自身を親部署に設定することはできません', [], 400);
                return;
            }

            $updateFields = [];
            $params = ['id' => $id];

            if ($name !== null) {
                $updateFields[] = "name = :name";
                $params['name'] = $name;
            }
            if ($code !== null) {
                // コードの重複チェック
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE tenant_id = :tenant_id AND code = :code AND id != :id AND deleted_at IS NULL
                ");
                $stmt->execute(['tenant_id' => $tenantId, 'code' => $code, 'id' => $id]);
                if ($stmt->fetch()) {
                    $pdo->rollBack();
                    $response->error('CONFLICT', 'この部署コードは既に使用されています', [], 409);
                    return;
                }
                $updateFields[] = "code = :code";
                $params['code'] = $code;
            }
            if ($parentId !== null) {
                // 親部署の存在確認
                if ($parentId) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM departments 
                        WHERE id = :parent_id AND tenant_id = :tenant_id AND deleted_at IS NULL
                    ");
                    $stmt->execute(['parent_id' => $parentId, 'tenant_id' => $tenantId]);
                    if (!$stmt->fetch()) {
                        $pdo->rollBack();
                        $response->error('NOT_FOUND', '親部署が見つかりません', [], 404);
                        return;
                    }
                }
                $updateFields[] = "parent_id = :parent_id";
                $params['parent_id'] = $parentId;
            }

            if (empty($updateFields)) {
                $pdo->rollBack();
                $response->error('VALIDATION_ERROR', '更新する項目がありません', [], 400);
                return;
            }

            $sql = "UPDATE departments SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // 監査ログ
            $after = array_merge($current, array_filter($params, function($key) {
                return in_array($key, ['name', 'code', 'parent_id']);
            }, ARRAY_FILTER_USE_KEY));

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'department.update', 'department', :entity_id, :before, :after)
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
                SELECT id, name, code, parent_id, created_at, updated_at
                FROM departments
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
            $department = $stmt->fetch();

            $response->success($department, '部署を更新しました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '部署の更新に失敗しました', $e, [], 500);
        }
    }

    /**
     * 部署削除（論理削除）
     * DELETE /departments/{id}
     */
    public function delete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック
        if (!Role::isAdmin($role)) {
            $response->error('FORBIDDEN', '部署を削除する権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        // 現在の値を取得
        $stmt = $pdo->prepare("
            SELECT * FROM departments 
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();

        if (!$current) {
            $response->error('NOT_FOUND', '部署が見つかりません', [], 404);
            return;
        }

        // 配下部署の確認
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM departments 
            WHERE parent_id = :id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        $childCount = $stmt->fetch()['count'];

        if ($childCount > 0) {
            $response->error('CONFLICT', '配下に部署が存在するため削除できません', [], 409);
            return;
        }

        // 配下従業員の確認
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM employee_profiles 
            WHERE dept_id = :id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id]);
        $employeeCount = $stmt->fetch()['count'];

        if ($employeeCount > 0) {
            $response->error('CONFLICT', '配下に従業員が存在するため削除できません', [], 409);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 論理削除
            $stmt = $pdo->prepare("
                UPDATE departments 
                SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before)
                VALUES (:tenant_id, :actor_user_id, 'department.delete', 'department', :entity_id, :before)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode($current),
            ]);

            $pdo->commit();

            $response->success(null, '部署を削除しました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '部署の削除に失敗しました', $e, [], 500);
        }
    }
}

