<?php

namespace App\Core;

/**
 * HTTPリクエスト処理クラス
 */
class Request
{
    private array $params = [];
    private array $query = [];
    private array $headers = [];
    private ?array $body = null;
    private string $method;
    private string $path;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // /api.php をパスから除去
        if (strpos($path, '/api.php') === 0) {
            $path = substr($path, strlen('/api.php'));
            if (empty($path)) {
                $path = '/';
            }
        }
        
        $this->path = $path;
        $this->query = $_GET ?? [];
        $this->headers = $this->getAllHeaders();
        $this->parseBody();
    }

    /**
     * すべてのHTTPヘッダーを取得
     */
    private function getAllHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($header)] = $value;
            }
        }
        return $headers;
    }

    /**
     * リクエストボディを解析
     */
    private function parseBody(): void
    {
        $contentType = $this->getHeader('content-type') ?? '';
        
        // Content-Typeからcharsetを除去して比較
        $contentTypeBase = explode(';', $contentType)[0];
        
        if (strpos(strtolower($contentTypeBase), 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $decoded = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                $this->body = $decoded;
            } else {
                $this->body = [];
            }
        } else {
            $this->body = $_POST ?? [];
        }
    }

    /**
     * HTTPメソッドを取得
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * パスを取得
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * クエリパラメータを取得
     */
    public function getQuery(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * リクエストボディを取得
     */
    public function getBody(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /**
     * ヘッダーを取得
     */
    public function getHeader(string $name, $default = null)
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * パスパラメータを設定
     */
    public function setParam(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * パスパラメータを取得
     */
    public function getParam(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }
}

