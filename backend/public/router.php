<?php
/**
 * PHP組み込みサーバー用ルーター
 * 静的ファイルとAPIリクエストを適切に処理
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// APIリクエストの処理
if (strpos($requestPath, '/api.php') === 0 || $requestPath === '/api.php') {
    require __DIR__ . '/api.php';
    exit;
}

// pagesディレクトリへのリクエストをプロジェクトルートのpagesにマッピング
if (strpos($requestPath, '/pages/') === 0) {
    $filePath = __DIR__ . '/../../' . $requestPath;
    if (file_exists($filePath) && is_file($filePath)) {
        // ファイルの拡張子に応じて適切なContent-Typeを設定
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        
        $contentType = $contentTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType . '; charset=UTF-8');
        readfile($filePath);
        exit;
    }
}

// stylesディレクトリへのリクエストをプロジェクトルートのstylesにマッピング
if (strpos($requestPath, '/styles/') === 0) {
    $filePath = __DIR__ . '/../../' . $requestPath;
    if (file_exists($filePath) && is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentType = $ext === 'css' ? 'text/css' : 'application/octet-stream';
        header('Content-Type: ' . $contentType . '; charset=UTF-8');
        readfile($filePath);
        exit;
    }
}

// scriptsディレクトリへのリクエストをプロジェクトルートのscriptsにマッピング
if (strpos($requestPath, '/scripts/') === 0) {
    $filePath = __DIR__ . '/../../' . $requestPath;
    if (file_exists($filePath) && is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentType = $ext === 'js' ? 'application/javascript' : 'application/octet-stream';
        header('Content-Type: ' . $contentType . '; charset=UTF-8');
        readfile($filePath);
        exit;
    }
}

// プロジェクトルートのHTMLファイルへのリクエスト
$rootHtmlFiles = ['/', '/index.html', '/dashboard.html'];
if (in_array($requestPath, $rootHtmlFiles)) {
    $filePath = __DIR__ . '/../../' . ($requestPath === '/' ? 'index.html' : ltrim($requestPath, '/'));
    if (file_exists($filePath) && is_file($filePath)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($filePath);
        exit;
    }
}

// プロジェクトルートのその他のHTMLファイル（拡張子が.htmlで、pages/、styles/、scripts/以外）
if (preg_match('/\.html$/', $requestPath) && 
    strpos($requestPath, '/pages/') !== 0 && 
    strpos($requestPath, '/styles/') !== 0 && 
    strpos($requestPath, '/scripts/') !== 0) {
    $filePath = __DIR__ . '/../../' . ltrim($requestPath, '/');
    if (file_exists($filePath) && is_file($filePath)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($filePath);
        exit;
    }
}

// その他のファイルが存在する場合はそのまま配信
$filePath = __DIR__ . $requestPath;
if (file_exists($filePath) && is_file($filePath)) {
    return false; // PHP組み込みサーバーに処理を委譲
}

// 404エラー
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($requestPath);

