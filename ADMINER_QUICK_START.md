# Adminer クイックスタートガイド

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
- パスワード: `attendance_password`
- データベース: `attendance_management`

### ステップ3: データベースを選択

左側のメニューから **attendance_management** をクリック

### ステップ4: テーブルを確認

20個のテーブルが表示されます。例えば:
- `users` - ユーザー一覧を確認
- `tenants` - テナント（企業）一覧を確認
- `employee_profiles` - 従業員一覧を確認

---

## 📊 よく使う操作

### データを確認する

1. テーブル名をクリック（例: `users`）
2. 「選択」タブでデータが表示される
3. 検索ボックスで絞り込み可能

### SQLを実行する

1. 上部メニューの「SQLコマンド」をクリック
2. SQLを入力（例: `SELECT * FROM users;`）
3. 「実行」ボタンをクリック

### データを追加する

1. テーブル名をクリック
2. 「新規アイテム」タブをクリック
3. フォームに入力して「保存」

---

## 💡 実践例: ユーザー一覧を確認

### 方法1: テーブルから確認

1. `users` テーブルをクリック
2. 「選択」タブで一覧を確認

### 方法2: SQLで確認

1. 「SQLコマンド」をクリック
2. 以下を入力:
   ```sql
   SELECT id, email, name, status 
   FROM users 
   WHERE deleted_at IS NULL;
   ```
3. 「実行」をクリック

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

## ⚠️ 注意事項

1. **開発環境専用**: 本番環境では使用しないでください
2. **データの変更は慎重に**: 削除・変更前にバックアップを推奨
3. **論理削除**: `deleted_at IS NULL` の条件を忘れずに

---

## 📚 詳細な使い方

より詳しい使い方は以下を参照:
- `doc/ADMINER_USAGE.md` - 詳細な使用方法ガイド

---

## 🆘 トラブルシューティング

### 接続できない
```cmd
# PostgreSQLサービスを確認
sc query postgresql-x64-18

# 起動していない場合
net start postgresql-x64-18
```

### パスワードが分からない
- パスワード: `attendance_password`
- `backend\.env` ファイルで確認可能

---

最終更新: 2025-01-15

