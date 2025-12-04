<?php

namespace App\Core;

/**
 * ルーティングクラス
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];

    /**
     * ルートを登録
     */
    public function add(string $method, string $path, callable|string|array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * GETルートを登録
     */
    public function get(string $path, callable|string|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    /**
     * POSTルートを登録
     */
    public function post(string $path, callable|string|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    /**
     * PUTルートを登録
     */
    public function put(string $path, callable|string|array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    /**
     * DELETEルートを登録
     */
    public function delete(string $path, callable|string|array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    /**
     * グローバルミドルウェアを登録
     */
    public function use(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * リクエストを処理
     */
    public function dispatch(Request $request, Response $response): void
    {
        // グローバルミドルウェアを実行
        foreach ($this->middlewares as $middleware) {
            $result = $middleware($request, $response);
            if ($result === false) {
                return;
            }
        }

        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertPathToRegex($route['path']);
            if (preg_match($pattern, $path, $matches)) {
                // パスパラメータを設定
                array_shift($matches);
                $paramNames = $this->extractParamNames($route['path']);
                foreach ($paramNames as $index => $name) {
                    if (isset($matches[$index])) {
                        $request->setParam($name, $matches[$index]);
                    }
                }

                // ルート固有のミドルウェアを実行
                foreach ($route['middlewares'] as $middleware) {
                    $result = $middleware($request, $response);
                    if ($result === false) {
                        return;
                    }
                }

                // ハンドラーを実行
                $handler = $route['handler'];
                if (is_array($handler) && count($handler) === 2) {
                    // [ClassName::class, 'methodName'] 形式
                    [$className, $methodName] = $handler;
                    $class = new $className();
                    $class->$methodName($request, $response);
                } elseif (is_string($handler) && strpos($handler, '@') !== false) {
                    // 'ClassName@methodName' 形式
                    [$className, $methodName] = explode('@', $handler);
                    $class = new $className();
                    $class->$methodName($request, $response);
                } else {
                    // クロージャー
                    $handler($request, $response);
                }
                return;
            }
        }

        // 404 Not Found
        $response->error('NOT_FOUND', 'リソースが見つかりません', [], 404);
    }

    /**
     * パスを正規表現に変換
     */
    private function convertPathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * パスからパラメータ名を抽出
     */
    private function extractParamNames(string $path): array
    {
        preg_match_all('/\{(\w+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }
}

