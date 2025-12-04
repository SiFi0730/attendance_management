# GitHubでプロジェクトを共有する手順

## ステップ1: GitHubでリポジトリを作成

1. [GitHub](https://github.com)にログイン
2. 右上の「+」ボタンをクリック → 「New repository」を選択
3. リポジトリ情報を入力：
   - **Repository name**: `attendance-management`（任意の名前）
   - **Description**: 「勤怠管理システム」（任意）
   - **Visibility**: 
     - **Public**: 誰でも見られる（オープンソース）
     - **Private**: 自分と招待した人のみ（推奨）
   - **Initialize this repository with**: チェックを外す（既存のリポジトリがあるため）
4. 「Create repository」をクリック

## ステップ2: リモートリポジトリを追加

GitHubでリポジトリを作成すると、URLが表示されます。以下のコマンドを実行してください：

```bash
# HTTPSの場合（推奨）
git remote add origin https://github.com/あなたのユーザー名/attendance-management.git

# またはSSHの場合（SSH鍵を設定済みの場合）
git remote add origin git@github.com:あなたのユーザー名/attendance-management.git
```

## ステップ3: ファイルをコミット

まず、すべての変更をステージングしてコミットします：

```bash
# すべてのファイルを追加
git add .

# 初回コミット
git commit -m "Initial commit: 勤怠管理システム"
```

## ステップ4: GitHubにプッシュ

```bash
# メインブランチをプッシュ（GitHubのデフォルトブランチ名に合わせる）
git branch -M main
git push -u origin main

# または、masterブランチのままの場合
git push -u origin master
```

## ステップ5: 確認

GitHubのリポジトリページをリロードして、ファイルがアップロードされているか確認してください。

---

## トラブルシューティング

### 認証エラーが発生する場合

GitHubは2021年8月以降、パスワード認証を廃止しています。以下のいずれかの方法を使用してください：

#### 方法1: Personal Access Token（PAT）を使用

1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. 「Generate new token (classic)」をクリック
3. スコープで `repo` にチェック
4. トークンを生成してコピー
5. プッシュ時にパスワードの代わりにトークンを入力

#### 方法2: GitHub CLIを使用

```bash
# GitHub CLIをインストール（未インストールの場合）
winget install GitHub.cli

# 認証
gh auth login

# その後、通常通りプッシュ
git push -u origin main
```

#### 方法3: SSH鍵を使用

1. SSH鍵を生成（未設定の場合）:
```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
```

2. 公開鍵をGitHubに登録:
   - `~/.ssh/id_ed25519.pub` の内容をコピー
   - GitHub → Settings → SSH and GPG keys → New SSH key

3. リモートURLをSSHに変更:
```bash
git remote set-url origin git@github.com:あなたのユーザー名/attendance-management.git
```

---

## 今後の作業フロー

### 変更をアップロードする場合

```bash
# 変更を確認
git status

# 変更を追加
git add .

# コミット
git commit -m "変更内容の説明"

# GitHubにプッシュ
git push
```

### 他のマシンでクローンする場合

```bash
git clone https://github.com/あなたのユーザー名/attendance-management.git
cd attendance-management
```

---

## 注意事項

- `.env`ファイルやパスワードなどの機密情報は`.gitignore`に含まれているため、GitHubにアップロードされません
- `vendor/`ディレクトリも除外されているため、他の人は`composer install`を実行する必要があります
- データベースの接続情報などは、`.env.example`ファイルを作成して共有することを推奨します

