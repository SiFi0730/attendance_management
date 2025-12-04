# 開発環境セットアップガイド

このガイドでは、PHP、PostgreSQL、Composerを個別にインストールして開発環境を構築する方法を説明します。

## 前提条件

- Windows 10/11
- 管理者権限
- インターネット接続

## セットアップ手順

### 1. PHPのインストール

#### ステップ1: PHPをダウンロード

1. **PHP公式サイトからダウンロード**
   - https://windows.php.net/download/
   - **PHP 8.2 Thread Safe (TS) x64** を選択
   - ZIP形式をダウンロード

#### ステップ2: PHPを解凍・配置

1. **解凍先を決定**
   - 推奨: `C:\php`
   - または: `C:\Program Files\php`

2. **ZIPファイルを解凍**
   - ダウンロードしたZIPファイルを解凍
   - 解凍したフォルダ内のファイルを `C:\php` に配置

#### ステップ3: 環境変数の設定

1. **システムの環境変数を開く**
   - Windowsキー + R → `sysdm.cpl` → Enter
   - 「詳細設定」タブ → 「環境変数」ボタン

2. **PATHに追加**
   - 「システム環境変数」の「Path」を選択 → 「編集」
   - 「新規」をクリック → `C:\php` を追加
   - 「OK」で保存

3. **確認**
   ```cmd
   php -v
   ```

#### ステップ4: php.iniの設定

1. **php.iniファイルの作成**
   ```cmd
   cd C:\php
   copy php.ini-development php.ini
   ```

2. **php.iniを編集**
   - `C:\php\php.ini` をメモ帳で開く
   - 以下の行のコメント（`;`）を外す：
     ```ini
     extension_dir = "ext"
     extension=pdo_pgsql
     extension=pgsql
     extension=mbstring
     extension=curl
     extension=openssl
     extension=fileinfo
     extension=gd
     ```
   - タイムゾーンを設定：
     ```ini
     date.timezone = Asia/Tokyo
     ```

3. **確認**
   ```cmd
   php -m
   ```
   - `pdo_pgsql` と `pgsql` が表示されればOK

### 2. PostgreSQLのインストール

#### ステップ1: PostgreSQLをダウンロード

1. **PostgreSQL公式サイトからダウンロード**
   - https://www.postgresql.org/download/windows/
   - 「Download the installer」をクリック
   - PostgreSQL 18を選択

#### ステップ2: インストール

1. **インストーラーを実行**
   - インストール先: `C:\Program Files\PostgreSQL\18`（デフォルト）
   - ポート: 5432（デフォルト）
   - ロケール: Japanese, Japan（オプション）

2. **スーパーユーザー（postgres）のパスワードを設定**
   - 覚えやすいパスワードを設定（後で使用します）

#### ステップ3: 環境変数の設定

1. **PATHに追加**
   - `C:\Program Files\PostgreSQL\18\bin` をPATHに追加

2. **確認**
   ```cmd
   psql --version
   ```

#### ステップ4: PostgreSQLサービスの起動

1. **サービスを起動**
   ```cmd
   net start postgresql-x64-18
   ```

2. **確認**
   ```cmd
   sc query postgresql-x64-18
   ```

### 3. Composerのインストール

#### ステップ1: Composerをダウンロード

1. **Composer公式サイトからダウンロード**
   - https://getcomposer.org/download/
   - 「Composer-Setup.exe」をダウンロード

#### ステップ2: インストール

1. **インストーラーを実行**
   - PHPのパス: `C:\php\php.exe` を指定
   - インストール先: デフォルト（`C:\ProgramData\ComposerSetup\bin`）

2. **確認**
   ```cmd
   composer --version
   ```

### 4. データベースのセットアップ

#### ステップ1: データベースとユーザーの作成

1. **PostgreSQLに接続**
   ```cmd
   psql -U postgres
   ```
   - インストール時に設定したpostgresユーザーのパスワードを入力

2. **データベースとユーザーを作成**
   ```sql
   CREATE DATABASE attendance_management;
   CREATE USER attendance_user WITH PASSWORD 'attendance_password';
   GRANT ALL PRIVILEGES ON DATABASE attendance_management TO attendance_user;
   \q
   ```

#### ステップ2: スキーマの適用

```cmd
psql -U attendance_user -d attendance_management -f database\schema.sql
```
- パスワード: `attendance_password`

#### ステップ3: RLSポリシーの適用

```cmd
psql -U attendance_user -d attendance_management -f database\rls_policies.sql
```

#### ステップ4: シードデータの投入（オプション）

```cmd
psql -U attendance_user -d attendance_management -f database\seed_data.sql
```

### 5. プロジェクトの設定

#### ステップ1: 環境変数ファイルの作成

```cmd
copy backend\.env.example backend\.env
```

#### ステップ2: .envファイルの編集

`backend\.env` を開いて、以下のように設定：

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=attendance_management
DB_USER=attendance_user
DB_PASSWORD=attendance_password

APP_ENV=development
APP_DEBUG=true
APP_TIMEZONE=Asia/Tokyo

SESSION_DRIVER=file
SESSION_LIFETIME=1440

LOG_LEVEL=debug
```

#### ステップ3: Composer依存関係のインストール

```cmd
cd backend
composer install
```

### 6. 開発サーバーの起動

#### 方法1: 起動スクリプトを使用（推奨）

```cmd
start-local-server.bat
```

#### 方法2: 手動で起動

```cmd
cd backend\public
php -S localhost:8080
```

### 7. 動作確認

ブラウザで http://localhost:8080 にアクセス

データベース接続成功メッセージが表示されればOKです！

## よく使うコマンド

### PHP

```cmd
# PHPバージョン確認
php -v

# インストール済み拡張の確認
php -m

# PHP設定の確認
php --ini

# 開発サーバーの起動
cd backend\public
php -S localhost:8080
```

### PostgreSQL

```cmd
# PostgreSQLバージョン確認
psql --version

# データベースに接続
psql -U attendance_user -d attendance_management

# サービス起動
net start postgresql-x64-18

# サービス停止
net stop postgresql-x64-18

# テーブル一覧を表示
psql -U attendance_user -d attendance_management -c "\dt"
```

### Composer

```cmd
# 依存関係のインストール
cd backend
composer install

# 依存関係の更新
composer update

# キャッシュのクリア
composer clear-cache
```

## トラブルシューティング

### PHPが見つからない

1. **環境変数の確認**
   ```cmd
   echo %PATH%
   ```
   - `C:\php` が含まれているか確認

2. **再起動**
   - 環境変数を変更した場合は、コマンドプロンプトを再起動

### PostgreSQL拡張が読み込まれない

1. **php.iniの確認**
   ```cmd
   php --ini
   ```
   - 読み込まれているphp.iniのパスを確認

2. **拡張の有効化確認**
   - `C:\php\php.ini` で以下が有効になっているか確認：
     ```ini
     extension=pdo_pgsql
     extension=pgsql
     ```

3. **拡張ファイルの存在確認**
   - `C:\php\ext\php_pdo_pgsql.dll` が存在するか確認
   - 存在しない場合は、PHP ZIPに含まれていない可能性があります

### PostgreSQL接続エラー

1. **サービスが起動しているか確認**
   ```cmd
   sc query postgresql-x64-18
   ```

2. **ポート5432が使用されているか確認**
   ```cmd
   netstat -an | findstr 5432
   ```

3. **pg_hba.confの確認**
   - `C:\Program Files\PostgreSQL\18\data\pg_hba.conf` を開く
   - 以下の行が有効になっているか確認：
     ```
     host    all             all             127.0.0.1/32            md5
     ```
   - 変更後はPostgreSQLサービスを再起動

### Composerエラー

1. **PHPのパスが正しいか確認**
   ```cmd
   where php
   ```

2. **Composerの再インストール**
   - Composer-Setup.exeを再実行

## 開発の進め方

### コードの編集

1. **プロジェクト構造**
   ```
   backend/
   ├── public/          # Webサーバーのドキュメントルート
   │   └── index.php    # エントリーポイント
   ├── src/             # アプリケーションコード
   ├── .env             # 環境変数
   └── composer.json    # 依存関係
   ```

2. **コード変更の反映**
   - PHP組み込みサーバーは自動的に変更を検知
   - ブラウザをリロードするだけ

### データベースの変更

1. **スキーマの変更**
   - `database/schema.sql` を編集
   - 手動でSQLを実行：
     ```cmd
     psql -U attendance_user -d attendance_management -f database\schema.sql
     ```

2. **データの確認**
   ```cmd
     psql -U attendance_user -d attendance_management
     \dt
     SELECT * FROM tenants;
   ```

## パフォーマンス最適化（オプション）

### OPcacheの有効化

1. **php.iniで有効化**
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=10000
   ```

### PostgreSQLの設定調整

1. **postgresql.confの編集**
   - `C:\Program Files\PostgreSQL\18\data\postgresql.conf`
   - 開発環境向けの設定を調整

## 次のステップ

1. APIエンドポイントの実装
2. 認証・認可機能の実装
3. 各サービスの実装（Identity, Organization, Attendance等）

---

最終更新: 2025-01-15

