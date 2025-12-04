# テスト用データベース作成ガイド

## 📋 概要

テスト用に複数のデータベースを作成する方法を説明します。

## 🗄️ 作成されるデータベース

以下の4つのテスト用データベースが作成されます：

1. `attendance_management_test`
2. `attendance_management_test1`
3. `attendance_management_test2`
4. `attendance_management_test3`

## 🚀 作成方法

### 方法1: SQLファイルを実行（推奨）

#### ステップ1: データベースを作成

```cmd
psql -U postgres -f database\create-test-databases.sql
```

postgresユーザーのパスワードを入力します。

#### ステップ2: スキーマを適用

```cmd
database\apply-schema-to-test-dbs.bat
```

または手動で各データベースに適用：

```cmd
psql -U attendance_user -d attendance_management_test -f database\schema.sql
psql -U attendance_user -d attendance_management_test -f database\rls_policies.sql
```

### 方法2: バッチスクリプトを実行

```cmd
database\create-test-databases.bat
```

### 方法3: PowerShellスクリプトを実行

```powershell
.\database\create-test-databases-simple.ps1
```

## ✅ 確認方法

### データベース一覧を確認

```cmd
psql -U postgres -c "\l" | findstr "attendance_management"
```

### 各データベースに接続して確認

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

## 🔧 各データベースの用途例

### attendance_management_test
- ユニットテスト用
- 基本的な機能テスト

### attendance_management_test1
- 統合テスト用
- APIテスト

### attendance_management_test2
- パフォーマンステスト用
- 大量データテスト

### attendance_management_test3
- E2Eテスト用
- エンドツーエンドテスト

## 📝 注意事項

1. **データベース作成にはスーパーユーザー権限が必要**
   - postgresユーザーで実行する必要があります

2. **スキーマの適用**
   - 各データベースに個別にスキーマを適用する必要があります

3. **テストデータ**
   - 必要に応じて `seed_data.sql` を各データベースに適用できます

## 🗑️ データベースの削除

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

## 🔗 関連ファイル

- `database/create-test-databases.sql` - データベース作成SQL
- `database/create-test-databases.bat` - バッチスクリプト
- `database/create-test-databases-simple.ps1` - PowerShellスクリプト
- `database/apply-schema-to-test-dbs.bat` - スキーマ適用スクリプト

---

最終更新: 2025-01-15

