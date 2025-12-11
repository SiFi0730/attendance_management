<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use App\Core\Constants\Status;
use PDO;

/**
 * レポート管理コントローラー
 */
class ReportController
{
    /**
     * 集計レポート取得
     * GET /reports/summary
     */
    public function summary(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $role = $request->getParam('role');
        $userId = $request->getParam('user_id');

        $periodType = $request->getQuery('period_type', 'monthly'); // daily, weekly, monthly
        $startDate = $request->getQuery('start_date');
        $endDate = $request->getQuery('end_date');
        $deptId = $request->getQuery('dept_id');
        $employeeId = $request->getQuery('employee_id');

        // バリデーション
        if (empty($startDate) || empty($endDate)) {
            $response->error('VALIDATION_ERROR', '開始日と終了日は必須です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // 従業員フィルタ
        $employeeWhere = ["ep.tenant_id = :tenant_id", "ep.deleted_at IS NULL", "ep.status = :status_active"];
        $employeeParams = ['tenant_id' => $tenantId, 'status_active' => Status::EMPLOYEE_ACTIVE];

        if ($employeeId) {
            $employeeWhere[] = "ep.id = :employee_id";
            $employeeParams['employee_id'] = $employeeId;
        } elseif ($deptId) {
            $employeeWhere[] = "ep.dept_id = :dept_id";
            $employeeParams['dept_id'] = $deptId;
        }

        // Employeeの場合は自分のみ
        if ($role === Role::EMPLOYEE) {
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
            // 打刻データを取得
            $stmt = $pdo->prepare("
                SELECT type, occurred_at
                FROM punch_records
                WHERE tenant_id = :tenant_id
                AND employee_id = :employee_id
                AND occurred_at >= :start_date::timestamp
                AND occurred_at < (:end_date::timestamp + INTERVAL '1 day')
                ORDER BY occurred_at
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'employee_id' => $employee['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $punches = $stmt->fetchAll();

            // 集計計算
            $summary = $this->calculateSummary($punches, $startDate, $endDate);

            $results[] = [
                'employee_id' => $employee['id'],
                'employee_code' => $employee['employee_code'],
                'employee_name' => $employee['name'],
                'department_name' => $employee['department_name'],
                'work_minutes' => $summary['work_minutes'],
                'work_hours' => round($summary['work_minutes'] / 60, 1),
                'scheduled_minutes' => $summary['scheduled_minutes'],
                'scheduled_hours' => round($summary['scheduled_minutes'] / 60, 1),
                'overtime_minutes' => $summary['overtime_minutes'],
                'overtime_hours' => round($summary['overtime_minutes'] / 60, 1),
                'night_work_minutes' => $summary['night_work_minutes'],
                'night_work_hours' => round($summary['night_work_minutes'] / 60, 1),
                'holiday_work_minutes' => $summary['holiday_work_minutes'],
                'holiday_work_hours' => round($summary['holiday_work_minutes'] / 60, 1),
                'late_count' => $summary['late_count'],
                'early_leave_count' => $summary['early_leave_count'],
            ];
        }

        $response->success($results);
    }

    /**
     * 集計計算
     */
    private function calculateSummary(array $punches, string $startDate, string $endDate): array
    {
        $workMinutes = 0;
        $scheduledMinutes = 0;
        $overtimeMinutes = 0;
        $nightWorkMinutes = 0;
        $holidayWorkMinutes = 0;
        $lateCount = 0;
        $earlyLeaveCount = 0;

        // 日付ごとにグループ化
        $dailyPunches = [];
        foreach ($punches as $punch) {
            $date = date('Y-m-d', strtotime($punch['occurred_at']));
            if (!isset($dailyPunches[$date])) {
                $dailyPunches[$date] = [];
            }
            $dailyPunches[$date][] = $punch;
        }

        // 各日の集計
        foreach ($dailyPunches as $date => $dayPunches) {
            $inTime = null;
            $outTime = null;
            $breakStart = null;
            $breakEnd = null;
            $breakMinutes = 0;

            // 打刻を時系列でソート
            usort($dayPunches, function($a, $b) {
                return strtotime($a['occurred_at']) - strtotime($b['occurred_at']);
            });

            foreach ($dayPunches as $punch) {
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

            // 実働時間計算
            if ($inTime && $outTime) {
                $dayWorkMinutes = ($outTime - $inTime) / 60 - $breakMinutes;
                $workMinutes += max(0, $dayWorkMinutes);

                // 所定労働時間（8時間 = 480分）
                $scheduledMinutes += 480;

                // 残業時間計算（1日8時間超過分）
                if ($dayWorkMinutes > 480) {
                    $overtimeMinutes += $dayWorkMinutes - 480;
                }

                // 深夜労働時間（22:00-05:00）
                $nightStart = strtotime($date . ' 22:00:00');
                $nightEnd = strtotime($date . ' 05:00:00') + 86400; // 翌日の5時
                if ($inTime < $nightEnd && $outTime > $nightStart) {
                    $nightStartActual = max($inTime, $nightStart);
                    $nightEndActual = min($outTime, $nightEnd);
                    if ($nightEndActual > $nightStartActual) {
                        $nightWorkMinutes += ($nightEndActual - $nightStartActual) / 60;
                    }
                }

                // 遅刻・早退チェック（9:00開始、18:00終了を想定）
                $scheduledStart = strtotime($date . ' 09:00:00');
                $scheduledEnd = strtotime($date . ' 18:00:00');
                if ($inTime > $scheduledStart + 900) { // 15分以上遅刻
                    $lateCount++;
                }
                if ($outTime < $scheduledEnd - 900) { // 15分以上早退
                    $earlyLeaveCount++;
                }
            }
        }

        return [
            'work_minutes' => round($workMinutes),
            'scheduled_minutes' => $scheduledMinutes,
            'overtime_minutes' => round($overtimeMinutes),
            'night_work_minutes' => round($nightWorkMinutes),
            'holiday_work_minutes' => $holidayWorkMinutes,
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
        ];
    }

    /**
     * 勤怠明細エクスポート
     * GET /reports/export/attendance
     */
    public function exportAttendance(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $startDate = $request->getQuery('start_date');
        $endDate = $request->getQuery('end_date');
        $deptId = $request->getQuery('dept_id');
        $employeeId = $request->getQuery('employee_id');

        if (empty($startDate) || empty($endDate)) {
            $response->error('VALIDATION_ERROR', '開始日と終了日は必須です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        // 従業員フィルタ
        $employeeWhere = ["ep.tenant_id = :tenant_id", "ep.deleted_at IS NULL"];
        $employeeParams = ['tenant_id' => $tenantId];

        if ($employeeId) {
            $employeeWhere[] = "ep.id = :employee_id";
            $employeeParams['employee_id'] = $employeeId;
        } elseif ($deptId) {
            $employeeWhere[] = "ep.dept_id = :dept_id";
            $employeeParams['dept_id'] = $deptId;
        }

        $employeeWhereClause = implode(' AND ', $employeeWhere);

        // 従業員一覧を取得
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.employee_code
            FROM employee_profiles ep
            WHERE $employeeWhereClause
            ORDER BY ep.employee_code
        ");
        $stmt->execute($employeeParams);
        $employees = $stmt->fetchAll();

        $results = [];

        foreach ($employees as $employee) {
            // 日付ごとの打刻データを取得
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(occurred_at) as date,
                    MIN(CASE WHEN type = 'in' THEN occurred_at END) as clock_in,
                    MAX(CASE WHEN type = 'out' THEN occurred_at END) as clock_out,
                    SUM(CASE WHEN type = 'break_in' THEN 1 ELSE 0 END) as break_count
                FROM punch_records
                WHERE tenant_id = :tenant_id
                AND employee_id = :employee_id
                AND occurred_at >= :start_date::timestamp
                AND occurred_at < (:end_date::timestamp + INTERVAL '1 day')
                GROUP BY DATE(occurred_at)
                ORDER BY DATE(occurred_at)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'employee_id' => $employee['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            $dailyRecords = $stmt->fetchAll();

            foreach ($dailyRecords as $record) {
                $clockIn = $record['clock_in'] ? date('H:i:s', strtotime($record['clock_in'])) : '';
                $clockOut = $record['clock_out'] ? date('H:i:s', strtotime($record['clock_out'])) : '';
                
                // 休憩時間計算（簡易版）
                $breakMinutes = 0;
                if ($clockIn && $clockOut) {
                    // デフォルト1時間休憩
                    $breakMinutes = 60;
                }

                // 実働時間計算
                $workMinutes = 0;
                if ($clockIn && $clockOut) {
                    $workMinutes = (strtotime($clockOut) - strtotime($clockIn)) / 60 - $breakMinutes;
                }

                // 残業時間計算（8時間超過分）
                $overtimeMinutes = max(0, $workMinutes - 480);

                $results[] = [
                    'employee_code' => $employee['employee_code'],
                    'date' => $record['date'],
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'break_minutes' => $breakMinutes,
                    'work_minutes' => round($workMinutes),
                    'overtime_minutes' => round($overtimeMinutes),
                    'late' => $clockIn && strtotime($clockIn) > strtotime($record['date'] . ' 09:15:00') ? 1 : 0,
                    'early_leave' => $clockOut && strtotime($clockOut) < strtotime($record['date'] . ' 17:45:00') ? 1 : 0,
                ];
            }
        }

        $response->success($results);
    }
}

