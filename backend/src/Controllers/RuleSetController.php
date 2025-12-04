<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * 就業ルールセット管理コントローラー
 */
class RuleSetController
{
    /**
     * 就業ルールセット取得（現在有効なもの）
     * GET /rule-sets/current
     */
    public function current(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT id, name, version, effective_from, effective_to, config
            FROM rule_sets
            WHERE tenant_id = :tenant_id
            AND effective_from <= CURRENT_DATE
            AND (effective_to IS NULL OR effective_to >= CURRENT_DATE)
            ORDER BY effective_from DESC, version DESC
            LIMIT 1
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $ruleSet = $stmt->fetch();

        if (!$ruleSet) {
            // デフォルト設定を返す
            $response->success([
                'id' => null,
                'name' => 'デフォルト',
                'version' => 1,
                'effective_from' => date('Y-m-d'),
                'effective_to' => null,
                'config' => [
                    'work_type' => 'fixed',
                    'overtime_calculation' => 'daily',
                    'night_work_start' => '22:00',
                    'night_work_end' => '05:00',
                    'rounding' => [
                        'start' => '5',
                        'end' => '5',
                        'total' => '5'
                    ]
                ]
            ]);
            return;
        }

        $ruleSet['config'] = is_string($ruleSet['config']) ? json_decode($ruleSet['config'], true) : $ruleSet['config'];
        $response->success($ruleSet);
    }

    /**
     * 就業ルールセット更新
     * PUT /rule-sets/current
     */
    public function updateCurrent(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック
        if (!in_array($role, ['SystemAdmin', 'CompanyAdmin'])) {
            $response->error('FORBIDDEN', '就業ルールを更新する権限がありません', [], 403);
            return;
        }

        $name = $request->getBody('name', 'デフォルト');
        $config = $request->getBody('config');

        if (!is_array($config)) {
            $response->error('VALIDATION_ERROR', 'ルール設定が不正です', [], 400);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // 既存の有効なルールセットの終了日を設定
            $stmt = $pdo->prepare("
                UPDATE rule_sets
                SET effective_to = CURRENT_DATE - INTERVAL '1 day'
                WHERE tenant_id = :tenant_id
                AND effective_from <= CURRENT_DATE
                AND (effective_to IS NULL OR effective_to >= CURRENT_DATE)
            ");
            $stmt->execute(['tenant_id' => $tenantId]);

            // 新しいルールセットを作成
            $stmt = $pdo->prepare("
                INSERT INTO rule_sets (tenant_id, name, version, effective_from, config)
                VALUES (:tenant_id, :name, 1, CURRENT_DATE, :config)
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'name' => $name,
                'config' => json_encode($config),
            ]);
            $ruleSet = $stmt->fetch();
            $ruleSetId = $ruleSet['id'];

            // 監査ログ
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, actor_user_id, action, entity, entity_id, after)
                VALUES (:tenant_id, :actor_user_id, 'rule_set.update', 'rule_set', :entity_id, :after)
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'entity_id' => $ruleSetId,
                'after' => json_encode(['name' => $name, 'config' => $config]),
            ]);

            $pdo->commit();

            // 更新後のデータを取得
            $this->current($request, $response);

        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', '就業ルールの更新に失敗しました: ' . $e->getMessage(), [], 500);
        }
    }
}

