<?php

namespace App\Core;

use PDO;

/**
 * 部署階層管理クラス
 * Managerロールが配下の従業員を取得するためのヘルパークラス
 */
class DepartmentHierarchy
{
    /**
     * 指定された部署IDとその配下の部署IDを再帰的に取得
     * 
     * @param PDO $pdo データベース接続
     * @param string $tenantId テナントID
     * @param string $deptId 部署ID
     * @return array 部署IDの配列（指定された部署IDを含む）
     */
    public static function getSubordinateDepartmentIds(PDO $pdo, string $tenantId, string $deptId): array
    {
        // 再帰CTEを使用して配下の部署を取得
        $stmt = $pdo->prepare("
            WITH RECURSIVE dept_tree AS (
                -- 基点: 指定された部署
                SELECT id, parent_id, tenant_id
                FROM departments
                WHERE id = :dept_id 
                  AND tenant_id = :tenant_id 
                  AND deleted_at IS NULL
                
                UNION ALL
                
                -- 再帰: 子部署を取得
                SELECT d.id, d.parent_id, d.tenant_id
                FROM departments d
                INNER JOIN dept_tree dt ON d.parent_id = dt.id
                WHERE d.tenant_id = :tenant_id 
                  AND d.deleted_at IS NULL
            )
            SELECT id FROM dept_tree
        ");
        
        $stmt->execute([
            'dept_id' => $deptId,
            'tenant_id' => $tenantId,
        ]);
        
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $result ?: [$deptId]; // 結果がない場合は元の部署IDのみ返す
    }

    /**
     * Managerの配下従業員IDを取得
     * Managerが所属する部署とその配下部署に所属する従業員を取得
     * 
     * @param PDO $pdo データベース接続
     * @param string $tenantId テナントID
     * @param string $userId ManagerのユーザーID
     * @return array 従業員IDの配列
     */
    public static function getSubordinateEmployeeIds(PDO $pdo, string $tenantId, string $userId): array
    {
        // Managerの従業員プロファイルを取得
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.dept_id
            FROM employee_profiles ep
            WHERE ep.user_id = :user_id 
              AND ep.tenant_id = :tenant_id 
              AND ep.deleted_at IS NULL
            LIMIT 1
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);
        
        $manager = $stmt->fetch();
        
        if (!$manager || !$manager['dept_id']) {
            // Managerの部署が取得できない場合は空配列を返す
            return [];
        }
        
        // Managerの部署とその配下部署を取得
        $deptIds = self::getSubordinateDepartmentIds($pdo, $tenantId, $manager['dept_id']);
        
        if (empty($deptIds)) {
            return [];
        }
        
        // 配下部署に所属する従業員IDを取得
        $placeholders = [];
        $params = ['tenant_id' => $tenantId];
        foreach ($deptIds as $index => $deptId) {
            $key = 'dept_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $deptId;
        }
        
        $stmt = $pdo->prepare("
            SELECT ep.id
            FROM employee_profiles ep
            WHERE ep.tenant_id = :tenant_id
              AND ep.dept_id IN (" . implode(',', $placeholders) . ")
              AND ep.deleted_at IS NULL
        ");
        
        $stmt->execute($params);
        
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $result ?: [];
    }

    /**
     * Managerが指定された従業員を管理できるかチェック
     * 
     * @param PDO $pdo データベース接続
     * @param string $tenantId テナントID
     * @param string $managerUserId ManagerのユーザーID
     * @param string $employeeId チェック対象の従業員ID
     * @return bool 管理できる場合はtrue
     */
    public static function canManageEmployee(PDO $pdo, string $tenantId, string $managerUserId, string $employeeId): bool
    {
        $subordinateIds = self::getSubordinateEmployeeIds($pdo, $tenantId, $managerUserId);
        return in_array($employeeId, $subordinateIds, true);
    }

    /**
     * Managerの配下従業員IDのSQL条件を生成
     * 
     * @param PDO $pdo データベース接続
     * @param string $tenantId テナントID
     * @param string $userId ManagerのユーザーID
     * @param string $columnName 従業員IDカラム名（デフォルト: 'ep.id'）
     * @return array ['where' => string, 'params' => array] WHERE句とパラメータ
     */
    public static function getSubordinateEmployeeCondition(PDO $pdo, string $tenantId, string $userId, string $columnName = 'ep.id'): array
    {
        $employeeIds = self::getSubordinateEmployeeIds($pdo, $tenantId, $userId);
        
        if (empty($employeeIds)) {
            // 配下従業員がいない場合は、該当なしを返す
            return [
                'where' => '1=0', // 常にfalse
                'params' => []
            ];
        }
        
        $placeholders = [];
        $params = [];
        foreach ($employeeIds as $index => $id) {
            $key = 'employee_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        
        return [
            'where' => $columnName . ' IN (' . implode(', ', $placeholders) . ')',
            'params' => $params
        ];
    }
}

