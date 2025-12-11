<?php

namespace App\Core;

/**
 * HTTPレスポンス処理クラス
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private $data = null;

    /**
     * ステータスコードを設定
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * ヘッダーを設定
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * JSONレスポンスを送信
     */
    public function json($data, int $statusCode = 200): void
    {
        $this->statusCode = $statusCode;
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->sendHeaders();
        http_response_code($this->statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 成功レスポンスを送信
     */
    public function success($data = null, string $message = null, int $statusCode = 200): void
    {
        $response = ['success' => true];
        if ($message !== null) {
            $response['message'] = $message;
        }
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->json($response, $statusCode);
    }

    /**
     * エラーレスポンスを送信
     */
    public function error(string $code, string $message, array $details = [], int $statusCode = 400): void
    {
        $response = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ]
        ];
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        $this->json($response, $statusCode);
    }

    /**
     * 例外を含むエラーレスポンスを送信（本番環境では詳細を隠蔽）
     */
    public function errorWithException(string $code, string $baseMessage, \Exception $exception, array $details = [], int $statusCode = 500): void
    {
        $appEnv = $_ENV['APP_ENV'] ?? 'development';
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // 本番環境ではエラー詳細を隠蔽
        if ($appEnv === 'production') {
            // ログには記録
            error_log($baseMessage . ': ' . $exception->getMessage());
            error_log('Stack trace: ' . $exception->getTraceAsString());
            // ユーザーには詳細を返さない
            $this->error($code, $baseMessage, $details, $statusCode);
        } else {
            // 開発環境では詳細を返す
            $message = $baseMessage;
            if ($appDebug) {
                $message .= ': ' . $exception->getMessage();
            }
            $this->error($code, $message, $details, $statusCode);
        }
    }

    /**
     * ページング付きレスポンスを送信
     */
    public function paginated(array $data, int $total, int $page, int $limit): void
    {
        $this->setHeader('X-Total-Count', (string)$total);
        $this->success([
            'items' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit),
            ]
        ]);
    }

    /**
     * ヘッダーを送信
     */
    private function sendHeaders(): void
    {
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }
}

