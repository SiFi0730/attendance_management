<?php

namespace App\Core;

use App\Core\Request;
use App\Middleware\VirtualTimeMiddleware;

/**
 * 時間取得ヘルパークラス
 * 仮想時間モードの場合は仮想時間を、そうでない場合は現実時間を返す
 */
class TimeHelper
{
    /**
     * 現在の時間を取得（仮想時間または現実時間）
     * 
     * @param Request|null $request リクエストオブジェクト（仮想時間を取得するため）
     * @return \DateTime
     */
    public static function getCurrentTime(?Request $request = null): \DateTime
    {
        if ($request) {
            return VirtualTimeMiddleware::getCurrentTime($request);
        }
        return new \DateTime();
    }

    /**
     * 現在の時間をISO8601形式の文字列で取得
     * 
     * @param Request|null $request リクエストオブジェクト
     * @return string
     */
    public static function getCurrentTimeString(?Request $request = null): string
    {
        return self::getCurrentTime($request)->format('Y-m-d H:i:s');
    }

    /**
     * SQLクエリで使用する現在時刻のプレースホルダー値を取得
     * 仮想時間が設定されている場合はその値を、そうでない場合はNULLを返す（SQLのCURRENT_TIMESTAMPを使用）
     * 
     * @param Request|null $request リクエストオブジェクト
     * @return string|null
     */
    public static function getSqlCurrentTime(?Request $request = null): ?string
    {
        if ($request) {
            $virtualTime = $request->getParam('virtual_time');
            if ($virtualTime instanceof \DateTime) {
                return $virtualTime->format('Y-m-d H:i:s');
            }
        }
        return null; // NULLの場合はSQLのCURRENT_TIMESTAMPを使用
    }
}

