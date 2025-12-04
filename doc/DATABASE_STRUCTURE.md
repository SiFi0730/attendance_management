# データベース構造ガイド

## 📁 データベース構造を確認するファイル

データベース構造を理解するには、以下のファイルを参照してください：

### 1. **`database/schema.sql`** ⭐ 最重要
- **内容**: 全テーブルの定義（CREATE TABLE文）
- **用途**: テーブル構造、カラム、データ型、制約、インデックス、トリガーを確認
- **見方**: SQLファイルを開いて、各テーブルの定義を確認

### 2. **`database/README.md`**
- **内容**: データベース設計の概要とテーブル一覧
- **用途**: テーブルの分類と主要な設計方針を理解
- **見方**: テーブルが機能別に分類されている

### 3. **`doc/仕様書.md`** (第9章: データモデル)
- **内容**: 論理的なデータモデルの説明
- **用途**: エンティティ間の関係とビジネスロジックを理解
- **見方**: 各エンティティの主要フィールドが説明されている

### 4. **`database/rls_policies.sql`**
- **内容**: Row-Level Security (RLS) ポリシー定義
- **用途**: テナント分離の仕組みを理解
- **見方**: 各テーブルに対するRLSポリシーを確認

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

## 📚 参考資料

- **スキーマ定義**: `database/schema.sql`
- **設計ドキュメント**: `database/README.md`
- **仕様書**: `doc/仕様書.md` (第9章)
- **RLSポリシー**: `database/rls_policies.sql`
- **シードデータ**: `database/seed_data.sql`

---

最終更新: 2025-01-15

