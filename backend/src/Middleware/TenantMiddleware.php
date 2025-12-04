<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * テナント解決ミドルウェア
 */
class TenantMiddleware
{
    public static function resolve(Request $request, Response $response): bool
    {
        // テナントIDを取得（サブドメイン、ヘッダー、セッションから）
        $tenantId = null;

        // 1. ヘッダーから取得
        $tenantId = $request->getHeader('x-tenant-id');
        
        // 2. サブドメインから取得（将来実装）
        // $host = $_SERVER['HTTP_HOST'] ?? '';
        // $subdomain = explode('.', $host)[0];
        
        // 3. セッションから取得（認証後）
        // if (!$tenantId && isset($_SESSION['tenant_id'])) {
        //     $tenantId = $_SESSION['tenant_id'];
        // }

        if ($tenantId) {
            $request->setParam('tenant_id', $tenantId);
        }

        // 認証が必要なエンドポイント以外はテナントIDがなくてもOK
        return true;
    }
}

