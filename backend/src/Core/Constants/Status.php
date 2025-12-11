<?php

namespace App\Core\Constants;

/**
 * ステータス定数クラス
 */
class Status
{
    // ユーザーステータス
    const USER_ACTIVE = 'active';
    const USER_INACTIVE = 'inactive';
    const USER_SUSPENDED = 'suspended';

    // テナントステータス
    const TENANT_ACTIVE = 'active';
    const TENANT_SUSPENDED = 'suspended';
    const TENANT_DELETED = 'deleted';

    // 従業員ステータス
    const EMPLOYEE_ACTIVE = 'active';
    const EMPLOYEE_ON_LEAVE = 'on_leave';
    const EMPLOYEE_RETIRED = 'retired';

    // 申請ステータス
    const REQUEST_PENDING = 'pending';
    const REQUEST_APPROVED = 'approved';
    const REQUEST_REJECTED = 'rejected';

    // タイムシートステータス
    const TIMESHEET_DRAFT = 'draft';
    const TIMESHEET_SUBMITTED = 'submitted';
    const TIMESHEET_APPROVED = 'approved';
    const TIMESHEET_REJECTED = 'rejected';

    /**
     * ユーザーステータス一覧を取得
     * 
     * @return array
     */
    public static function userStatuses(): array
    {
        return [
            self::USER_ACTIVE,
            self::USER_INACTIVE,
            self::USER_SUSPENDED,
        ];
    }

    /**
     * テナントステータス一覧を取得
     * 
     * @return array
     */
    public static function tenantStatuses(): array
    {
        return [
            self::TENANT_ACTIVE,
            self::TENANT_SUSPENDED,
            self::TENANT_DELETED,
        ];
    }

    /**
     * 従業員ステータス一覧を取得
     * 
     * @return array
     */
    public static function employeeStatuses(): array
    {
        return [
            self::EMPLOYEE_ACTIVE,
            self::EMPLOYEE_ON_LEAVE,
            self::EMPLOYEE_RETIRED,
        ];
    }

    /**
     * 申請ステータス一覧を取得
     * 
     * @return array
     */
    public static function requestStatuses(): array
    {
        return [
            self::REQUEST_PENDING,
            self::REQUEST_APPROVED,
            self::REQUEST_REJECTED,
        ];
    }

    /**
     * タイムシートステータス一覧を取得
     * 
     * @return array
     */
    public static function timesheetStatuses(): array
    {
        return [
            self::TIMESHEET_DRAFT,
            self::TIMESHEET_SUBMITTED,
            self::TIMESHEET_APPROVED,
            self::TIMESHEET_REJECTED,
        ];
    }
}

