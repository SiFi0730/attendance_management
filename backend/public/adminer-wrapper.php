<?php
/**
 * Adminerラッパー - 環境変数から接続情報を自動読み込み
 * 
 * ⚠️ セキュリティ警告:
 *   このファイルは開発環境専用です。
 *   本番環境では削除するか、アクセス制限を設定してください。
 */

// 環境変数の読み込み
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 接続情報を取得
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '5432';
$dbName = $_ENV['DB_NAME'] ?? 'attendance_management';
$dbUser = $_ENV['DB_USER'] ?? 'attendance_user';
$dbPassword = $_ENV['DB_PASSWORD'] ?? 'attendance_password';

// Adminerに接続情報を渡す
$_GET['pgsql'] = '';
$_GET['server'] = $dbHost . ':' . $dbPort;
$_GET['username'] = $dbUser;
$_GET['db'] = $dbName;

// パスワードをセッションに保存（初回接続時）
if (!isset($_SESSION)) {
    session_start();
}
$_SESSION['pwds'][$dbHost . ':' . $dbPort][$dbUser] = $dbPassword;

// Adminerを読み込む
require_once __DIR__ . '/adminer.php';

