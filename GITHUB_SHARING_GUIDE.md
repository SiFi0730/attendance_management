# GitHubでプロジェクトを共有する方法

## 📋 現在の状態

✅ **GitHubリポジトリは既に設定済みです**
- リポジトリURL: `https://github.com/SiFi0730/attendance_management.git`
- ブランチ: `main`
- 状態: すべての変更がコミット済み

## 🔒 プライベートリポジトリの共有方法

プロジェクトが**Private**に設定されている場合、以下の方法で共有できます：

### 方法1: コラボレーターを追加（推奨）

1. **GitHubリポジトリページにアクセス**
   ```
   https://github.com/SiFi0730/attendance_management
   ```

2. **Settings（設定）をクリック**
   - リポジトリページの上部メニューから「Settings」を選択

3. **Collaborators（コラボレーター）を選択**
   - 左サイドバーの「Collaborators」をクリック
   - 「Add people」ボタンをクリック

4. **共有したい人のGitHubユーザー名またはメールアドレスを入力**
   - 検索ボックスにユーザー名を入力
   - 該当するユーザーを選択

5. **権限を設定**
   - **Read**: 閲覧のみ（推奨）
   - **Write**: 編集可能
   - **Admin**: 管理者権限

6. **招待を送信**
   - 「Add [ユーザー名] to this repository」をクリック

### 方法2: 組織に移行して共有

1. **GitHub Organizationを作成**
   - GitHubの右上の「+」→「New organization」

2. **リポジトリを組織に移行**
   - リポジトリのSettings → General → Transfer ownership

3. **組織メンバーを追加**
   - 組織のSettings → Members → Add member

### 方法3: 一時的にPublicにする（非推奨）

⚠️ **注意**: 機密情報が含まれていないことを確認してください

1. **Settings → General → Danger Zone**
2. **「Change visibility」をクリック**
3. **「Change to public」を選択**

---

## 📤 最新の変更をプッシュする

もし最新の変更がまだプッシュされていない場合：

```bash
# 現在の状態を確認
git status

# 変更があればコミット
git add .
git commit -m "プロジェクト整理: ドキュメント統合とファイル整理"

# GitHubにプッシュ
git push origin main
```

---

## 🔐 セキュリティチェックリスト

共有前に確認すべき項目：

### ✅ 機密情報が含まれていないか確認

以下のファイルは`.gitignore`で除外されているはずですが、確認してください：

- ❌ `backend/.env` - データベース接続情報（**絶対にコミットしない**）
- ❌ `backend/vendor/` - Composer依存関係（自動生成）
- ❌ `cookies.txt` - 一時ファイル
- ❌ `.env` - 環境変数ファイル

### ✅ 確認方法

```bash
# .envファイルがコミットされていないか確認
git ls-files | findstr "\.env"

# vendorディレクトリがコミットされていないか確認
git ls-files | findstr "vendor"
```

もし見つかった場合は、履歴から削除する必要があります。

### ✅ 修正済み項目

以下の機密情報は既に修正済みです：

- ✅ README.mdから実際のパスワードを削除（プレースホルダーに置き換え）
- ✅ README.mdから実際のパスを削除（プレースホルダーに置き換え）
- ✅ ドキュメント内のパスワードをプレースホルダーに置き換え
- ✅ 開発用ファイルにセキュリティ警告を追加

詳細は`SECURITY_CHECKLIST.md`と`GITHUB_PUBLIC_CHECKLIST.md`を参照してください。

---

## 👥 共有後の手順（コラボレーター向け）

### 1. リポジトリをクローン

```bash
git clone https://github.com/SiFi0730/attendance_management.git
cd attendance_management
```

### 2. 環境変数ファイルを作成

```bash
# Windows
copy backend\.env.example backend\.env

# Linux/Mac
cp backend/.env.example backend/.env
```

### 3. `.env`ファイルを編集

データベース接続情報を設定してください。

### 4. Composer依存関係をインストール

```bash
cd backend
composer install
```

### 5. データベースをセットアップ

```bash
# Windows
database\setup-database.bat

# Linux/Mac
psql -U postgres -f database/schema.sql
```

### 6. 開発サーバーを起動

```bash
# Windows
start-local-server.bat

# Linux/Mac
cd backend/public
php -S localhost:8080 router.php
```

---

## 📝 README.mdの更新

共有する際は、`README.md`に以下を追加することを推奨します：

```markdown
## コントリビューション

このプロジェクトへの貢献を歓迎します。以下の手順に従ってください：

1. リポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/AmazingFeature`)
3. 変更をコミット (`git commit -m 'Add some AmazingFeature'`)
4. ブランチにプッシュ (`git push origin feature/AmazingFeature`)
5. プルリクエストを作成
```

---

## 🆘 トラブルシューティング

### 問題: プッシュ時に認証エラーが発生する

**解決方法**:
1. Personal Access Token (PAT) を使用
   - GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - `repo`スコープでトークンを生成
   - プッシュ時にパスワードの代わりにトークンを入力

2. SSH鍵を使用
   ```bash
   git remote set-url origin git@github.com:SiFi0730/attendance_management.git
   ```

### 問題: コラボレーターがリポジトリにアクセスできない

**確認事項**:
- 招待メールが送信されているか確認
- コラボレーターが招待を承認しているか確認
- リポジトリの権限設定を確認

---

## 📚 参考リンク

- [GitHub Collaborators ドキュメント](https://docs.github.com/ja/account-and-profile/setting-up-and-managing-your-github-user-account/managing-access-to-your-personal-repositories/inviting-collaborators-to-a-personal-repository)
- [GitHub Organization ドキュメント](https://docs.github.com/ja/organizations)
- [Personal Access Token の作成](https://docs.github.com/ja/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token)

---

最終更新: 2025-01-15

