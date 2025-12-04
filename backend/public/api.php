<?php
/**
 * 勤怠管理システム - APIエントリーポイント
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

// CORS設定（開発環境）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-ID, X-Request-ID');

// OPTIONSリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\ErrorHandler;
use App\Middleware\TenantMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\VirtualTimeMiddleware;
use App\Controllers\AuthController;
use App\Controllers\TenantController;
use App\Controllers\EmployeeController;
use App\Controllers\DepartmentController;
use App\Controllers\PunchController;
use App\Controllers\RequestController;
use App\Controllers\BusinessHoursController;
use App\Controllers\RuleSetController;
use App\Controllers\HolidayCalendarController;
use App\Controllers\ReportController;
use App\Controllers\PayslipController;
use App\Controllers\AuditLogController;
use App\Controllers\DashboardController;
use App\Controllers\DevController;

// リクエストとレスポンスのインスタンス作成
$request = new Request();
$response = new Response();

// エラーハンドラーを設定
ErrorHandler::handle($request, $response);

// ルーターの設定
$router = new Router();

// グローバルミドルウェア
$router->use(function (Request $req, Response $res) {
    // 仮想時間処理（開発環境のみ）
    VirtualTimeMiddleware::handle($req, $res);
    // テナント解決
    TenantMiddleware::resolve($req, $res);
    return true;
});

// 認証エンドポイント（認証不要）
$router->post('/auth/register', [AuthController::class, 'register']);
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/logout', [AuthController::class, 'logout']);
$router->post('/auth/password-reset', [AuthController::class, 'requestPasswordReset']);
$router->post('/auth/password-reset/verify', [AuthController::class, 'verifyPasswordReset']);

// 認証が必要なエンドポイント
$router->get('/auth/me', [AuthController::class, 'me'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->put('/auth/me', [AuthController::class, 'updateProfile'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/auth/password-change', [AuthController::class, 'changePassword'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// テナント管理
$router->get('/tenants', [TenantController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/tenants/{id}', [TenantController::class, 'show'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->put('/tenants/{id}', [TenantController::class, 'update'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 営業時間
$router->get('/business-hours', [BusinessHoursController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->put('/business-hours', [BusinessHoursController::class, 'update'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 就業ルール
$router->get('/rule-sets/current', [RuleSetController::class, 'current'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->put('/rule-sets/current', [RuleSetController::class, 'updateCurrent'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 祝日カレンダー
$router->get('/holidays', [HolidayCalendarController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/holidays', [HolidayCalendarController::class, 'create'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->put('/holidays/{id}', [HolidayCalendarController::class, 'update'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->delete('/holidays/{id}', [HolidayCalendarController::class, 'delete'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// レポート
$router->get('/reports/summary', [ReportController::class, 'summary'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/reports/export/attendance', [ReportController::class, 'exportAttendance'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 給与明細
$router->get('/payslips', [PayslipController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/payslips/{employee_id}/{period}', [PayslipController::class, 'show'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 監査ログ
$router->get('/audit-logs', [AuditLogController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/audit-logs/{id}', [AuditLogController::class, 'show'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// ダッシュボード
$router->get('/dashboard/stats', [DashboardController::class, 'stats'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/dashboard/today-punches', [DashboardController::class, 'todayPunches'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/dashboard/pending-requests', [DashboardController::class, 'pendingRequests'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 従業員管理
$router->get('/employees', [EmployeeController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/employees/{id}', [EmployeeController::class, 'show'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/employees', [EmployeeController::class, 'create'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->put('/employees/{id}', [EmployeeController::class, 'update'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->delete('/employees/{id}', [EmployeeController::class, 'delete'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/employees/invite', [EmployeeController::class, 'invite'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 部署管理
$router->get('/departments', [DepartmentController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/departments/{id}', [DepartmentController::class, 'show'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/departments', [DepartmentController::class, 'create'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->put('/departments/{id}', [DepartmentController::class, 'update'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->delete('/departments/{id}', [DepartmentController::class, 'delete'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 打刻管理
$router->get('/punches', [PunchController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/punches', [PunchController::class, 'create'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/punches/proxy', [PunchController::class, 'proxy'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 申請管理
$router->get('/requests', [RequestController::class, 'index'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->get('/requests/{id}', [RequestController::class, 'show'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/requests', [RequestController::class, 'create'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/requests/{id}/approve', [RequestController::class, 'approve'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);
$router->post('/requests/{id}/reject', [RequestController::class, 'reject'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// 開発用エンドポイント（開発環境のみ）
$router->post('/dev/reset-database', [DevController::class, 'resetDatabase'], [
    function (Request $req, Response $res) {
        return AuthMiddleware::requireAuth($req, $res);
    }
]);

// ルーティング実行
$router->dispatch($request, $response);

