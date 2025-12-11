<?php
/**
 * 勤怠管理システム - 開発環境用エントリーポイント
 * 
 * ⚠️ セキュリティ警告:
 *   このファイルは開発環境専用です。
 *   本番環境では使用しないでください。
 *   データベース情報が表示される可能性があります。
 */

// エラーレポート設定（開発環境のみ）
$appEnv = $_ENV['APP_ENV'] ?? 'development';
if ($appEnv === 'production') {
    // 本番環境ではエラーを表示しない
    error_reporting(0);
    ini_set('display_errors', '0');
    die('このページは開発環境専用です。');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// オートローダー（Composer）
require_once __DIR__ . '/../vendor/autoload.php';

// 環境変数の読み込み
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// データベース接続テスト
try {
    $pdo = new PDO(
        "pgsql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']}",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "<!DOCTYPE html>";
    echo "<html lang='ja'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>勤怠管理システム - 開発環境</title>";
    echo "<style>";
    echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }";
    echo ".success { color: green; padding: 10px; background: #f0f0f0; border-radius: 5px; }";
    echo ".info { color: blue; padding: 10px; background: #f0f0f0; border-radius: 5px; margin-top: 20px; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    echo "<h1>勤怠管理システム - 開発環境</h1>";
    echo "<div class='success'>✓ データベース接続成功</div>";
    
    // テーブル一覧を取得
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll();
    
    echo "<div class='info'>";
    echo "<h2>データベーステーブル一覧</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table['table_name']) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // テナント数を取得
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tenants WHERE deleted_at IS NULL");
    $tenantCount = $stmt->fetch();
    
    // ユーザー数を取得
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL");
    $userCount = $stmt->fetch();
    
    echo "<div class='info'>";
    echo "<h2>データベース統計</h2>";
    echo "<ul>";
    echo "<li>テナント数: " . htmlspecialchars($tenantCount['count']) . "</li>";
    echo "<li>ユーザー数: " . htmlspecialchars($userCount['count']) . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h2>開発環境情報</h2>";
    echo "<ul>";
    echo "<li>PHPバージョン: " . phpversion() . "</li>";
    echo "<li>PostgreSQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "</li>";
    echo "<li>データベース: " . htmlspecialchars($_ENV['DB_NAME']) . "</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</body>";
    echo "</html>";
    
} catch (PDOException $e) {
    echo "<!DOCTYPE html>";
    echo "<html lang='ja'>";
    echo "<head><meta charset='UTF-8'><title>エラー</title></head>";
    echo "<body>";
    echo "<h1>データベース接続エラー</h1>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body>";
    echo "</html>";
}

