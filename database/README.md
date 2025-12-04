# データベース設計ドキュメント

## 概要

このディレクトリには、勤怠管理システムのPostgreSQLデータベーススキーマ定義が含まれています。

## ファイル構成

- `schema.sql` - メインのスキーマ定義（テーブル、インデックス、制約、トリガー）
- `rls_policies.sql` - Row-Level Security (RLS) ポリシー定義
- `seed_data.sql` - 開発・テスト用のシードデータ
- `create-test-databases.sql` - テスト用データベース作成SQL
- `create-test-databases.ps1` - テスト用データベース作成スクリプト（PowerShell）
- `apply-schema-to-test-dbs.bat` - テストデータベースへのスキーマ適用スクリプト
- `setup-database.bat` - データベースセットアップスクリプト
- `update_passwords.sql` - パスワード更新SQL

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

---

## 📁 データベース構造を確認するファイル

データベース構造を理解するには、以下のファイルを参照してください：

### 1. **`database/schema.sql`** ⭐ 最重要
- **内容**: 全テーブルの定義（CREATE TABLE文）
- **用途**: テーブル構造、カラム、データ型、制約、インデックス、トリガーを確認
- **見方**: SQLファイルを開いて、各テーブルの定義を確認

### 2. **`database/rls_policies.sql`**
- **内容**: Row-Level Security (RLS) ポリシー定義
- **用途**: テナント分離の仕組みを理解
- **見方**: 各テーブルに対するRLSポリシーを確認

### 3. **`doc/仕様書.md`** (第9章: データモデル)
- **内容**: 論理的なデータモデルの説明
- **用途**: エンティティ間の関係とビジネスロジックを理解
- **見方**: 各エンティティの主要フィールドが説明されている

---

## 🗄️ データベース構造の概要

このシステムは**マルチテナント対応**の勤怠管理システムで、**20個のテーブル**で構成されています。

### テーブル分類

#### 1. 認証・認可関連（4テーブル）
- `users` - ユーザーアカウント（12カラム）
- `password_reset_tokens` - パスワードリセットトークン（6カラム）
- `sessions` - セッション管理（8カラム）
- `role_assignments` - 役割割り当て（RBAC）（9カラム）

#### 2. 組織管理関連（3テーブル）
- `tenants` - テナント（企業）（9カラム）
- `departments` - 部署（階層構造対応）（8カラム）
- `employee_profiles` - 従業員プロファイル（15カラム）

#### 3. 就業ルール関連（3テーブル）
- `business_hours` - 営業時間設定（10カラム）
- `rule_sets` - 就業ルールセット（9カラム）
- `holiday_calendars` - 祝日カレンダー（9カラム）

#### 4. 勤怠管理関連（3テーブル）
- `punch_records` - 打刻記録（12カラム）
- `work_sessions` - 勤務セッション（10カラム）
- `timesheets` - タイムシート（13カラム）

#### 5. 申請・承認関連（2テーブル）
- `approval_flows` - 承認フロー定義（7カラム）
- `requests` - 申請（14カラム）

#### 6. ジョブ管理関連（2テーブル）
- `import_jobs` - CSVインポートジョブ（12カラム）
- `export_jobs` - CSVエクスポートジョブ（12カラム）

#### 7. その他（3テーブル）
- `audit_logs` - 監査ログ（11カラム）
- `notifications` - 通知（9カラム）
- `file_storage` - ファイルストレージ（10カラム）

---

## 🔗 主要なリレーション（関係）

### コアリレーション

```
tenants (企業)
  ├── departments (部署) [1対多]
  │     └── employee_profiles (従業員) [1対多]
  │           └── punch_records (打刻) [1対多]
  │           └── work_sessions (勤務セッション) [1対多]
  │           └── timesheets (タイムシート) [1対多]
  │           └── requests (申請) [1対多]
  │
  ├── users (ユーザー) [多対多 via role_assignments]
  │     ├── sessions (セッション) [1対多]
  │     ├── password_reset_tokens (パスワードリセット) [1対多]
  │     └── employee_profiles (従業員プロファイル) [1対1]
  │
  ├── business_hours (営業時間) [1対多]
  ├── rule_sets (就業ルール) [1対多]
  ├── holiday_calendars (祝日) [1対多]
  ├── approval_flows (承認フロー) [1対多]
  ├── import_jobs (インポートジョブ) [1対多]
  ├── export_jobs (エクスポートジョブ) [1対多]
  ├── audit_logs (監査ログ) [1対多]
  ├── notifications (通知) [1対多]
  └── file_storage (ファイル) [1対多]
```

### 詳細なリレーション

#### ユーザーと従業員の関係
```
users (ユーザーアカウント)
  └── employee_profiles (従業員プロファイル) [1対1, オプショナル]
      └── departments (部署) [多対1]
```

#### 打刻と勤務セッションの関係
```
punch_records (打刻記録)
  └── work_sessions (勤務セッション) [集約]
      └── timesheets (タイムシート) [集約]
```

#### 申請と承認の関係
```
requests (申請)
  ├── employee_profiles (申請者) [多対1]
  ├── users (承認者) [多対1]
  └── approval_flows (承認フロー) [多対1]
```

---

## 📊 主要テーブルの詳細

### 1. tenants（テナント/企業）

```sql
CREATE TABLE tenants (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) DEFAULT 'Asia/Tokyo',
    locale VARCHAR(10) DEFAULT 'ja',
    status VARCHAR(20) DEFAULT 'active',
    logo_path VARCHAR(500),
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    deleted_at TIMESTAMP WITH TIME ZONE  -- 論理削除
);
```

**特徴:**
- マルチテナントの核となるテーブル
- すべての業務テーブルが `tenant_id` で参照

### 2. users（ユーザー）

```sql
CREATE TABLE users (
    id UUID PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    mfa_enabled BOOLEAN DEFAULT false,
    mfa_secret VARCHAR(255),
    backup_codes JSONB,
    status VARCHAR(20) DEFAULT 'active',
    last_login_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    deleted_at TIMESTAMP WITH TIME ZONE  -- 論理削除
);
```

**特徴:**
- 認証情報を管理
- MFA（多要素認証）対応
- `employee_profiles` と1対1で紐付け可能

### 3. employee_profiles（従業員プロファイル）

```sql
CREATE TABLE employee_profiles (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID REFERENCES users(id),  -- オプショナル
    employee_code VARCHAR(50) NOT NULL,  -- テナント内で一意
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    dept_id UUID REFERENCES departments(id),
    employment_type VARCHAR(50) NOT NULL,
    hire_date DATE NOT NULL,
    leave_date DATE,
    work_location_tz VARCHAR(50) DEFAULT 'Asia/Tokyo',
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    deleted_at TIMESTAMP WITH TIME ZONE  -- 論理削除
);
```

**特徴:**
- テナント内で `employee_code` が一意
- `user_id` はオプショナル（招待前はNULL）
- 部署（`departments`）に所属

### 4. punch_records（打刻記録）

```sql
CREATE TABLE punch_records (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    employee_id UUID NOT NULL REFERENCES employee_profiles(id),
    type VARCHAR(20) NOT NULL,  -- 'in', 'out', 'break_in', 'break_out'
    occurred_at TIMESTAMP WITH TIME ZONE NOT NULL,
    source VARCHAR(50) DEFAULT 'web',
    device VARCHAR(255),
    note TEXT,
    proxy_user_id UUID REFERENCES users(id),  -- 代理打刻者
    proxy_reason TEXT,  -- 代理打刻理由
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    -- 冪等性: 同じ打刻は重複不可
    UNIQUE (tenant_id, employee_id, type, occurred_at)
);
```

**特徴:**
- 冪等性保証（同じ打刻は重複不可）
- 代理打刻対応
- 打刻タイプ: 出勤(in)、退勤(out)、休憩開始(break_in)、休憩終了(break_out)

### 5. work_sessions（勤務セッション）

```sql
CREATE TABLE work_sessions (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    employee_id UUID NOT NULL REFERENCES employee_profiles(id),
    start_at TIMESTAMP WITH TIME ZONE NOT NULL,
    end_at TIMESTAMP WITH TIME ZONE,
    total_break_minutes INTEGER DEFAULT 0,
    work_minutes INTEGER,  -- 実働時間（分）
    anomalies JSONB,  -- 異常情報（未打刻、順序エラー等）
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**特徴:**
- `punch_records` から自動生成される1日の勤務単位
- 異常検知情報をJSONBで保存

### 6. timesheets（タイムシート）

```sql
CREATE TABLE timesheets (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    employee_id UUID NOT NULL REFERENCES employee_profiles(id),
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',  -- draft, submitted, approved, rejected
    totals JSONB,  -- 集計結果（実働時間、残業時間等）
    submitted_at TIMESTAMP WITH TIME ZONE,
    approved_by UUID REFERENCES users(id),
    approved_at TIMESTAMP WITH TIME ZONE,
    rejection_reason TEXT,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**特徴:**
- 日/週/月次集計単位
- 承認フロー対応
- 集計結果をJSONBで保存

---

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

---

## 🔐 セキュリティ設計

### テナント分離

すべての業務テーブルに `tenant_id` カラムがあり、Row-Level Security (RLS) で分離されています。

```sql
-- 例: employee_profiles のRLSポリシー
CREATE POLICY tenant_isolation_employee_profiles ON employee_profiles
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);
```

### 論理削除

重要なテーブルには `deleted_at` カラムがあり、物理削除ではなく論理削除を実装しています。

```sql
-- 論理削除されたレコードを除外
SELECT * FROM users WHERE deleted_at IS NULL;
```

### 冪等性

打刻記録は重複を防ぐため、ユニーク制約が設定されています。

```sql
-- punch_records の冪等キー
UNIQUE (tenant_id, employee_id, type, occurred_at)
```

---

## 📈 インデックス戦略

### 複合インデックス

すべての業務テーブルに `(tenant_id, ...)` の複合インデックスが設定されています。

```sql
-- 例: employee_profiles
CREATE INDEX idx_employee_profiles_tenant_id 
ON employee_profiles(tenant_id) 
WHERE deleted_at IS NULL;
```

### 日付範囲検索用インデックス

```sql
-- punch_records の日付検索用
CREATE INDEX idx_punch_records_tenant_occurred 
ON punch_records(tenant_id, occurred_at);
```

---

## 🛠️ データベース構造を確認する方法

### 方法1: SQLファイルを読む

```bash
# schema.sql を開く
code database/schema.sql
```

### 方法2: Adminerで確認

1. `http://localhost:8080/adminer-login.php` にアクセス
2. テーブル一覧を確認
3. 各テーブルの「構造」タブで詳細を確認

### 方法3: psqlコマンドで確認

```bash
# テーブル一覧
psql -U attendance_user -d attendance_management -c "\dt"

# テーブル構造を確認
psql -U attendance_user -d attendance_management -c "\d users"

# リレーションを確認
psql -U attendance_user -d attendance_management -c "\d+ employee_profiles"
```

### 方法4: ER図を生成（推奨ツール）

- **pgAdmin**: PostgreSQL公式の管理ツール
- **DBeaver**: 汎用データベース管理ツール（ER図生成機能あり）
- **dbdiagram.io**: オンラインER図作成ツール

---

## セキュリティ

### Row-Level Security (RLS)
- テナント単位でのデータ分離を保証
- `rls_policies.sql` でポリシーを定義

### 注意事項
- RLSは防御の一層として機能
- アプリケーションレベルでのテナントチェックも必須
- SystemAdminの場合は特別な処理が必要

---

## パフォーマンス

### インデックス最適化
- 頻繁に検索されるカラムにインデックスを設定
- 複合インデックスでクエリ性能を向上

### パーティショニング（将来対応）
- 大規模データの場合は、`punch_records`, `audit_logs` を日付でパーティショニング検討

---

## マイグレーション

### 推奨ツール
- Knex.js (Node.js)
- Alembic (Python)
- Flyway (Java)

### バージョン管理
- スキーマ変更は必ずマイグレーションファイルで管理
- ロールバック手順を準備

---

## バックアップ・リストア

### バックアップ
```bash
pg_dump -d attendance_management -F c -f backup.dump
```

### リストア
```bash
pg_restore -d attendance_management backup.dump
```

---

## テスト用データベース

### 作成されるデータベース

以下の4つのテスト用データベースが作成されます：

1. `attendance_management_test`
2. `attendance_management_test1`
3. `attendance_management_test2`
4. `attendance_management_test3`

### 作成方法

#### 方法1: SQLファイルを実行（推奨）

```cmd
psql -U postgres -f database\create-test-databases.sql
```

postgresユーザーのパスワードを入力します。

#### 方法2: PowerShellスクリプトを実行

```powershell
.\database\create-test-databases.ps1
```

### スキーマの適用

```cmd
database\apply-schema-to-test-dbs.bat
```

または手動で各データベースに適用：

```cmd
psql -U attendance_user -d attendance_management_test -f database\schema.sql
psql -U attendance_user -d attendance_management_test -f database\rls_policies.sql
```

### 確認方法

#### データベース一覧を確認

```cmd
psql -U postgres -c "\l" | findstr "attendance_management"
```

#### 各データベースに接続して確認

```cmd
psql -U attendance_user -d attendance_management_test
```

データベース内で：

```sql
-- テーブル一覧を確認
\dt

-- テーブル数を確認
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';
```

### 各データベースの用途例

- **attendance_management_test**: ユニットテスト用、基本的な機能テスト
- **attendance_management_test1**: 統合テスト用、APIテスト
- **attendance_management_test2**: パフォーマンステスト用、大量データテスト
- **attendance_management_test3**: E2Eテスト用、エンドツーエンドテスト

### 注意事項

1. **データベース作成にはスーパーユーザー権限が必要**
   - postgresユーザーで実行する必要があります

2. **スキーマの適用**
   - 各データベースに個別にスキーマを適用する必要があります

3. **テストデータ**
   - 必要に応じて `seed_data.sql` を各データベースに適用できます

### データベースの削除

テスト用データベースを削除する場合：

```sql
-- postgresユーザーで実行
DROP DATABASE attendance_management_test;
DROP DATABASE attendance_management_test1;
DROP DATABASE attendance_management_test2;
DROP DATABASE attendance_management_test3;
```

または：

```cmd
psql -U postgres -c "DROP DATABASE attendance_management_test;"
psql -U postgres -c "DROP DATABASE attendance_management_test1;"
psql -U postgres -c "DROP DATABASE attendance_management_test2;"
psql -U postgres -c "DROP DATABASE attendance_management_test3;"
```

---

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

---

## 📝 データモデルの論理的な説明

詳細な論理的なデータモデルは `doc/仕様書.md` の第9章「データモデル」を参照してください。

主要なエンティティ:
- **Tenant**: 企業単位のデータ分離
- **User**: 認証情報
- **EmployeeProfile**: 従業員情報
- **PunchRecord**: 打刻イベント
- **WorkSession**: 1日の勤務単位
- **Timesheet**: 集計単位

---

## 🔍 よくある質問

### Q: テーブル間の関係を確認するには？

A: `database/schema.sql` の外部キー制約（`REFERENCES`）を確認してください。

### Q: どのテーブルが重要？

A: コアテーブルは以下です：
- `tenants` - テナント分離の核
- `users` - 認証の核
- `employee_profiles` - 従業員管理の核
- `punch_records` - 打刻の核
- `work_sessions` - 集計の核

### Q: JSONBカラムは何に使われている？

A: 柔軟な設定データを保存するために使用：
- `rule_sets.config` - 就業ルール設定
- `timesheets.totals` - 集計結果
- `requests.payload` - 申請内容
- `work_sessions.anomalies` - 異常情報

---

## 参考資料

- **スキーマ定義**: `database/schema.sql`
- **RLSポリシー**: `database/rls_policies.sql`
- **シードデータ**: `database/seed_data.sql`
- **仕様書**: `doc/仕様書.md` (第9章)
- **実装手順書**: `doc/実装手順書.md`
- **PostgreSQL公式ドキュメント**: https://www.postgresql.org/docs/

---

最終更新: 2025-01-15
