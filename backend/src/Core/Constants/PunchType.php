<?php

namespace App\Core\Constants;

/**
 * 打刻種類定数クラス
 */
class PunchType
{
    const IN = 'in';
    const OUT = 'out';
    const BREAK_IN = 'break_in';
    const BREAK_OUT = 'break_out';

    /**
     * すべての打刻種類を取得
     * 
     * @return array
     */
    public static function all(): array
    {
        return [
            self::IN,
            self::OUT,
            self::BREAK_IN,
            self::BREAK_OUT,
        ];
    }

    /**
     * 打刻種類が有効かチェック
     * 
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}

