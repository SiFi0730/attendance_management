<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * ダッシュボード管理コントローラー
 */
class DashboardController
{
    /**
     * ダッシュボード統計取得
     * GET /dashboard/stats
     */
    public function stats(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $pdo = Database::getInstance();

        // 今日の日付を取得
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // 今月の開始日と終了日
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        // 従業員フィルタ
        $employeeWhere = ["ep.tenant_id = :tenant_id", "ep.deleted_at IS NULL", "ep.status = 'active'"];
        $employeeParams = ['tenant_id' => $tenantId];

        // Employeeの場合は自分のみ
        if ($role === 'Employee') {
            $employeeWhere[] = "ep.user_id = :user_id";
            $employeeParams['user_id'] = $userId;
        }

        $employeeWhereClause = implode(' AND ', $employeeWhere);

        // 1. 今日の出勤者数
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pr.employee_id) as count
            FROM punch_records pr
            JOIN employee_profiles ep ON pr.employee_id = ep.id
            WHERE pr.tenant_id = :tenant_id
            AND pr.type = 'in'
            AND pr.occurred_at >= :start_date::timestamp
            AND pr.occurred_at <= :end_date::timestamp
            AND $employeeWhereClause
        ");
        $params = array_merge($employeeParams, [
            'start_date' => $todayStart,
            'end_date' => $todayEnd
        ]);
        $stmt->execute($params);
        $todayAttendance = $stmt->fetch()['count'];

        // 前日の出勤者数（比較用）
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterdayStart = $yesterday . ' 00:00:00';
        $yesterdayEnd = $yesterday . ' 23:59:59';
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pr.employee_id) as count
            FROM punch_records pr
            JOIN employee_profiles ep ON pr.employee_id = ep.id
            WHERE pr.tenant_id = :tenant_id
            AND pr.type = 'in'
            AND pr.occurred_at >= :start_date::timestamp
            AND pr.occurred_at <= :end_date::timestamp
            AND $employeeWhereClause
        ");
        $params = array_merge($employeeParams, [
            'start_date' => $yesterdayStart,
            'end_date' => $yesterdayEnd
        ]);
        $stmt->execute($params);
        $yesterdayAttendance = $stmt->fetch()['count'];
        $attendanceDiff = $todayAttendance - $yesterdayAttendance;

        // 2. 未打刻者数（出勤打刻がない従業員）
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM employee_profiles ep
            WHERE $employeeWhereClause
            AND ep.id NOT IN (
                SELECT DISTINCT pr.employee_id
                FROM punch_records pr
                WHERE pr.tenant_id = :tenant_id
                AND pr.type = 'in'
                AND pr.occurred_at >= :start_date::timestamp
                AND pr.occurred_at <= :end_date::timestamp
            )
        ");
        $params = array_merge($employeeParams, [
            'tenant_id' => $tenantId,
            'start_date' => $todayStart,
            'end_date' => $todayEnd
        ]);
        $stmt->execute($params);
        $noPunchCount = $stmt->fetch()['count'];

        // 前日の未打刻者数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM employee_profiles ep
            WHERE $employeeWhereClause
            AND ep.id NOT IN (
                SELECT DISTINCT pr.employee_id
                FROM punch_records pr
                WHERE pr.tenant_id = :tenant_id
                AND pr.type = 'in'
                AND pr.occurred_at >= :start_date::timestamp
                AND pr.occurred_at <= :end_date::timestamp
            )
        ");
        $params = array_merge($employeeParams, [
            'tenant_id' => $tenantId,
            'start_date' => $yesterdayStart,
            'end_date' => $yesterdayEnd
        ]);
        $stmt->execute($params);
        $yesterdayNoPunchCount = $stmt->fetch()['count'];
        $noPunchDiff = $noPunchCount - $yesterdayNoPunchCount;

        // 3. 承認待ち申請数
        $requestWhere = ["r.tenant_id = :tenant_id", "r.status = 'pending'"];
        $requestParams = ['tenant_id' => $tenantId];

        // Managerの場合は配下の従業員のみ
        if ($role === 'Manager') {
            // TODO: 部署階層を考慮
        } elseif ($role === 'Employee') {
            // Employeeの場合は自分の申請のみ
            $stmt = $pdo->prepare("
                SELECT ep.id FROM employee_profiles ep
                WHERE ep.user_id = :user_id AND ep.tenant_id = :tenant_id
            ");
            $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
            $employee = $stmt->fetch();
            if ($employee) {
                $requestWhere[] = "r.employee_id = :employee_id";
                $requestParams['employee_id'] = $employee['id'];
            } else {
                $requestWhere[] = "1=0"; // 該当なし
            }
        }

        $requestWhereClause = implode(' AND ', $requestWhere);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM requests r
            WHERE $requestWhereClause
        ");
        $stmt->execute($requestParams);
        $pendingRequests = $stmt->fetch()['count'];

        // 4. 今月の平均労働時間
        $stmt = $pdo->prepare("
            SELECT 
                ep.id,
                pr.type,
                pr.occurred_at
            FROM employee_profiles ep
            LEFT JOIN punch_records pr ON ep.id = pr.employee_id
                AND pr.tenant_id = :tenant_id
                AND pr.occurred_at >= :start_date::timestamp
                AND pr.occurred_at < (:end_date::timestamp + INTERVAL '1 day')
            WHERE $employeeWhereClause
            ORDER BY ep.id, pr.occurred_at
        ");
        $params = array_merge($employeeParams, [
            'tenant_id' => $tenantId,
            'start_date' => $monthStart,
            'end_date' => $monthEnd
        ]);
        $stmt->execute($params);
        $allPunches = $stmt->fetchAll();

        // 従業員ごとに集計
        $employeeWorkMinutes = [];
        foreach ($allPunches as $punch) {
            $empId = $punch['id'];
            if (!isset($employeeWorkMinutes[$empId])) {
                $employeeWorkMinutes[$empId] = [];
            }
            if ($punch['type'] && $punch['occurred_at']) {
                $employeeWorkMinutes[$empId][] = [
                    'type' => $punch['type'],
                    'occurred_at' => $punch['occurred_at']
                ];
            }
        }

        $totalWorkMinutes = 0;
        $employeeCount = 0;
        foreach ($employeeWorkMinutes as $empId => $punches) {
            if (empty($punches)) continue;
            $workMinutes = $this->calculateWorkMinutes($punches);
            $totalWorkMinutes += $workMinutes;
            $employeeCount++;
        }

        $avgWorkHours = $employeeCount > 0 ? round($totalWorkMinutes / $employeeCount / 60, 1) : 0;

        $response->success([
            'today_attendance' => (int)$todayAttendance,
            'attendance_diff' => (int)$attendanceDiff,
            'no_punch_count' => (int)$noPunchCount,
            'no_punch_diff' => (int)$noPunchDiff,
            'pending_requests' => (int)$pendingRequests,
            'avg_work_hours' => $avgWorkHours
        ]);
    }

    /**
     * 今日の打刻状況取得
     * GET /dashboard/today-punches
     */
    public function todayPunches(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $pdo = Database::getInstance();

        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // 従業員フィルタ
        $employeeWhere = ["ep.tenant_id = :tenant_id", "ep.deleted_at IS NULL", "ep.status = 'active'"];
        $employeeParams = ['tenant_id' => $tenantId];

        // Employeeの場合は自分のみ
        if ($role === 'Employee') {
            $employeeWhere[] = "ep.user_id = :user_id";
            $employeeParams['user_id'] = $userId;
        }

        $employeeWhereClause = implode(' AND ', $employeeWhere);

        // 従業員一覧を取得
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.employee_code, ep.name, d.name as department_name
            FROM employee_profiles ep
            LEFT JOIN departments d ON ep.dept_id = d.id
            WHERE $employeeWhereClause
            ORDER BY ep.employee_code
        ");
        $stmt->execute($employeeParams);
        $employees = $stmt->fetchAll();

        $results = [];

        foreach ($employees as $employee) {
            // 今日の打刻データを取得
            $stmt = $pdo->prepare("
                SELECT type, occurred_at
                FROM punch_records
                WHERE tenant_id = :tenant_id
                AND employee_id = :employee_id
                AND occurred_at >= :start_date::timestamp
                AND occurred_at <= :end_date::timestamp
                ORDER BY occurred_at
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'employee_id' => $employee['id'],
                'start_date' => $todayStart,
                'end_date' => $todayEnd
            ]);
            $punches = $stmt->fetchAll();

            $clockIn = null;
            $clockOut = null;
            $workMinutes = 0;
            $status = '未打刻';

            if (!empty($punches)) {
                // 出勤・退勤時刻を取得
                foreach ($punches as $punch) {
                    if ($punch['type'] === 'in' && !$clockIn) {
                        $clockIn = $punch['occurred_at'];
                    }
                    if ($punch['type'] === 'out') {
                        $clockOut = $punch['occurred_at'];
                    }
                }

                // 実働時間計算
                if ($clockIn && $clockOut) {
                    $workMinutes = $this->calculateWorkMinutes($punches);
                    $status = '正常';
                } elseif ($clockIn) {
                    $status = '退勤未打刻';
                }
            }

            $results[] = [
                'employee_id' => $employee['id'],
                'employee_code' => $employee['employee_code'],
                'employee_name' => $employee['name'],
                'department_name' => $employee['department_name'],
                'clock_in' => $clockIn ? date('H:i', strtotime($clockIn)) : null,
                'clock_out' => $clockOut ? date('H:i', strtotime($clockOut)) : null,
                'work_hours' => $workMinutes > 0 ? round($workMinutes / 60, 1) : null,
                'status' => $status
            ];
        }

        $response->success($results);
    }

    /**
     * 承認待ち申請取得
     * GET /dashboard/pending-requests
     */
    public function pendingRequests(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $limit = (int)($request->getQuery('limit', 10));

        $pdo = Database::getInstance();

        $where = ["r.tenant_id = :tenant_id", "r.status = 'pending'"];
        $params = ['tenant_id' => $tenantId];

        // Managerの場合は配下の従業員のみ
        if ($role === 'Manager') {
            // TODO: 部署階層を考慮
        } elseif ($role === 'Employee') {
            // Employeeの場合は自分の申請のみ
            $stmt = $pdo->prepare("
                SELECT ep.id FROM employee_profiles ep
                WHERE ep.user_id = :user_id AND ep.tenant_id = :tenant_id
            ");
            $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
            $employee = $stmt->fetch();
            if ($employee) {
                $where[] = "r.employee_id = :employee_id";
                $params['employee_id'] = $employee['id'];
            } else {
                $where[] = "1=0"; // 該当なし
            }
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.tenant_id,
                r.employee_id,
                ep.employee_code,
                ep.name as employee_name,
                r.type,
                r.target_date,
                r.payload,
                r.status,
                r.reason,
                r.created_at
            FROM requests r
            JOIN employee_profiles ep ON r.employee_id = ep.id
            WHERE $whereClause
            ORDER BY r.created_at DESC
            LIMIT :limit
        ");
        $params['limit'] = $limit;
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        // 申請種類のラベル
        $typeLabels = [
            'edit' => '修正申請',
            'overtime' => '残業申請',
            'leave' => '休暇申請',
            'others' => 'その他'
        ];

        $results = [];
        foreach ($requests as $request) {
            $payload = $request['payload'] ? json_decode($request['payload'], true) : [];
            $content = $this->formatRequestContent($request['type'], $payload, $request['reason']);

            $results[] = [
                'id' => $request['id'],
                'employee_code' => $request['employee_code'],
                'employee_name' => $request['employee_name'],
                'type' => $request['type'],
                'type_label' => $typeLabels[$request['type']] ?? $request['type'],
                'target_date' => $request['target_date'],
                'content' => $content,
                'created_at' => $request['created_at']
            ];
        }

        $response->success($results);
    }

    /**
     * 実働時間を計算
     */
    private function calculateWorkMinutes(array $punches): int
    {
        $workMinutes = 0;
        $inTime = null;
        $outTime = null;
        $breakStart = null;
        $breakMinutes = 0;

        foreach ($punches as $punch) {
            $time = strtotime($punch['occurred_at']);
            switch ($punch['type']) {
                case 'in':
                    $inTime = $time;
                    break;
                case 'out':
                    $outTime = $time;
                    break;
                case 'break_in':
                    $breakStart = $time;
                    break;
                case 'break_out':
                    if ($breakStart) {
                        $breakMinutes += ($time - $breakStart) / 60;
                        $breakStart = null;
                    }
                    break;
            }
        }

        if ($inTime && $outTime) {
            $workMinutes = ($outTime - $inTime) / 60 - $breakMinutes;
        }

        return max(0, round($workMinutes));
    }

    /**
     * 申請内容をフォーマット
     */
    private function formatRequestContent(string $type, array $payload, ?string $reason): string
    {
        switch ($type) {
            case 'edit':
                if (isset($payload['clock_in']) || isset($payload['clock_out'])) {
                    $parts = [];
                    if (isset($payload['clock_in'])) {
                        $parts[] = '出勤時刻: ' . $payload['clock_in'];
                    }
                    if (isset($payload['clock_out'])) {
                        $parts[] = '退勤時刻: ' . $payload['clock_out'];
                    }
                    return implode(', ', $parts);
                }
                return $reason ?: '時刻修正';
            case 'overtime':
                return $reason ?: '残業申請';
            case 'leave':
                return $reason ?: '休暇申請';
            default:
                return $reason ?: '申請';
        }
    }
}

