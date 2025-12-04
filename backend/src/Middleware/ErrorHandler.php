<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * エラーハンドリングミドルウェア
 */
class ErrorHandler
{
    public static function handle(Request $request, Response $response): void
    {
        set_error_handler(function ($severity, $message, $file, $line) use ($response) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            $response->error('INTERNAL_ERROR', '内部サーバーエラーが発生しました', [
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ], 500);
            return true;
        });

        set_exception_handler(function (\Throwable $e) use ($response) {
            $response->error('INTERNAL_ERROR', '内部サーバーエラーが発生しました', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        });
    }
}

