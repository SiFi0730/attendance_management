<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * データベース接続管理クラス
 * 
 * 注意: シングルトンパターンを使用しています。
 * 将来的にDIコンテナを導入する場合は、このクラスをインターフェース化し、
 * コンストラクタインジェクションに移行することを推奨します。
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * データベース接続を取得（シングルトン）
     * 
     * @return PDO データベース接続インスタンス
     * @throws \RuntimeException データベース接続に失敗した場合
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * 新しいデータベース接続を作成
     * 
     * このメソッドを分離することで、テスト時にモックに差し替えやすくなります。
     * 
     * @return PDO データベース接続インスタンス
     * @throws \RuntimeException データベース接続に失敗した場合
     */
    protected static function createConnection(): PDO
    {
        try {
            return new PDO(
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
            throw new \RuntimeException("データベース接続に失敗しました: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 接続を閉じる（テスト用）
     */
    public static function close(): void
    {
        self::$instance = null;
    }

    /**
     * テスト用: インスタンスを設定（モック用）
     * 
     * 注意: このメソッドはテスト専用です。本番コードでは使用しないでください。
     * 
     * @param PDO|null $instance 設定するインスタンス（nullの場合はリセット）
     */
    public static function setInstance(?PDO $instance): void
    {
        self::$instance = $instance;
    }
}

