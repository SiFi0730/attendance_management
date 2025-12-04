<?php
/**
 * Adminer簡易ログインページ
 * 環境変数から接続情報を自動入力
 */

// 環境変数の読み込み
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 接続情報を取得
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '5432';
$dbName = $_ENV['DB_NAME'] ?? 'attendance_management';
$dbUser = $_ENV['DB_USER'] ?? 'attendance_user';
$dbPassword = $_ENV['DB_PASSWORD'] ?? 'attendance_password';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース管理 - Adminer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .login-box {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info-box strong {
            color: #0066cc;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0052a3;
        }
        .connection-info {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
            font-size: 13px;
        }
        .connection-info dt {
            font-weight: bold;
            margin-top: 8px;
            color: #555;
        }
        .connection-info dd {
            margin-left: 20px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🗄️ データベース管理</h1>
        <p class="subtitle">PostgreSQL管理ツール（Adminer）</p>
        
        <div class="info-box">
            <strong>接続情報が自動設定されています</strong><br>
            以下のボタンをクリックしてデータベース管理画面を開きます。
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="adminer.php?pgsql=&server=<?php echo htmlspecialchars($dbHost . ':' . $dbPort); ?>&username=<?php echo htmlspecialchars($dbUser); ?>&db=<?php echo htmlspecialchars($dbName); ?>" class="btn">
                Adminerを開く
            </a>
        </div>
        
        <div class="connection-info">
            <dl>
                <dt>データベースシステム:</dt>
                <dd>PostgreSQL</dd>
                
                <dt>サーバー:</dt>
                <dd><?php echo htmlspecialchars($dbHost . ':' . $dbPort); ?></dd>
                
                <dt>データベース名:</dt>
                <dd><?php echo htmlspecialchars($dbName); ?></dd>
                
                <dt>ユーザー名:</dt>
                <dd><?php echo htmlspecialchars($dbUser); ?></dd>
            </dl>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999;">
            <p><strong>注意:</strong> パスワードは初回接続時に手動入力が必要な場合があります。</p>
            <p>パスワード: <code><?php echo htmlspecialchars($dbPassword); ?></code></p>
        </div>
    </div>
</body>
</html>

