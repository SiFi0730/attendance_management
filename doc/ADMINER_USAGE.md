# Adminer 使用方法ガイド

## 🚀 5分で始めるAdminer

### ステップ1: アクセス

ブラウザで以下にアクセス:
```
http://localhost:8080/adminer-login.php
```

### ステップ2: ログイン

「Adminerを開く」ボタンをクリック

または、直接以下にアクセスしてログイン情報を入力:
```
http://localhost:8080/adminer.php
```

**ログイン情報:**
- システム: **PostgreSQL**
- サーバー: `localhost:5432`
- ユーザー名: `attendance_user`
- パスワード: `<データベース作成時に設定したパスワード>`
- データベース: `attendance_management`

⚠️ **注意**: このドキュメントは開発環境用です。本番環境では、Adminerへのアクセスを制限してください。

### ステップ3: データベースを選択

左側のメニューから **attendance_management** をクリック

### ステップ4: テーブルを確認

20個のテーブルが表示されます。例えば:
- `users` - ユーザー一覧を確認
- `tenants` - テナント（企業）一覧を確認
- `employee_profiles` - 従業員一覧を確認

---

## 📋 目次

1. [初回アクセス](#初回アクセス)
2. [基本的な使い方](#基本的な使い方)
3. [よく使う機能](#よく使う機能)
4. [実践例](#実践例)
5. [トラブルシューティング](#トラブルシューティング)

---

## 初回アクセス

### ステップ1: ログインページにアクセス

1. ブラウザを開く
2. 以下のURLにアクセス:
   ```
   http://localhost:8080/adminer-login.php
   ```

### ステップ2: Adminerを開く

1. 画面に表示される「Adminerを開く」ボタンをクリック
2. または、直接以下のURLにアクセス:
   ```
   http://localhost:8080/adminer.php
   ```

### ステップ3: ログイン情報を入力

ログイン画面で以下を入力:

- **システム**: `PostgreSQL` を選択（ドロップダウンから）
- **サーバー**: `localhost:5432`
- **ユーザー名**: `attendance_user`
- **パスワード**: `attendance_password`
- **データベース**: `attendance_management`（または空欄のまま）

「ログイン」ボタンをクリック

---

## 基本的な使い方

### データベース一覧画面

ログイン後、左側にデータベース一覧が表示されます。

- **attendance_management** をクリックしてデータベースを選択

### テーブル一覧の表示

データベースを選択すると、以下の20個のテーブルが表示されます:

1. `approval_flows` - 承認フロー定義
2. `audit_logs` - 監査ログ
3. `business_hours` - 営業時間設定
4. `departments` - 部署
5. `employee_profiles` - 従業員プロファイル
6. `export_jobs` - エクスポートジョブ
7. `file_storage` - ファイルストレージ
8. `holiday_calendars` - 祝日カレンダー
9. `import_jobs` - インポートジョブ
10. `notifications` - 通知
11. `password_reset_tokens` - パスワードリセットトークン
12. `punch_records` - 打刻記録
13. `requests` - 申請
14. `role_assignments` - 役割割り当て
15. `rule_sets` - 就業ルールセット
16. `sessions` - セッション管理
17. `tenants` - テナント（企業）
18. `timesheets` - タイムシート
19. `users` - ユーザーアカウント
20. `work_sessions` - 勤務セッション

---

## よく使う機能

### 1. テーブルのデータを閲覧する

1. テーブル名をクリック（例: `users`）
2. 「選択」タブが開く
3. データが一覧表示される
4. ページネーションでページを移動可能

**便利な機能:**
- 検索ボックスでデータを検索
- 列名をクリックでソート
- 「編集」リンクで個別レコードを編集

### 2. データを追加する

1. テーブル名をクリック
2. 「新規アイテム」タブをクリック
3. フォームにデータを入力
4. 「保存」ボタンをクリック

**例: ユーザーを追加**
```
テーブル: users
- email: test@example.com
- password_hash: $2y$10$... (bcryptハッシュ)
- name: テストユーザー
- status: active
```

### 3. データを編集する

1. テーブル名をクリック
2. 「選択」タブで編集したい行の「編集」リンクをクリック
3. フォームでデータを変更
4. 「保存」ボタンをクリック

### 4. データを削除する

1. テーブル名をクリック
2. 「選択」タブで削除したい行の「削除」リンクをクリック
3. 確認ダイアログで「OK」をクリック

**注意:** 論理削除のテーブル（`deleted_at`カラムがある）では、物理削除ではなく`deleted_at`を更新してください。

### 5. SQLクエリを実行する

1. 上部メニューの「SQLコマンド」をクリック
2. SQLクエリを入力
3. 「実行」ボタンをクリック

**よく使うSQL例:**

```sql
-- 全ユーザーを表示
SELECT * FROM users WHERE deleted_at IS NULL;

-- テナント一覧
SELECT id, name, timezone, status FROM tenants WHERE deleted_at IS NULL;

-- 従業員一覧（部署情報付き）
SELECT 
    ep.employee_code,
    ep.name,
    ep.email,
    d.name as department_name
FROM employee_profiles ep
LEFT JOIN departments d ON ep.dept_id = d.id
WHERE ep.deleted_at IS NULL;

-- 打刻記録の最新10件
SELECT 
    pr.type,
    pr.occurred_at,
    ep.name as employee_name
FROM punch_records pr
JOIN employee_profiles ep ON pr.employee_id = ep.id
ORDER BY pr.occurred_at DESC
LIMIT 10;
```

### 6. テーブル構造を確認する

1. テーブル名をクリック
2. 「構造」タブをクリック
3. カラム名、データ型、制約、インデックスが表示される

### 7. インデックスを確認する

1. テーブル名をクリック
2. 「インデックス」タブをクリック
3. 設定されているインデックスが一覧表示される

---

## 実践例

### 例1: テストユーザーを作成する

1. `users` テーブルを開く
2. 「新規アイテム」タブをクリック
3. 以下を入力:
   ```
   id: (自動生成されるUUID)
   email: test@example.com
   password_hash: $2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy
   name: テストユーザー
   status: active
   mfa_enabled: false
   ```
4. 「保存」をクリック

**パスワードハッシュの生成方法:**
```php
// PHPで実行
echo password_hash('your_password', PASSWORD_BCRYPT);
```

### 例2: テナントを追加する

1. `tenants` テーブルを開く
2. 「新規アイテム」タブをクリック
3. 以下を入力:
   ```
   id: (自動生成されるUUID)
   name: 株式会社テスト
   timezone: Asia/Tokyo
   locale: ja
   status: active
   ```
4. 「保存」をクリック

### 例3: 従業員データを確認する

1. 「SQLコマンド」をクリック
2. 以下のSQLを実行:
   ```sql
   SELECT 
       ep.employee_code,
       ep.name,
       ep.email,
       d.name as department,
       ep.employment_type,
       ep.hire_date,
       ep.status
   FROM employee_profiles ep
   LEFT JOIN departments d ON ep.dept_id = d.id
   WHERE ep.deleted_at IS NULL
   ORDER BY ep.employee_code;
   ```

### 例4: 打刻記録を確認する

1. 「SQLコマンド」をクリック
2. 以下のSQLを実行:
   ```sql
   SELECT 
       pr.type,
       pr.occurred_at,
       ep.name as employee_name,
       pr.source,
       pr.note
   FROM punch_records pr
   JOIN employee_profiles ep ON pr.employee_id = ep.id
   WHERE pr.occurred_at >= CURRENT_DATE
   ORDER BY pr.occurred_at DESC;
   ```

### 例5: データをエクスポートする

1. テーブル名をクリック
2. 「選択」タブでデータを表示
3. 上部の「エクスポート」リンクをクリック
4. フォーマットを選択（CSV、SQL、JSON等）
5. 「実行」をクリック

---

## 🔍 便利なSQLクエリ集

### 全テーブルのレコード数を確認
```sql
SELECT 
    schemaname,
    tablename,
    n_tup_ins as inserts,
    n_tup_upd as updates,
    n_tup_del as deletes,
    n_live_tup as live_rows
FROM pg_stat_user_tables
ORDER BY tablename;
```

### ユーザーと従業員の紐付けを確認
```sql
SELECT 
    u.email,
    u.name as user_name,
    ep.employee_code,
    ep.name as employee_name,
    d.name as department
FROM users u
LEFT JOIN employee_profiles ep ON u.id = ep.user_id
LEFT JOIN departments d ON ep.dept_id = d.id
WHERE u.deleted_at IS NULL;
```

### 今日の打刻記録を確認
```sql
SELECT 
    pr.type,
    pr.occurred_at,
    ep.name as employee_name,
    d.name as department
FROM punch_records pr
JOIN employee_profiles ep ON pr.employee_id = ep.id
LEFT JOIN departments d ON ep.dept_id = d.id
WHERE DATE(pr.occurred_at) = CURRENT_DATE
ORDER BY pr.occurred_at DESC;
```

### テナントごとの従業員数を確認
```sql
SELECT 
    t.name as tenant_name,
    COUNT(ep.id) as employee_count
FROM tenants t
LEFT JOIN employee_profiles ep ON t.id = ep.tenant_id AND ep.deleted_at IS NULL
WHERE t.deleted_at IS NULL
GROUP BY t.id, t.name;
```

---

## 画面の見方

### メイン画面の構成

```
┌─────────────────────────────────────────┐
│  [データベース] [SQLコマンド] [エクスポート] │  ← 上部メニュー
├─────────────────────────────────────────┤
│                                         │
│  左側: テーブル一覧                      │
│                                         │
│  右側: 選択したテーブルの内容            │
│                                         │
└─────────────────────────────────────────┘
```

### タブの説明

- **選択**: データの一覧表示・検索
- **新規アイテム**: 新しいレコードを追加
- **構造**: テーブル構造（カラム、型、制約）
- **インデックス**: インデックスの一覧
- **SQL**: そのテーブル用のSQLクエリ例

---

## 便利なショートカット

### キーボードショートカット

- `Ctrl + F`: ページ内検索
- `Ctrl + Enter`: SQLクエリを実行（SQLコマンド画面）

### よく使うリンク

- **データベース名をクリック**: データベース一覧に戻る
- **テーブル名をクリック**: そのテーブルのデータを表示
- **「SQLコマンド」**: 任意のSQLを実行

---

## トラブルシューティング

### 接続できない場合

1. **PostgreSQLサービスが起動しているか確認**
   ```cmd
   sc query postgresql-x64-18
   ```
   起動していない場合:
   ```cmd
   net start postgresql-x64-18
   ```

2. **接続情報を確認**
   - サーバー: `localhost:5432`
   - ユーザー名: `attendance_user`
   - パスワード: `attendance_password`
   - データベース: `attendance_management`

3. **ポートが使用されているか確認**
   ```cmd
   netstat -an | findstr :5432
   ```

### SQLエラーが発生する場合

1. **RLSポリシーの影響**
   - テナント分離のため、一部のクエリでエラーが出る場合があります
   - テナントIDを指定してクエリを実行してください

2. **権限エラー**
   - `attendance_user` には必要な権限が付与されています
   - それでもエラーが出る場合は、postgresユーザーで権限を再付与:
   ```sql
   GRANT ALL PRIVILEGES ON DATABASE attendance_management TO attendance_user;
   ```

### データが表示されない場合

1. **論理削除の確認**
   - `deleted_at IS NULL` の条件を追加:
   ```sql
   SELECT * FROM users WHERE deleted_at IS NULL;
   ```

2. **テナントIDの確認**
   - テナント分離されているため、適切なテナントIDでフィルタリング:
   ```sql
   SELECT * FROM employee_profiles 
   WHERE tenant_id = 'your-tenant-id' AND deleted_at IS NULL;
   ```

---

## セキュリティ注意事項

⚠️ **重要**: このAdminerは開発環境専用です

1. **本番環境では使用しない**
   - 本番環境では適切なアクセス制御を設定してください

2. **パスワードの管理**
   - 接続情報は環境変数（`.env`）で管理されています
   - `.env`ファイルはGitにコミットしないでください

3. **操作の注意**
   - データの削除・変更は慎重に行ってください
   - 重要な操作の前にバックアップを取ることを推奨します

---

## 参考リンク

- Adminer公式サイト: https://www.adminer.org/
- PostgreSQL公式ドキュメント: https://www.postgresql.org/docs/
- プロジェクト仕様書: `doc/仕様書.md`
- データベース設計: `database/README.md`

---

## よくある質問

### Q: 複数のデータベースを管理できますか？

A: はい。ログイン画面でデータベース名を変更するか、左側のデータベース一覧から選択できます。

### Q: SQLクエリの結果をCSVでエクスポートできますか？

A: はい。「SQLコマンド」でクエリを実行後、「エクスポート」リンクからCSV形式でエクスポートできます。

### Q: テーブル間のリレーションを確認できますか？

A: はい。テーブルの「構造」タブで外部キー制約を確認できます。

### Q: バックアップを取るには？

A: 「エクスポート」機能を使用するか、PostgreSQLの`pg_dump`コマンドを使用してください:
```cmd
pg_dump -U attendance_user -d attendance_management -f backup.sql
```

---

最終更新: 2025-01-15

