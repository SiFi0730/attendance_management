<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * 認証ミドルウェア
 */
class AuthMiddleware
{
    public static function requireAuth(Request $request, Response $response): bool
    {
        // セッション開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // セッションからユーザー情報を取得
        if (!isset($_SESSION['user_id'])) {
            $response->error('UNAUTHORIZED', '認証が必要です', [], 401);
            return false;
        }

        // ユーザー情報をリクエストに設定
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.name
            FROM users u
            WHERE u.id = :user_id AND u.deleted_at IS NULL
        ");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            session_destroy();
            $response->error('UNAUTHORIZED', 'ユーザーが見つかりません', [], 401);
            return false;
        }

        // テナントIDと役割を取得
        $tenantId = $_SESSION['tenant_id'] ?? null;
        $role = $_SESSION['role'] ?? 'Employee';

        $request->setParam('user_id', $user['id']);
        $request->setParam('user_email', $user['email']);
        $request->setParam('user_name', $user['name']);
        $request->setParam('tenant_id', $tenantId);
        $request->setParam('role', $role);

        return true;
    }
}

