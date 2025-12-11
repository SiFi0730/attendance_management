<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * 開発用コントローラー（デバッグ機能）
 * 
 * ⚠️ セキュリティ警告:
 *   このコントローラーは開発環境専用です。
 *   本番環境では、このコントローラーへのアクセスを無効化するか、
 *   ルーティングから削除してください。
 */
class DevController
{
    /**
     * データベースリセット
     * POST /dev/reset-database
     * 
     * ⚠️ 警告: 本番環境では絶対に使用しないでください。
     *   すべてのデータが削除されます。
     */
    public function resetDatabase(Request $request, Response $response): void
    {
        // 開発環境のみ許可（本番環境では無効化）
        if (($_ENV['APP_ENV'] ?? 'development') !== 'development') {
            $response->error('FORBIDDEN', 'この機能は開発環境でのみ使用できます', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // すべてのテーブルを削除（外部キー制約を無視）
            $tables = [
                'audit_logs',
                'notifications',
                'file_storage',
                'import_jobs',
                'export_jobs',
                'requests',
                'approval_flows',
                'timesheets',
                'work_sessions',
                'punch_records',
                'holiday_calendars',
                'rule_sets',
                'business_hours',
                'employee_profiles',
                'departments',
                'role_assignments',
                'sessions',
                'password_reset_tokens',
                'users',
                'tenants',
            ];

            // 外部キー制約を一時的に無効化
            $pdo->exec('SET session_replication_role = replica');

            // テーブルを削除（CASCADEで依存関係も削除）
            foreach ($tables as $table) {
                try {
                    $pdo->exec("TRUNCATE TABLE {$table} CASCADE");
                } catch (\PDOException $e) {
                    // テーブルが存在しない場合はスキップ
                    if (strpos($e->getMessage(), 'does not exist') === false) {
                        throw $e;
                    }
                }
            }

            // 外部キー制約を再有効化
            $pdo->exec('SET session_replication_role = DEFAULT');

            // シーケンスをリセット（UUIDを使用しているため不要だが、念のため）
            // PostgreSQLのUUIDはシーケンスを使用しないため、スキップ

            $pdo->commit();

            $response->success([
                'message' => 'データベースをリセットしました',
                'note' => 'スキーマの再適用が必要な場合は、手動でschema.sqlを実行してください'
            ], 'データベースをリセットしました');

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->errorWithException('INTERNAL_ERROR', 'データベースのリセットに失敗しました', $e, [], 500);
        }
    }
}

