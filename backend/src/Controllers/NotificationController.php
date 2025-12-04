<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * 通知管理コントローラー
 */
class NotificationController
{
    /**
     * 通知一覧取得
     * GET /notifications
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 20));
        $offset = ($page - 1) * $limit;

        $unreadOnly = $request->getQuery('unread_only') === 'true';
        $type = $request->getQuery('type');
        $channel = $request->getQuery('channel');

        $pdo = Database::getInstance();

        $where = ["n.tenant_id = :tenant_id", "n.user_id = :user_id"];
        $params = ['tenant_id' => $tenantId, 'user_id' => $userId];

        if ($unreadOnly) {
            $where[] = "n.read_at IS NULL";
        }

        if ($type) {
            $where[] = "n.type = :type";
            $params['type'] = $type;
        }

        if ($channel) {
            $where[] = "n.channel = :channel";
            $params['channel'] = $channel;
        }

        $whereClause = implode(' AND ', $where);

        // 総件数取得
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM notifications n
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // データ取得
        $stmt = $pdo->prepare("
            SELECT 
                n.id,
                n.tenant_id,
                n.user_id,
                n.type,
                n.title,
                n.body,
                n.channel,
                n.read_at,
                n.created_at
            FROM notifications n
            WHERE $whereClause
            ORDER BY n.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        $stmt->execute($params);
        $notifications = $stmt->fetchAll();

        $response->paginated($notifications, $total, $page, $limit);
    }

    /**
     * 通知を既読にする
     * PUT /notifications/{id}/read
     */
    public function markAsRead(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');

        $pdo = Database::getInstance();

        // 通知を取得
        $stmt = $pdo->prepare("
            SELECT * FROM notifications
            WHERE id = :id AND tenant_id = :tenant_id AND user_id = :user_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId, 'user_id' => $userId]);
        $notification = $stmt->fetch();

        if (!$notification) {
            $response->error('NOT_FOUND', '通知が見つかりません', [], 404);
            return;
        }

        // 既読にする
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET read_at = CURRENT_TIMESTAMP
            WHERE id = :id AND read_at IS NULL
        ");
        $stmt->execute(['id' => $id]);

        // 更新した通知を取得
        $stmt = $pdo->prepare("
            SELECT 
                id,
                tenant_id,
                user_id,
                type,
                title,
                body,
                channel,
                read_at,
                created_at
            FROM notifications
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $updatedNotification = $stmt->fetch();

        $response->success($updatedNotification, '通知を既読にしました', 200);
    }

    /**
     * 通知設定更新
     * PUT /notifications/settings
     */
    public function updateSettings(Request $request, Response $response): void
    {
        // 現時点では通知設定テーブルが未実装のため、プレースホルダー
        $response->error('NOT_IMPLEMENTED', '通知設定機能は今後実装予定です', [], 501);
    }
}

