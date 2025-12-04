<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * 仮想時間ミドルウェア
 * 開発環境でのデバッグ用に、クライアントから送信された仮想時間を使用
 */
class VirtualTimeMiddleware
{
    /**
     * 仮想時間を処理
     * X-Virtual-Timeヘッダーから仮想時間を取得し、リクエストに設定
     */
    public static function handle(Request $request, Response $response): void
    {
        // 開発環境のみ有効
        if (($_ENV['APP_ENV'] ?? 'development') !== 'development') {
            return;
        }

        // X-Virtual-Timeヘッダーから仮想時間を取得
        $virtualTimeHeader = $request->getHeader('X-Virtual-Time');
        
        if ($virtualTimeHeader) {
            try {
                $virtualTime = new \DateTime($virtualTimeHeader);
                // リクエストに仮想時間を設定
                $request->setParam('virtual_time', $virtualTime);
            } catch (\Exception $e) {
                // 無効な形式の場合は無視
                error_log('Invalid virtual time header: ' . $virtualTimeHeader);
            }
        }
    }

    /**
     * 現在の時間を取得（仮想時間が設定されている場合はそれを使用）
     */
    public static function getCurrentTime(Request $request): \DateTime
    {
        $virtualTime = $request->getParam('virtual_time');
        if ($virtualTime instanceof \DateTime) {
            return $virtualTime;
        }
        return new \DateTime();
    }
}

