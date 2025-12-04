# データベース設計ドキュメント

## 概要

このディレクトリには、勤怠管理システムのPostgreSQLデータベーススキーマ定義が含まれています。

## ファイル構成

- `schema.sql` - メインのスキーマ定義（テーブル、インデックス、制約、トリガー）
- `rls_policies.sql` - Row-Level Security (RLS) ポリシー定義
- `seed_data.sql` - 開発・テスト用のシードデータ

## データベース要件

- PostgreSQL 12以上
- 拡張機能: `uuid-ossp`, `pg_trgm`

## セットアップ手順

### 1. データベース作成

```bash
createdb attendance_management
```

### 2. スキーマ適用

```bash
psql -d attendance_management -f schema.sql
```

### 3. RLSポリシー適用（オプション）

```bash
psql -d attendance_management -f rls_policies.sql
```

### 4. シードデータ投入（開発環境のみ）

```bash
psql -d attendance_management -f seed_data.sql
```

## テーブル一覧

### 認証・認可関連
- `users` - ユーザーアカウント
- `password_reset_tokens` - パスワードリセットトークン
- `sessions` - セッション管理
- `role_assignments` - 役割割り当て（RBAC）

### 組織管理関連
- `tenants` - テナント（企業）
- `departments` - 部署（階層構造対応）
- `employee_profiles` - 従業員プロファイル

### 就業ルール関連
- `business_hours` - 営業時間設定
- `rule_sets` - 就業ルールセット
- `holiday_calendars` - 祝日カレンダー

### 勤怠管理関連
- `punch_records` - 打刻記録
- `work_sessions` - 勤務セッション
- `timesheets` - タイムシート

### 申請・承認関連
- `approval_flows` - 承認フロー定義
- `requests` - 申請

### ジョブ管理関連
- `import_jobs` - CSVインポートジョブ
- `export_jobs` - CSVエクスポートジョブ

### その他
- `audit_logs` - 監査ログ
- `notifications` - 通知
- `file_storage` - ファイルストレージ

## 主要な設計方針

### 1. テナント分離
- すべての業務テーブルに `tenant_id` カラムを配置
- Row-Level Security (RLS) でデータベースレベルでの分離を保証
- アプリケーションレベルでのチェックも必須

### 2. 論理削除
- `deleted_at` カラムによる論理削除
- 物理削除は30日後に自動実行（設定で変更可能）

### 3. 冪等性
- `punch_records` テーブルに `(tenant_id, employee_id, type, occurred_at)` のユニーク制約
- 重複打刻を防止

### 4. インデックス戦略
- すべての業務テーブルに `(tenant_id, ...)` 複合インデックス
- 日付範囲検索用のインデックス
- ステータス検索用のインデックス

### 5. タイムスタンプ
- `created_at`, `updated_at` を自動管理（トリガー）
- `updated_at` は更新時に自動更新

### 6. JSONB活用
- 柔軟な設定データ（`rule_sets.config`）
- 集計結果（`timesheets.totals`）
- 申請内容（`requests.payload`）

## セキュリティ

### Row-Level Security (RLS)
- テナント単位でのデータ分離を保証
- `rls_policies.sql` でポリシーを定義

### 注意事項
- RLSは防御の一層として機能
- アプリケーションレベルでのテナントチェックも必須
- SystemAdminの場合は特別な処理が必要

## パフォーマンス

### インデックス最適化
- 頻繁に検索されるカラムにインデックスを設定
- 複合インデックスでクエリ性能を向上

### パーティショニング（将来対応）
- 大規模データの場合は、`punch_records`, `audit_logs` を日付でパーティショニング検討

## マイグレーション

### 推奨ツール
- Knex.js (Node.js)
- Alembic (Python)
- Flyway (Java)

### バージョン管理
- スキーマ変更は必ずマイグレーションファイルで管理
- ロールバック手順を準備

## バックアップ・リストア

### バックアップ
```bash
pg_dump -d attendance_management -F c -f backup.dump
```

### リストア
```bash
pg_restore -d attendance_management backup.dump
```

## トラブルシューティング

### よくある問題

1. **RLSポリシーでアクセスできない**
   - `set_tenant_id()` 関数でテナントIDを設定しているか確認
   - SystemAdminの場合は特別な処理が必要

2. **循環参照エラー（部署）**
   - 部署の階層構造を確認
   - 最大5階層まで

3. **冪等性エラー（打刻）**
   - 同一の `(tenant_id, employee_id, type, occurred_at)` で重複打刻しようとしていないか確認

## 参考資料

- 仕様書: `doc/仕様書.md`
- 実装手順書: `doc/実装手順書.md`
- PostgreSQL公式ドキュメント: https://www.postgresql.org/docs/

