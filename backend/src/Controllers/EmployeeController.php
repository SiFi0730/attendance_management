<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use App\Core\Constants\Status;
use App\Core\DepartmentHierarchy;
use PDO;

/**
 * 従業員管理コントローラー
 */
class EmployeeController
{
    /**
     * 従業員一覧取得
     * GET /employees
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 20));
        $offset = ($page - 1) * $limit;

        $pdo = Database::getInstance();

        // フィルタ条件
        $where = ["ep.tenant_id = :tenant_id", "ep.deleted_at IS NULL"];
        $params = ['tenant_id' => $tenantId];

        // Managerの場合は配下の従業員のみ
        if ($role === Role::MANAGER) {
            $condition = DepartmentHierarchy::getSubordinateEmployeeCondition($pdo, $tenantId, $userId, 'ep.id');
            if ($condition['where'] === '1=0') {
                // 配下従業員がいない場合は該当なし
                $where[] = '1=0';
            } else {
                $where[] = $condition['where'];
                $params = array_merge($params, $condition['params']);
            }
        }

        $status = $request->getQuery('status');
        if ($status) {
            $where[] = "ep.status = :status";
            $params['status'] = $status;
        }

        $deptId = $request->getQuery('dept_id');
        if ($deptId) {
            $where[] = "ep.dept_id = :dept_id";
            $params['dept_id'] = $deptId;
        }

        $whereClause = implode(' AND ', $where);

        // 総件数取得
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM employee_profiles ep
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // データ取得
        $stmt = $pdo->prepare("
            SELECT 
                ep.id,
                ep.employee_code,
                ep.name,
                ep.email,
                ep.dept_id,
                d.name as department_name,
                ep.employment_type,
                ep.hire_date,
                ep.leave_date,
                ep.work_location_tz,
                ep.status,
                ep.user_id,
                ep.created_at,
                ep.updated_at
            FROM employee_profiles ep
            LEFT JOIN departments d ON ep.dept_id = d.id
            WHERE $whereClause
            ORDER BY ep.employee_code
            LIMIT :limit OFFSET :offset
        ");
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        $response->paginated($employees, $total, $page, $limit);
    }

    /**
     * 従業員詳細取得
     * GET /employees/{id}
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
                ep.id,
                ep.employee_code,
                ep.name,
                ep.email,
                ep.dept_id,
                d.name as department_name,
                ep.employment_type,
                ep.hire_date,
                ep.leave_date,
                ep.work_location_tz,
                ep.status,
                ep.user_id,
                ep.created_at,
                ep.updated_at
            FROM employee_profiles ep
            LEFT JOIN departments d ON ep.dept_id = d.id
            WHERE ep.id = :id AND ep.tenant_id = :tenant_id AND ep.deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            $response->error('NOT_FOUND', '従業員が見つかりません', [], 404);
            return;
        }

        // Employeeの場合は自分の情報のみアクセス可能
        if ($role === Role::EMPLOYEE) {
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE user_id = :user_id AND id = :id
            ");
            $stmt->execute(['user_id' => $userId, 'id' => $id]);
            if (!$stmt->fetch()) {
                $response->error('FORBIDDEN', 'この従業員情報にアクセスする権限がありません', [], 403);
                return;
            }
        }

        $response->success($employee);
    }

    /**
     * 従業員作成
     * POST /employees
     */
    public function create(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック
        if (!in_array($role, ['SystemAdmin', 'CompanyAdmin', 'Professional'])) {
            $response->error('FORBIDDEN', '従業員を作成する権限がありません', [], 403);
            return;
        }

        $employeeCode = $request->getBody('employee_code');
        $name = $request->getBody('name');
        $email = $request->getBody('email');
        $deptId = $request->getBody('dept_id');
        $employmentType = $request->getBody('employment_type');
        $hireDate = $request->getBody('hire_date');
        $workLocationTz = $request->getBody('work_location_tz') ?? 'Asia/Tokyo';

        // バリデーション
        if (empty($employeeCode) || empty($name) || empty($email) || empty($employmentType) || empty($hireDate)) {
            $response->error('VALIDATION_ERROR', '必須項目が不足しています', [], 400);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->error('VALIDATION_ERROR', '有効なメールアドレスを入力してください', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // 従業員コードの重複チェック
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE tenant_id = :tenant_id AND employee_code = :employee_code AND deleted_at IS NULL
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'employee_code' => $employeeCode]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $response->error('CONFLICT', 'この従業員コードは既に使用されています', [], 409);
                return;
            }

            // 従業員作成
            $stmt = $pdo->prepare("
                INSERT INTO employee_profiles (
                    tenant_id, employee_code, name, email, dept_id, 
                    employment_type, hire_date, work_location_tz, status
                )
                VALUES (
                    :tenant_id, :employee_code, :name, :email, :dept_id,
                    :employment_type, :hire_date, :work_location_tz, 'active'
                )
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'employee_code' => $employeeCode,
                'name' => $name,
                'email' => $email,
                'dept_id' => $deptId,
                'employment_type' => $employmentType,
                'hire_date' => $hireDate,
                'work_location_tz' => $workLocationTz,
            ]);
            $employee = $stmt->fetch();
            $employeeId = $employee['id'];

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'employee.create', 'employee', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $employeeId,
                'after' => json_encode([
                    'employee_code' => $employeeCode,
                    'name' => $name,
                    'email' => $email,
                ]),
            ]);

            $pdo->commit();

            // 作成した従業員を取得
            $stmt = $pdo->prepare("
                SELECT 
                    ep.id,
                    ep.employee_code,
                    ep.name,
                    ep.email,
                    ep.dept_id,
                    d.name as department_name,
                    ep.employment_type,
                    ep.hire_date,
                    ep.work_location_tz,
                    ep.status,
                    ep.created_at
                FROM employee_profiles ep
                LEFT JOIN departments d ON ep.dept_id = d.id
                WHERE ep.id = :id
            ");
            $stmt->execute(['id' => $employeeId]);
            $createdEmployee = $stmt->fetch();

            $response->success($createdEmployee, '従業員を作成しました', 201);

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '従業員の作成に失敗しました', $e, [], 500);
        }
    }

    /**
     * 従業員更新
     * PUT /employees/{id}
     */
    public function update(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $pdo = Database::getInstance();

        // 現在の値を取得
        $stmt = $pdo->prepare("
            SELECT * FROM employee_profiles 
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();

        if (!$current) {
            $response->error('NOT_FOUND', '従業員が見つかりません', [], 404);
            return;
        }

        // Employeeの場合は自分の情報のみ更新可能
        if ($role === Role::EMPLOYEE) {
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE user_id = :user_id AND id = :id
            ");
            $stmt->execute(['user_id' => $userId, 'id' => $id]);
            if (!$stmt->fetch()) {
                $response->error('FORBIDDEN', 'この従業員情報を更新する権限がありません', [], 403);
                return;
            }
        } elseif (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '従業員を更新する権限がありません', [], 403);
            return;
        }

        $name = $request->getBody('name');
        $email = $request->getBody('email');
        $deptId = $request->getBody('dept_id');
        $employmentType = $request->getBody('employment_type');
        $hireDate = $request->getBody('hire_date');
        $leaveDate = $request->getBody('leave_date');
        $workLocationTz = $request->getBody('work_location_tz');
        $status = $request->getBody('status');

        try {
            $pdo->beginTransaction();

            $updateFields = [];
            $params = ['id' => $id];

            if ($name !== null) {
                $updateFields[] = "name = :name";
                $params['name'] = $name;
            }
            if ($email !== null) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $pdo->rollBack();
                    $response->error('VALIDATION_ERROR', '有効なメールアドレスを入力してください', [], 400);
                    return;
                }
                $updateFields[] = "email = :email";
                $params['email'] = $email;
            }
            if ($deptId !== null) {
                $updateFields[] = "dept_id = :dept_id";
                $params['dept_id'] = $deptId;
            }
            if ($employmentType !== null) {
                $updateFields[] = "employment_type = :employment_type";
                $params['employment_type'] = $employmentType;
            }
            if ($hireDate !== null) {
                $updateFields[] = "hire_date = :hire_date";
                $params['hire_date'] = $hireDate;
            }
            if ($leaveDate !== null) {
                $updateFields[] = "leave_date = :leave_date";
                $params['leave_date'] = $leaveDate;
            }
            if ($workLocationTz !== null) {
                $updateFields[] = "work_location_tz = :work_location_tz";
                $params['work_location_tz'] = $workLocationTz;
            }
            if ($status !== null && in_array($status, [Status::EMPLOYEE_ACTIVE, Status::EMPLOYEE_ON_LEAVE, Status::EMPLOYEE_RETIRED])) {
                $updateFields[] = "status = :status";
                $params['status'] = $status;
            }

            if (empty($updateFields)) {
                $pdo->rollBack();
                $response->error('VALIDATION_ERROR', '更新する項目がありません', [], 400);
                return;
            }

            $sql = "UPDATE employee_profiles SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // 監査ログ
            $after = array_merge($current, array_filter($params, function($key) {
                return in_array($key, ['name', 'email', 'dept_id', 'employment_type', 'hire_date', 'leave_date', 'work_location_tz', 'status']);
            }, ARRAY_FILTER_USE_KEY));

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'employee.update', 'employee', :entity_id, :before, :after)
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
                SELECT 
                    ep.id,
                    ep.employee_code,
                    ep.name,
                    ep.email,
                    ep.dept_id,
                    d.name as department_name,
                    ep.employment_type,
                    ep.hire_date,
                    ep.leave_date,
                    ep.work_location_tz,
                    ep.status,
                    ep.user_id,
                    ep.created_at,
                    ep.updated_at
                FROM employee_profiles ep
                LEFT JOIN departments d ON ep.dept_id = d.id
                WHERE ep.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $employee = $stmt->fetch();

            $response->success($employee, '従業員を更新しました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '従業員の更新に失敗しました', $e, [], 500);
        }
    }

    /**
     * 従業員削除（論理削除）
     * DELETE /employees/{id}
     */
    public function delete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック
        if (!in_array($role, ['SystemAdmin', 'CompanyAdmin'])) {
            $response->error('FORBIDDEN', '従業員を削除する権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        // 現在の値を取得
        $stmt = $pdo->prepare("
            SELECT * FROM employee_profiles 
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $current = $stmt->fetch();

        if (!$current) {
            $response->error('NOT_FOUND', '従業員が見つかりません', [], 404);
            return;
        }

        try {
            $pdo->beginTransaction();

            // 論理削除
            $stmt = $pdo->prepare("
                UPDATE employee_profiles 
                SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before)
                VALUES (:tenant_id, :actor_user_id, 'employee.delete', 'employee', :entity_id, :before)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $id,
                'before' => json_encode($current),
            ]);

            $pdo->commit();

            $response->success(null, '従業員を削除しました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '従業員の削除に失敗しました', $e, [], 500);
        }
    }

    /**
     * 従業員招待（ユーザーアカウント作成 + 従業員プロファイル作成 + 役割割り当て）
     * POST /employees/invite
     */
    public function invite(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        // 権限チェック
        if (!in_array($role, [Role::SYSTEM_ADMIN, Role::COMPANY_ADMIN, Role::PROFESSIONAL])) {
            $response->error('FORBIDDEN', '従業員を招待する権限がありません', [], 403);
            return;
        }

        $employeeCode = $request->getBody('employee_code');
        $name = $request->getBody('name');
        $email = $request->getBody('email');
        $deptId = $request->getBody('dept_id');
        $employmentType = $request->getBody('employment_type');
        $hireDate = $request->getBody('hire_date');
        $workLocationTz = $request->getBody('work_location_tz') ?? 'Asia/Tokyo';
        $userRole = $request->getBody('role') ?? 'Employee'; // Employee, Manager, CompanyAdmin

        // バリデーション
        if (empty($employeeCode) || empty($name) || empty($email) || empty($employmentType) || empty($hireDate)) {
            $response->error('VALIDATION_ERROR', '必須項目が不足しています', [], 400);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->error('VALIDATION_ERROR', '有効なメールアドレスを入力してください', [], 400);
            return;
        }

        $validRoles = [Role::EMPLOYEE, Role::MANAGER, Role::COMPANY_ADMIN];
        if (!in_array($userRole, $validRoles)) {
            $response->error('VALIDATION_ERROR', '無効な役割です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // メールアドレスの重複チェック
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE email = :email AND deleted_at IS NULL
            ");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $response->error('CONFLICT', 'このメールアドレスは既に登録されています', [], 409);
                return;
            }

            // 従業員コードの重複チェック
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE tenant_id = :tenant_id AND employee_code = :employee_code AND deleted_at IS NULL
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'employee_code' => $employeeCode]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $response->error('CONFLICT', 'この従業員コードは既に使用されています', [], 409);
                return;
            }

            // 一時パスワードを生成（実際の運用ではメール送信などが必要）
            $tempPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            // ユーザー作成
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, name, status)
                VALUES (:email, :password_hash, :name, 'active')
                RETURNING id
            ");
            $stmt->execute([
                'email' => $email,
                'password_hash' => $passwordHash,
                'name' => $name,
            ]);
            $newUser = $stmt->fetch();
            $newUserId = $newUser['id'];

            // 従業員プロファイル作成
            $stmt = $pdo->prepare("
                INSERT INTO employee_profiles (
                    tenant_id, user_id, employee_code, name, email, dept_id, 
                    employment_type, hire_date, work_location_tz, status
                )
                VALUES (
                    :tenant_id, :user_id, :employee_code, :name, :email, :dept_id,
                    :employment_type, :hire_date, :work_location_tz, 'active'
                )
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $newUserId,
                'employee_code' => $employeeCode,
                'name' => $name,
                'email' => $email,
                'dept_id' => $deptId,
                'employment_type' => $employmentType,
                'hire_date' => $hireDate,
                'work_location_tz' => $workLocationTz,
            ]);
            $employee = $stmt->fetch();
            $employeeId = $employee['id'];

            // 役割割り当て
            $stmt = $pdo->prepare("
                INSERT INTO role_assignments (tenant_id, user_id, role, scope_type)
                VALUES (:tenant_id, :user_id, :role, 'all')
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $newUserId,
                'role' => $userRole,
            ]);

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'employee.invite', 'employee', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $employeeId,
                'after' => json_encode([
                    'employee_code' => $employeeCode,
                    'name' => $name,
                    'email' => $email,
                    'role' => $userRole,
                ]),
            ]);

            $pdo->commit();

            // 作成した従業員を取得
            $stmt = $pdo->prepare("
                SELECT 
                    ep.id,
                    ep.employee_code,
                    ep.name,
                    ep.email,
                    ep.dept_id,
                    d.name as department_name,
                    ep.employment_type,
                    ep.hire_date,
                    ep.work_location_tz,
                    ep.status,
                    ep.user_id,
                    ep.created_at
                FROM employee_profiles ep
                LEFT JOIN departments d ON ep.dept_id = d.id
                WHERE ep.id = :id
            ");
            $stmt->execute(['id' => $employeeId]);
            $createdEmployee = $stmt->fetch();

            // 一時パスワードを含めて返す（実際の運用ではメール送信）
            $createdEmployee['temp_password'] = $tempPassword;

            $response->success($createdEmployee, '従業員を招待しました', 201);

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '従業員の招待に失敗しました', $e, [], 500);
        }
    }
}

