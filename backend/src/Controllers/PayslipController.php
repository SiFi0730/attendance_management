<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Constants\Role;
use PDO;

/**
 * 給与明細管理コントローラー
 */
class PayslipController
{
    /**
     * 給与明細一覧取得
     * GET /payslips
     */
    public function index(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        $employeeId = $request->getQuery('employee_id');
        $period = $request->getQuery('period'); // YYYY-MM形式

        $pdo = Database::getInstance();

        // 従業員フィルタ
        $employeeWhere = ["ep.tenant_id = :tenant_id", "ep.deleted_at IS NULL"];
        $employeeParams = ['tenant_id' => $tenantId];

        if ($employeeId) {
            $employeeWhere[] = "ep.id = :employee_id";
            $employeeParams['employee_id'] = $employeeId;
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
            // 期間を設定（指定がない場合は今月）
            if (!$period) {
                $period = date('Y-m');
            }

            // 期間の開始日と終了日を計算
            $startDate = $period . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            // 打刻データから給与明細を生成
            $payslip = $this->generatePayslip($pdo, $tenantId, $employee['id'], $startDate, $endDate, $period);

            if ($payslip) {
                $results[] = $payslip;
            }
        }

        $response->success($results);
    }

    /**
     * 給与明細詳細取得
     * GET /payslips/{employee_id}/{period}
     */
    public function show(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');
        $employeeId = $request->getParam('employee_id');
        $period = $request->getParam('period'); // YYYY-MM形式

        $pdo = Database::getInstance();

        // 従業員情報を取得
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.employee_code, ep.name, ep.employment_type, d.name as department_name
            FROM employee_profiles ep
            LEFT JOIN departments d ON ep.dept_id = d.id
            WHERE ep.id = :employee_id AND ep.tenant_id = :tenant_id AND ep.deleted_at IS NULL
        ");
        $stmt->execute(['employee_id' => $employeeId, 'tenant_id' => $tenantId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            $response->error('NOT_FOUND', '従業員が見つかりません', [], 404);
            return;
        }

        // Employeeの場合は自分のみ
        if ($role === Role::EMPLOYEE) {
            $stmt = $pdo->prepare("
                SELECT id FROM employee_profiles 
                WHERE user_id = :user_id AND id = :id
            ");
            $stmt->execute(['user_id' => $userId, 'id' => $employeeId]);
            if (!$stmt->fetch()) {
                $response->error('FORBIDDEN', 'この給与明細にアクセスする権限がありません', [], 403);
                return;
            }
        }

        // 期間の開始日と終了日を計算
        $startDate = $period . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        // 給与明細を生成
        $payslip = $this->generatePayslip($pdo, $tenantId, $employeeId, $startDate, $endDate, $period, true);

        if (!$payslip) {
            $response->error('NOT_FOUND', '給与明細が見つかりません', [], 404);
            return;
        }

        $response->success($payslip);
    }

    /**
     * 給与明細を生成
     */
    private function generatePayslip(PDO $pdo, string $tenantId, string $employeeId, string $startDate, string $endDate, string $period, bool $includeDetails = false): ?array
    {
        // 従業員情報を取得
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.employee_code, ep.name, ep.employment_type, d.name as department_name
            FROM employee_profiles ep
            LEFT JOIN departments d ON ep.dept_id = d.id
            WHERE ep.id = :employee_id AND ep.tenant_id = :tenant_id
        ");
        $stmt->execute(['employee_id' => $employeeId, 'tenant_id' => $tenantId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            return null;
        }

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
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $punches = $stmt->fetchAll();

        if (empty($punches)) {
            return null;
        }

        // 勤怠集計
        $attendance = $this->calculateAttendance($punches, $startDate, $endDate);

        // 給与計算（簡易版）
        $baseSalary = 300000; // 基本給（固定値、実際は従業員マスタから取得）
        $hourlyRate = $baseSalary / 160; // 時給（月160時間想定）

        // 残業手当計算
        $overtimeHours = $attendance['overtime_minutes'] / 60;
        $overtimePay = $overtimeHours * $hourlyRate * 1.25; // 25%割増

        // 深夜手当計算
        $nightHours = $attendance['night_work_minutes'] / 60;
        $nightPay = $nightHours * $hourlyRate * 0.25; // 25%割増

        // 支給項目
        $paymentItems = [
            [
                'name' => '基本給',
                'quantity' => '-',
                'unit_price' => '-',
                'amount' => $baseSalary
            ]
        ];

        if ($overtimePay > 0) {
            $paymentItems[] = [
                'name' => '残業手当',
                'quantity' => round($overtimeHours, 1) . '時間',
                'unit_price' => '¥' . number_format($hourlyRate * 1.25),
                'amount' => round($overtimePay)
            ];
        }

        if ($nightPay > 0) {
            $paymentItems[] = [
                'name' => '深夜手当',
                'quantity' => round($nightHours, 1) . '時間',
                'unit_price' => '¥' . number_format($hourlyRate * 0.25),
                'amount' => round($nightPay)
            ];
        }

        // 通勤手当（固定）
        $commutingAllowance = 15000;
        $paymentItems[] = [
            'name' => '通勤手当',
            'quantity' => '-',
            'unit_price' => '-',
            'amount' => $commutingAllowance
        ];

        $paymentTotal = array_sum(array_column($paymentItems, 'amount'));

        // 控除項目（簡易計算）
        $healthInsurance = round($paymentTotal * 0.053); // 健康保険料 5.3%
        $pensionInsurance = round($paymentTotal * 0.081); // 厚生年金 8.1%
        $employmentInsurance = round($paymentTotal * 0.003); // 雇用保険 0.3%
        $incomeTax = round(($paymentTotal - $healthInsurance - $pensionInsurance - $employmentInsurance) * 0.05); // 所得税 5%（簡易）
        $residentTax = 11100; // 住民税（固定）

        $deductionItems = [
            [
                'name' => '健康保険料',
                'quantity' => '-',
                'unit_price' => '-',
                'amount' => $healthInsurance
            ],
            [
                'name' => '厚生年金保険料',
                'quantity' => '-',
                'unit_price' => '-',
                'amount' => $pensionInsurance
            ],
            [
                'name' => '雇用保険料',
                'quantity' => '-',
                'unit_price' => '-',
                'amount' => $employmentInsurance
            ],
            [
                'name' => '所得税',
                'quantity' => '-',
                'unit_price' => '-',
                'amount' => $incomeTax
            ],
            [
                'name' => '住民税',
                'quantity' => '-',
                'unit_price' => '-',
                'amount' => $residentTax
            ]
        ];

        $deductionTotal = array_sum(array_column($deductionItems, 'amount'));
        $netPayment = $paymentTotal - $deductionTotal;

        // 支給日（翌月25日）
        $paymentDate = date('Y年m月d日', strtotime($period . '-01 +1 month +24 days'));

        $payslip = [
            'id' => $employeeId . '_' . $period,
            'employee_id' => $employeeId,
            'employee_code' => $employee['employee_code'],
            'employee_name' => $employee['name'],
            'department_name' => $employee['department_name'],
            'employment_type' => $this->getEmploymentTypeLabel($employee['employment_type']),
            'period' => date('Y年m月', strtotime($period . '-01')) . '分',
            'payment_date' => $paymentDate,
            'status' => '確定',
            'payment_total' => $paymentTotal,
            'deduction_total' => $deductionTotal,
            'net_payment' => $netPayment,
            'base_salary' => $baseSalary,
            'overtime_pay' => round($overtimePay),
            'deduction_total_amount' => $deductionTotal
        ];

        if ($includeDetails) {
            $payslip['payment_items'] = $paymentItems;
            $payslip['deduction_items'] = $deductionItems;
            $payslip['attendance'] = $attendance;
        }

        return $payslip;
    }

    /**
     * 勤怠集計
     */
    private function calculateAttendance(array $punches, string $startDate, string $endDate): array
    {
        $workMinutes = 0;
        $overtimeMinutes = 0;
        $nightWorkMinutes = 0;
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
            $breakMinutes = 0;
            $breakStart = null;

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

                // 残業時間計算（1日8時間超過分）
                if ($dayWorkMinutes > 480) {
                    $overtimeMinutes += $dayWorkMinutes - 480;
                }

                // 深夜労働時間（22:00-05:00）
                $nightStart = strtotime($date . ' 22:00:00');
                $nightEnd = strtotime($date . ' 05:00:00') + 86400;
                if ($inTime < $nightEnd && $outTime > $nightStart) {
                    $nightStartActual = max($inTime, $nightStart);
                    $nightEndActual = min($outTime, $nightEnd);
                    if ($nightEndActual > $nightStartActual) {
                        $nightWorkMinutes += ($nightEndActual - $nightStartActual) / 60;
                    }
                }

                // 遅刻・早退チェック
                $scheduledStart = strtotime($date . ' 09:00:00');
                $scheduledEnd = strtotime($date . ' 18:00:00');
                if ($inTime > $scheduledStart + 900) {
                    $lateCount++;
                }
                if ($outTime < $scheduledEnd - 900) {
                    $earlyLeaveCount++;
                }
            }
        }

        return [
            'work_minutes' => round($workMinutes),
            'work_hours' => round($workMinutes / 60, 1),
            'overtime_minutes' => round($overtimeMinutes),
            'overtime_hours' => round($overtimeMinutes / 60, 1),
            'night_work_minutes' => round($nightWorkMinutes),
            'night_work_hours' => round($nightWorkMinutes / 60, 1),
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
        ];
    }

    /**
     * 雇用形態ラベルを取得
     */
    private function getEmploymentTypeLabel(string $type): string
    {
        $labels = [
            'full_time' => '正社員',
            'part_time' => 'パート',
            'contract' => '契約社員'
        ];
        return $labels[$type] ?? $type;
    }
}

