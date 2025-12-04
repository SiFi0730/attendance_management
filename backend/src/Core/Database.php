<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * データベース接続管理クラス
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * データベース接続を取得（シングルトン）
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    "pgsql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']}",
                    $_ENV['DB_USER'],
                    $_ENV['DB_PASSWORD'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                throw new \RuntimeException("データベース接続に失敗しました: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * 接続を閉じる（テスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}

