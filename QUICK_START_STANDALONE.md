# クイックスタートガイド

このガイドでは、最短で開発環境をセットアップする手順を説明します。

## 最短セットアップ手順

### 1. 必要なソフトウェアのインストール

#### PHP

1. **ダウンロード**
   - https://windows.php.net/download/
   - **PHP 8.2 Thread Safe (TS) x64** ZIP形式をダウンロード

2. **解凍・配置**
   - `C:\php` に解凍

3. **環境変数に追加**
   - システムの環境変数PATHに `C:\php` を追加

4. **php.iniの設定**
   ```cmd
   cd C:\php
   copy php.ini-development php.ini
   ```
   - `php.ini` を開いて以下を有効化：
     ```ini
     extension=pdo_pgsql
     extension=pgsql
     extension=mbstring
     extension=curl
     extension=openssl
     date.timezone = Asia/Tokyo
     ```

5. **確認**
   ```cmd
   php -v
   php -m | findstr pdo_pgsql
   ```

#### PostgreSQL

1. **ダウンロード**
   - https://www.postgresql.org/download/windows/
   - PostgreSQL 18をダウンロード

2. **インストール**
   - デフォルト設定でインストール
   - postgresユーザーのパスワードを設定

3. **環境変数に追加**
   - PATHに `C:\Program Files\PostgreSQL\18\bin` を追加

4. **サービス起動**
   ```cmd
   net start postgresql-x64-18
   ```

5. **確認**
   ```cmd
   psql --version
   ```

#### Composer

1. **ダウンロード**
   - https://getcomposer.org/download/
   - `Composer-Setup.exe` をダウンロード

2. **インストール**
   - PHPのパス: `C:\php\php.exe` を指定

3. **確認**
   ```cmd
   composer --version
   ```

### 2. 自動セットアップスクリプトの実行

```cmd
setup-standalone-dev.bat
```

このスクリプトが以下を自動確認・実行します：
- PHP/PostgreSQL/Composerの確認
- 環境変数ファイルの作成
- PostgreSQLサービスの起動確認
- データベースの作成（必要に応じて）

### 3. データベースのセットアップ

```cmd
database\setup-database.bat
```

このスクリプトが以下を実行します：
- データベースとユーザーの作成
- スキーマの適用
- RLSポリシーの適用
- シードデータの投入（オプション）

### 4. 開発サーバーの起動

```cmd
start-local-server.bat
```

または手動で：

```cmd
cd backend\public
php -S localhost:8080
```

### 5. 動作確認

ブラウザで http://localhost:8080 にアクセス

データベース接続成功メッセージが表示されればOKです！

## トラブルシューティング

### PHPが見つからない

1. **環境変数の確認**
   ```cmd
   echo %PATH%
   ```
   - `C:\php` が含まれているか確認

2. **コマンドプロンプトの再起動**
   - 環境変数を変更した場合は、コマンドプロンプトを再起動

### PostgreSQL拡張が読み込まれない

1. **php.iniの場所を確認**
   ```cmd
   php --ini
   ```

2. **拡張ファイルの存在確認**
   - `C:\php\ext\php_pdo_pgsql.dll` が存在するか確認

3. **php.iniの設定確認**
   ```ini
   extension_dir = "ext"
   extension=pdo_pgsql
   extension=pgsql
   ```

### PostgreSQL接続エラー

1. **サービスが起動しているか確認**
   ```cmd
   net start postgresql-x64-18
   ```

2. **接続情報の確認**
   - `backend\.env` の設定を確認：
     ```env
     DB_HOST=localhost
     DB_PORT=5432
     DB_NAME=attendance_management
     DB_USER=attendance_user
     DB_PASSWORD=attendance_password
     ```

### Composerエラー

1. **PHPのパス確認**
   ```cmd
   where php
   ```

2. **キャッシュのクリア**
   ```cmd
   composer clear-cache
   ```

## 詳細情報

詳細なセットアップ手順は `README_STANDALONE_DEV.md` を参照してください。

