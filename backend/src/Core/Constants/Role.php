<?php

namespace App\Core\Constants;

/**
 * 役割定数クラス
 */
class Role
{
    const SYSTEM_ADMIN = 'SystemAdmin';
    const COMPANY_ADMIN = 'CompanyAdmin';
    const PROFESSIONAL = 'Professional';
    const MANAGER = 'Manager';
    const EMPLOYEE = 'Employee';

    /**
     * すべての役割を取得
     * 
     * @return array
     */
    public static function all(): array
    {
        return [
            self::SYSTEM_ADMIN,
            self::COMPANY_ADMIN,
            self::PROFESSIONAL,
            self::MANAGER,
            self::EMPLOYEE,
        ];
    }

    /**
     * 役割が有効かチェック
     * 
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    /**
     * 管理者権限を持つ役割かチェック
     * 
     * @param string $role
     * @return bool
     */
    public static function isAdmin(string $role): bool
    {
        return in_array($role, [
            self::SYSTEM_ADMIN,
            self::COMPANY_ADMIN,
        ], true);
    }

    /**
     * 承認権限を持つ役割かチェック
     * 
     * @param string $role
     * @return bool
     */
    public static function canApprove(string $role): bool
    {
        return in_array($role, [
            self::SYSTEM_ADMIN,
            self::COMPANY_ADMIN,
            self::MANAGER,
            self::PROFESSIONAL,
        ], true);
    }
}

