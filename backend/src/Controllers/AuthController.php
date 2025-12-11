<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use PDO;

/**
 * 認証コントローラー
 */
class AuthController
{
    /**
     * ユーザーのテナントIDと役割を取得
     * 
     * @param PDO $pdo データベース接続
     * @param string $userId ユーザーID
     * @return array|null テナントIDと役割を含む配列、見つからない場合はnull
     */
    private function getUserRoleAssignment(PDO $pdo, string $userId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT r.tenant_id, r.role
            FROM role_assignments r
            WHERE r.user_id = :user_id AND r.deleted_at IS NULL
            ORDER BY 
                CASE r.role
                    WHEN :role_system_admin THEN 1
                    WHEN :role_company_admin THEN 2
                    WHEN :role_professional THEN 3
                    WHEN :role_manager THEN 4
                    WHEN :role_employee THEN 5
                END
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'role_system_admin' => Role::SYSTEM_ADMIN,
            'role_company_admin' => Role::COMPANY_ADMIN,
            'role_professional' => Role::PROFESSIONAL,
            'role_manager' => Role::MANAGER,
            'role_employee' => Role::EMPLOYEE,
        ]);
        return $stmt->fetch() ?: null;
    }
    /**
     * ログイン
     * POST /auth/login
     */
    public function login(Request $request, Response $response): void
    {
        $email = $request->getBody('email');
        $password = $request->getBody('password');

        if (empty($email) || empty($password)) {
            $response->error('VALIDATION_ERROR', 'メールアドレスとパスワードは必須です', [], 400);
            return;
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.name, u.password_hash, u.deleted_at
            FROM users u
            WHERE u.email = :email AND u.deleted_at IS NULL
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $response->error('INVALID_CREDENTIALS', 'メールアドレスまたはパスワードが正しくありません', [], 401);
            return;
        }

        // セッション開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];

        // テナントIDと役割を取得（role_assignmentsから）
        $roleAssignment = $this->getUserRoleAssignment($pdo, $user['id']);

        $tenantId = $roleAssignment['tenant_id'] ?? null;
        $role = $roleAssignment['role'] ?? 'Employee';

        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['role'] = $role;

        // 最終ログイン時刻を更新
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_login_at = CURRENT_TIMESTAMP 
            WHERE id = :user_id
        ");
        $stmt->execute(['user_id' => $user['id']]);

        $response->success([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'tenant_id' => $tenantId,
                'role' => $role,
            ],
        ], 'ログインに成功しました');
    }

    /**
     * ログアウト
     * POST /auth/logout
     */
    public function logout(Request $request, Response $response): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();

        $response->success(null, 'ログアウトしました');
    }

    /**
     * 現在のユーザー情報を取得
     * GET /auth/me
     */
    public function me(Request $request, Response $response): void
    {
        $userId = $request->getParam('user_id');
        
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.name
            FROM users u
            WHERE u.id = :user_id AND u.deleted_at IS NULL
        ");
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $response->error('NOT_FOUND', 'ユーザーが見つかりません', [], 404);
            return;
        }

        // テナントIDと役割を取得
        $roleAssignment = $this->getUserRoleAssignment($pdo, $userId);

        $tenantId = $roleAssignment['tenant_id'] ?? null;
        $role = $roleAssignment['role'] ?? 'Employee';

        $response->success([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'tenant_id' => $tenantId,
                'role' => $role,
            ],
        ]);
    }

    /**
     * 会員登録（テナント作成 + ユーザー登録）
     * POST /auth/register
     */
    public function register(Request $request, Response $response): void
    {
        $body = $request->getBody();
        
        // デバッグ用（開発環境のみ）
        if ($_ENV['APP_DEBUG'] ?? false) {
            error_log('Register request body: ' . json_encode($body));
        }
        
        $tenantName = $body['tenant_name'] ?? null;
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;
        $name = $body['name'] ?? null;
        $timezone = $body['timezone'] ?? 'Asia/Tokyo';
        $locale = $body['locale'] ?? 'ja';

        // バリデーション
        if (empty($tenantName) || empty($email) || empty($password) || empty($name)) {
            $response->error('VALIDATION_ERROR', '必須項目が不足しています', [
                'received' => [
                    'tenant_name' => !empty($tenantName),
                    'email' => !empty($email),
                    'password' => !empty($password),
                    'name' => !empty($name),
                ],
                'body' => $body
            ], 400);
            return;
        }

        // パスワードポリシー（最小8文字、大文字・小文字・数字・記号を含む）
        if (strlen($password) < 8) {
            $response->error('VALIDATION_ERROR', 'パスワードは8文字以上である必要があります', [], 400);
            return;
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || 
            !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $response->error('VALIDATION_ERROR', 'パスワードは大文字・小文字・数字・記号を含む必要があります', [], 400);
            return;
        }

        // メールアドレスの形式チェック
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->error('VALIDATION_ERROR', '有効なメールアドレスを入力してください', [], 400);
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

            // テナント作成
            $stmt = $pdo->prepare("
                INSERT INTO tenants (name, timezone, locale, status)
                VALUES (:name, :timezone, :locale, 'active')
                RETURNING id
            ");
            $stmt->execute([
                'name' => $tenantName,
                'timezone' => $timezone,
                'locale' => $locale,
            ]);
            $tenant = $stmt->fetch();
            $tenantId = $tenant['id'];

            // ユーザー作成
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
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
            $user = $stmt->fetch();
            $userId = $user['id'];

            // 役割割り当て（CompanyAdmin）
            $stmt = $pdo->prepare("
                INSERT INTO role_assignments (tenant_id, user_id, role, scope_type)
                VALUES (:tenant_id, :user_id, 'CompanyAdmin', 'all')
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ]);

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'tenant.create', 'tenant', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $tenantId,
                'after' => json_encode(['name' => $tenantName, 'timezone' => $timezone, 'locale' => $locale]),
            ]);

            $pdo->commit();

            // セッション開始
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['user_id'] = $userId;
            $_SESSION['tenant_id'] = $tenantId;
            $_SESSION['role'] = 'CompanyAdmin';

            $response->success([
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'name' => $name,
                    'tenant_id' => $tenantId,
                    'role' => 'CompanyAdmin',
                ],
                'tenant' => [
                    'id' => $tenantId,
                    'name' => $tenantName,
                    'timezone' => $timezone,
                    'locale' => $locale,
                ],
            ], '会員登録が完了しました', 201);

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', '会員登録に失敗しました', $e, [], 500);
        }
    }

    /**
     * パスワードリセット要求
     * POST /auth/password-reset
     */
    public function requestPasswordReset(Request $request, Response $response): void
    {
        $email = $request->getBody('email');

        if (empty($email)) {
            $response->error('VALIDATION_ERROR', 'メールアドレスは必須です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // ユーザーを検索
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE email = :email AND deleted_at IS NULL
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // セキュリティのため、ユーザーが存在しなくても成功レスポンスを返す
        if (!$user) {
            $response->success(null, 'パスワードリセットメールを送信しました（該当するメールアドレスが存在する場合）');
            return;
        }

        // レート制限チェック（1時間に3回まで）
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM password_reset_tokens 
            WHERE user_id = :user_id 
            AND created_at > NOW() - INTERVAL '1 hour'
            AND used_at IS NULL
        ");
        $stmt->execute(['user_id' => $user['id']]);
        $result = $stmt->fetch();

        if ($result['count'] >= 3) {
            $response->error('RATE_LIMIT', 'リセット要求が多すぎます。1時間後に再度お試しください', [], 429);
            return;
        }

        // トークン生成（UUID、32文字以上）
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // 既存の未使用トークンを無効化
        $stmt = $pdo->prepare("
            UPDATE password_reset_tokens 
            SET used_at = CURRENT_TIMESTAMP 
            WHERE user_id = :user_id AND used_at IS NULL
        ");
        $stmt->execute(['user_id' => $user['id']]);

        // トークンを保存
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at)
            VALUES (:user_id, :token, :expires_at)
        ");
        $stmt->execute([
            'user_id' => $user['id'],
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        // TODO: メール送信機能を実装
        // 環境変数で制御（開発環境のみトークンをレスポンスに含める）
        $appEnv = $_ENV['APP_ENV'] ?? 'development';
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if ($appEnv === 'development' && $appDebug) {
        $response->success([
                'token' => $token,
            'expires_at' => $expiresAt,
        ], 'パスワードリセットメールを送信しました');
        } else {
            $response->success(null, 'パスワードリセットメールを送信しました');
        }
    }

    /**
     * パスワードリセット実行
     * POST /auth/password-reset/verify
     */
    public function verifyPasswordReset(Request $request, Response $response): void
    {
        $token = $request->getBody('token');
        $newPassword = $request->getBody('password');

        if (empty($token) || empty($newPassword)) {
            $response->error('VALIDATION_ERROR', 'トークンとパスワードは必須です', [], 400);
            return;
        }

        // パスワードポリシー
        if (strlen($newPassword) < 8) {
            $response->error('VALIDATION_ERROR', 'パスワードは8文字以上である必要があります', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // トークンを検証
        $stmt = $pdo->prepare("
            SELECT prt.user_id, prt.expires_at, prt.used_at
            FROM password_reset_tokens prt
            WHERE prt.token = :token
        ");
        $stmt->execute(['token' => $token]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            $response->error('INVALID_TOKEN', '無効なトークンです', [], 400);
            return;
        }

        if ($tokenData['used_at']) {
            $response->error('INVALID_TOKEN', 'このトークンは既に使用されています', [], 400);
            return;
        }

        if (strtotime($tokenData['expires_at']) < time()) {
            $response->error('INVALID_TOKEN', 'トークンの有効期限が切れています', [], 400);
            return;
        }

        // パスワードを更新
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ");
        $stmt->execute([
            'user_id' => $tokenData['user_id'],
            'password_hash' => $passwordHash,
        ]);

        // トークンを無効化
        $stmt = $pdo->prepare("
            UPDATE password_reset_tokens 
            SET used_at = CURRENT_TIMESTAMP 
            WHERE token = :token
        ");
        $stmt->execute(['token' => $token]);

        // 監査ログ
        $stmt = $pdo->prepare("
            SELECT tenant_id FROM role_assignments 
            WHERE user_id = :user_id AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $tokenData['user_id']]);
        $roleAssignment = $stmt->fetch();

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id)
            VALUES (:tenant_id, :actor_user_id, 'user.password_reset', 'user', :entity_id)
        ");
        $stmt->execute([
            'tenant_id' => $roleAssignment['tenant_id'] ?? null,
            'actor_user_id' => $tokenData['user_id'],
            'entity_id' => $tokenData['user_id'],
        ]);

        $response->success(null, 'パスワードをリセットしました');
    }

    /**
     * ユーザー情報更新
     * PUT /auth/me
     */
    public function updateProfile(Request $request, Response $response): void
    {
        $userId = $request->getParam('user_id');
        $body = $request->getBody();

        $name = $body['name'] ?? null;
        $email = $body['email'] ?? null;

        if (empty($name)) {
            $response->error('VALIDATION_ERROR', '氏名は必須です', [], 400);
            return;
        }

        if (empty($email)) {
            $response->error('VALIDATION_ERROR', 'メールアドレスは必須です', [], 400);
            return;
        }

        // メールアドレスの形式チェック
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->error('VALIDATION_ERROR', '有効なメールアドレスを入力してください', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // 現在のユーザー情報を取得
        $stmt = $pdo->prepare("
            SELECT id, email, name
            FROM users
            WHERE id = :user_id AND deleted_at IS NULL
        ");
        $stmt->execute(['user_id' => $userId]);
        $currentUser = $stmt->fetch();

        if (!$currentUser) {
            $response->error('NOT_FOUND', 'ユーザーが見つかりません', [], 404);
            return;
        }

        // メールアドレスの重複チェック（自分以外）
        if ($email !== $currentUser['email']) {
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE email = :email AND id != :user_id AND deleted_at IS NULL
            ");
            $stmt->execute(['email' => $email, 'user_id' => $userId]);
            if ($stmt->fetch()) {
                $response->error('CONFLICT', 'このメールアドレスは既に使用されています', [], 409);
                return;
            }
        }

        try {
            // ユーザー情報を更新
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = :name, email = :email, updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");
            $stmt->execute([
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
            ]);

            // テナントIDを取得（監査ログ用）
            $stmt = $pdo->prepare("
                SELECT tenant_id FROM role_assignments 
                WHERE user_id = :user_id AND deleted_at IS NULL 
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $roleAssignment = $stmt->fetch();
            $tenantId = $roleAssignment['tenant_id'] ?? null;

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, before, after)
                VALUES (:tenant_id, :actor_user_id, 'user.update', 'user', :entity_id, :before, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $userId,
                'before' => json_encode(['name' => $currentUser['name'], 'email' => $currentUser['email']]),
                'after' => json_encode(['name' => $name, 'email' => $email]),
            ]);

            // 更新後のユーザー情報を取得
            $stmt = $pdo->prepare("
                SELECT u.id, u.email, u.name
                FROM users u
                WHERE u.id = :user_id AND u.deleted_at IS NULL
            ");
            $stmt->execute(['user_id' => $userId]);
            $updatedUser = $stmt->fetch();

            // テナントIDと役割を取得
            $roleAssignment = $this->getUserRoleAssignment($pdo, $userId);

            $tenantId = $roleAssignment['tenant_id'] ?? null;
            $role = $roleAssignment['role'] ?? 'Employee';

            $response->success([
                'user' => [
                    'id' => $updatedUser['id'],
                    'email' => $updatedUser['email'],
                    'name' => $updatedUser['name'],
                    'tenant_id' => $tenantId,
                    'role' => $role,
                ],
            ], 'プロフィールを更新しました');

        } catch (\Exception $e) {
            $response->errorWithException('INTERNAL_ERROR', 'プロフィールの更新に失敗しました', $e, [], 500);
        }
    }

    /**
     * パスワード変更
     * POST /auth/password-change
     */
    public function changePassword(Request $request, Response $response): void
    {
        $userId = $request->getParam('user_id');
        $body = $request->getBody();

        $currentPassword = $body['current_password'] ?? null;
        $newPassword = $body['new_password'] ?? null;
        $confirmPassword = $body['confirm_password'] ?? null;

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $response->error('VALIDATION_ERROR', '現在のパスワード、新しいパスワード、確認パスワードは必須です', [], 400);
            return;
        }

        // 新しいパスワードと確認パスワードの一致チェック
        if ($newPassword !== $confirmPassword) {
            $response->error('VALIDATION_ERROR', '新しいパスワードと確認パスワードが一致しません', [], 400);
            return;
        }

        // パスワードポリシー（最小8文字、大文字・小文字・数字・記号を含む）
        if (strlen($newPassword) < 8) {
            $response->error('VALIDATION_ERROR', 'パスワードは8文字以上である必要があります', [], 400);
            return;
        }

        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || 
            !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $response->error('VALIDATION_ERROR', 'パスワードは大文字・小文字・数字・記号を含む必要があります', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // 現在のパスワードを検証
        $stmt = $pdo->prepare("
            SELECT id, password_hash
            FROM users
            WHERE id = :user_id AND deleted_at IS NULL
        ");
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $response->error('NOT_FOUND', 'ユーザーが見つかりません', [], 404);
            return;
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $response->error('INVALID_CREDENTIALS', '現在のパスワードが正しくありません', [], 401);
            return;
        }

        // 新しいパスワードが現在のパスワードと同じでないかチェック
        if (password_verify($newPassword, $user['password_hash'])) {
            $response->error('VALIDATION_ERROR', '新しいパスワードは現在のパスワードと異なる必要があります', [], 400);
            return;
        }

        try {
            // パスワードを更新
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");
            $stmt->execute([
                'user_id' => $userId,
                'password_hash' => $passwordHash,
            ]);

            // テナントIDを取得（監査ログ用）
            $stmt = $pdo->prepare("
                SELECT tenant_id FROM role_assignments 
                WHERE user_id = :user_id AND deleted_at IS NULL 
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $roleAssignment = $stmt->fetch();
            $tenantId = $roleAssignment['tenant_id'] ?? null;

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id)
                VALUES (:tenant_id, :actor_user_id, 'user.password_change', 'user', :entity_id)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $userId,
            ]);

            $response->success(null, 'パスワードを変更しました');

        } catch (\Exception $e) {
            $response->errorWithException('INTERNAL_ERROR', 'パスワードの変更に失敗しました', $e, [], 500);
        }
    }
}
