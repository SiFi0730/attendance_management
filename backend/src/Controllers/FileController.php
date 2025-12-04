<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * ファイル管理コントローラー
 */
class FileController
{
    /**
     * ファイルアップロード
     * POST /files/upload
     */
    public function upload(Request $request, Response $response): void
    {
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');

        $entityType = $request->getBody('entity_type');
        $entityId = $request->getBody('entity_id');

        // バリデーション
        if (empty($entityType)) {
            $response->error('VALIDATION_ERROR', 'entity_typeは必須です', [], 400);
            return;
        }

        $validEntityTypes = ['tenant_logo', 'request_attachment', 'other'];
        if (!in_array($entityType, $validEntityTypes)) {
            $response->error('VALIDATION_ERROR', '無効なentity_typeです', [], 400);
            return;
        }

        // ファイルアップロード処理（現時点では簡易実装）
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $response->error('VALIDATION_ERROR', 'ファイルのアップロードに失敗しました', [], 400);
            return;
        }

        $file = $_FILES['file'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $mimeType = $file['type'];
        $tmpPath = $file['tmp_name'];

        // ファイルサイズチェック（最大10MB）
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($fileSize > $maxSize) {
            $response->error('VALIDATION_ERROR', 'ファイルサイズが大きすぎます（最大10MB）', [], 400);
            return;
        }

        // ファイル形式チェック
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml',
            'application/pdf'
        ];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $response->error('VALIDATION_ERROR', '許可されていないファイル形式です', [], 400);
            return;
        }

        // ファイル保存先（開発環境: ローカル、本番環境: S3等）
        $uploadDir = __DIR__ . '/../../storage/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileId = bin2hex(random_bytes(16));
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileId . '.' . $fileExtension;

        if (!move_uploaded_file($tmpPath, $filePath)) {
            $response->error('INTERNAL_ERROR', 'ファイルの保存に失敗しました', [], 500);
            return;
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            // ファイル情報をデータベースに保存
            $stmt = $pdo->prepare("
                INSERT INTO file_storage (
                    tenant_id, entity_type, entity_id, file_name, file_path,
                    file_size, mime_type, uploaded_by, created_at
                ) VALUES (
                    :tenant_id, :entity_type, :entity_id, :file_name, :file_path,
                    :file_size, :mime_type, :uploaded_by, CURRENT_TIMESTAMP
                )
                RETURNING id
            ");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => $userId,
            ]);
            $result = $stmt->fetch();
            $fileStorageId = $result['id'];

            $pdo->commit();

            // 作成したファイル情報を取得
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    tenant_id,
                    entity_type,
                    entity_id,
                    file_name,
                    file_path,
                    file_size,
                    mime_type,
                    uploaded_by,
                    created_at
                FROM file_storage
                WHERE id = :id
            ");
            $stmt->execute(['id' => $fileStorageId]);
            $fileStorage = $stmt->fetch();

            $response->success($fileStorage, 'ファイルをアップロードしました', 201);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            // アップロードしたファイルを削除
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $response->error('INTERNAL_ERROR', 'ファイルの保存に失敗しました: ' . $e->getMessage(), [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            // アップロードしたファイルを削除
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $response->error('INTERNAL_ERROR', 'ファイルの保存に失敗しました: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * ファイル取得
     * GET /files/{id}
     */
    public function show(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');

        $pdo = Database::getInstance();

        $stmt = $pdo->prepare("
            SELECT 
                id,
                tenant_id,
                entity_type,
                entity_id,
                file_name,
                file_path,
                file_size,
                mime_type,
                uploaded_by,
                created_at
            FROM file_storage
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $fileStorage = $stmt->fetch();

        if (!$fileStorage) {
            $response->error('NOT_FOUND', 'ファイルが見つかりません', [], 404);
            return;
        }

        // ファイルパスを返す（実際のダウンロードは別エンドポイントで実装）
        $response->success($fileStorage);
    }

    /**
     * ファイルダウンロード
     * GET /files/{id}/download
     */
    public function download(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');

        $pdo = Database::getInstance();

        $stmt = $pdo->prepare("
            SELECT file_path, file_name, mime_type
            FROM file_storage
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $fileStorage = $stmt->fetch();

        if (!$fileStorage || !file_exists($fileStorage['file_path'])) {
            $response->error('NOT_FOUND', 'ファイルが見つかりません', [], 404);
            return;
        }

        // ファイルをダウンロード
        header('Content-Type: ' . $fileStorage['mime_type']);
        header('Content-Disposition: attachment; filename="' . $fileStorage['file_name'] . '"');
        header('Content-Length: ' . filesize($fileStorage['file_path']));
        readfile($fileStorage['file_path']);
        exit;
    }

    /**
     * ファイル削除
     * DELETE /files/{id}
     */
    public function delete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $tenantId = $request->getParam('tenant_id');
        $userId = $request->getParam('user_id');
        $role = $request->getParam('role');

        // 権限チェック（CompanyAdmin、Professionalのみ）
        if (!in_array($role, ['CompanyAdmin', 'Professional'])) {
            $response->error('FORBIDDEN', 'ファイルを削除する権限がありません', [], 403);
            return;
        }

        $pdo = Database::getInstance();

        // ファイル情報を取得
        $stmt = $pdo->prepare("
            SELECT file_path
            FROM file_storage
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $fileStorage = $stmt->fetch();

        if (!$fileStorage) {
            $response->error('NOT_FOUND', 'ファイルが見つかりません', [], 404);
            return;
        }

        try {
            $pdo->beginTransaction();

            // データベースから削除
            $stmt = $pdo->prepare("
                DELETE FROM file_storage
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

            $pdo->commit();

            // 物理ファイルを削除
            if (file_exists($fileStorage['file_path'])) {
                unlink($fileStorage['file_path']);
            }

            $response->success(null, 'ファイルを削除しました', 200);

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', 'ファイルの削除に失敗しました: ' . $e->getMessage(), [], 500);
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->error('INTERNAL_ERROR', 'ファイルの削除に失敗しました: ' . $e->getMessage(), [], 500);
        }
    }
}

