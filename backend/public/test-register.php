<?php
/**
 * 会員登録APIのテスト用スクリプト
 */

// エラーレポート設定
error_reporting(E_ALL);
ini_set('display_errors', '1');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// オートローダー（Composer）
require_once __DIR__ . '/../vendor/autoload.php';

// 環境変数の読み込み
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// テストデータ
$testData = [
    'tenant_name' => 'テスト株式会社',
    'name' => 'テスト管理者',
    'email' => 'test@example.com',
    'password' => 'Test1234!',
    'timezone' => 'Asia/Tokyo',
    'locale' => 'ja'
];

echo "=== 会員登録APIテスト ===\n\n";
echo "テストデータ:\n";
print_r($testData);
echo "\n";

// cURLでテスト
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/api.php/auth/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTPステータスコード: $httpCode\n";
if ($error) {
    echo "cURLエラー: $error\n";
}
echo "レスポンス:\n";
echo $response . "\n";

$decoded = json_decode($response, true);
if ($decoded) {
    echo "\nデコードされたレスポンス:\n";
    print_r($decoded);
}

