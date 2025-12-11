# 勤怠管理システム

SaaS形式の勤怠管理ウェブアプリケーション

## 概要

マルチテナント対応の勤怠打刻、集計、可視化、CSV入出力、企業・従業員管理機能を提供するシステムです。

## 機能

- 出勤/退勤/休憩の打刻、修正申請、承認ワークフロー
- 企業側の従業員登録・部署/雇用区分管理
- 営業時間/就業ルールの登録・適用（フレックス/シフト/固定など）
- 勤怠のガントチャート可視化（日/週/月・個人/チーム）
- CSVインポート/エクスポート
- 社労士の招待/権限付与

## 技術スタック

- **フロントエンド**: HTML, CSS, JavaScript
- **バックエンド**: PHP 8.2
- **データベース**: PostgreSQL 18
- **依存関係管理**: Composer

## セットアップ

### 前提条件

- Windows 10/11
- 管理者権限
- インタネット接続

### 必要なソフトウェア

1. **PHP 8.2**
   - https://windows.php.net/download/
   - PHP 8.2 Thread Safe (TS) x64 ZIP形式をダウンロード

2. **PostgreSQL 18**
   - https://www.postgresql.org/download/windows/
   - PostgreSQL 18をダウンロード・インストール

3. **Composer**
   - https://getcomposer.org/download/
   - Composer-Setup.exeをダウンロード・インストール

---

## クイックスタート

最短で開発環境をセットアップする手順です。

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

---

## 詳細なセットアップ手順

より詳細な手順が必要な場合は、以下を参照してください。

### PHPの詳細インストール手順

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

### PostgreSQLの詳細インストール手順

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

### Composerの詳細インストール手順

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

### データベースの詳細セットアップ

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
- パスワード: データベース作成時に設定したパスワードを入力してください

#### ステップ3: RLSポリシーの適用

```cmd
psql -U attendance_user -d attendance_management -f database\rls_policies.sql
```

#### ステップ4: シードデータの投入（オプション）

```cmd
psql -U attendance_user -d attendance_management -f database\seed_data.sql
```

### プロジェクトの設定

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
DB_PASSWORD=<データベース作成時に設定したパスワード>

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

---

## 開発サーバーの起動

### 方法1: バッチスクリプトを使用（推奨）

1. **コマンドプロンプトまたはPowerShellを開く**

2. **プロジェクトディレクトリに移動**
   ```cmd
   cd <プロジェクトディレクトリのパス>
   ```

3. **起動スクリプトを実行**
   ```cmd
   start-local-server.bat
   ```

   または、PowerShellの場合：
   ```powershell
   .\start-local-server.bat
   ```

4. **サーバーが起動したら、新しいウィンドウが開きます**
   - サーバーURL: `http://localhost:8080`
   - 停止するには `Ctrl+C` を押してください

### 方法2: 手動で起動

1. **コマンドプロンプトまたはPowerShellを開く**

2. **プロジェクトディレクトリに移動**

3. **backend/publicディレクトリに移動**
   ```cmd
   cd backend\public
   ```

4. **PHPサーバーを起動**
   ```cmd
   php -S localhost:8080
   ```

5. **サーバーが起動したら、以下のメッセージが表示されます**
   ```
   PHP 8.2.12 Development Server (http://localhost:8080) started
   ```

### 方法3: バックグラウンドで起動（PowerShell）

```powershell
cd backend\public
Start-Process powershell -ArgumentList "-NoExit", "-Command", "php -S localhost:8080"
```

### サーバー起動確認

#### 1. ポート8080が使用されているか確認

```cmd
netstat -an | findstr :8080
```

**期待される出力:**
```
TCP    [::1]:8080    [::]:0    LISTENING
```

#### 2. ブラウザでアクセス

以下のURLにアクセスして確認：

- **開発環境確認**: http://localhost:8080/index.php
- **APIエンドポイント**: http://localhost:8080/api.php
- **ログインページ**: http://localhost:8080/index.html
- **会員登録ページ**: http://localhost:8080/pages/register.html

#### 3. API動作確認

ブラウザの開発者ツール（F12）のNetworkタブで、APIリクエストが正常に送信されているか確認してください。

---

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

---

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

3. **ポート5432が使用されているか確認**
   ```cmd
   netstat -an | findstr 5432
   ```

4. **pg_hba.confの確認**
   - `C:\Program Files\PostgreSQL\18\data\pg_hba.conf` を開く
   - 以下の行が有効になっているか確認：
     ```
     host    all             all             127.0.0.1/32            md5
     ```
   - 変更後はPostgreSQLサービスを再起動

### Composerエラー

1. **PHPのパス確認**
   ```cmd
   where php
   ```

2. **キャッシュのクリア**
   ```cmd
   composer clear-cache
   ```

### ポート8080が既に使用されている

**エラーメッセージ:**
```
Address already in use
```

**解決方法:**
1. 別のポートを使用する：
   ```cmd
   php -S localhost:8081
   ```
2. または、使用中のプロセスを終了する：
   ```cmd
   netstat -ano | findstr :8080
   taskkill /PID <プロセスID> /F
   ```

### データベース接続エラー

**エラーメッセージ:**
```
データベース接続に失敗しました
```

**解決方法:**
1. PostgreSQLサービスが起動しているか確認：
   ```cmd
   sc query postgresql-x64-18
   ```

2. 起動していない場合：
   ```cmd
   net start postgresql-x64-18
   ```

3. `backend/.env` ファイルの設定を確認

### ネットワークエラー（CORS）

**症状:**
- ブラウザで「ネットワークエラーが発生しました」と表示される
- OPTIONSリクエストは成功するが、POSTリクエストが失敗する

**解決方法:**
1. サーバーが正しく起動しているか確認
2. ブラウザの開発者ツール（F12）のConsoleタブでエラーを確認
3. Networkタブでリクエストの詳細を確認

---

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

---

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

---

## プロジェクト構成

```
.
├── backend/              # バックエンド（PHP）
│   ├── public/          # Webサーバーのドキュメントルート
│   ├── src/             # アプリケーションコード
│   └── composer.json   # PHP依存関係
├── database/            # データベース関連
│   ├── schema.sql      # スキーマ定義
│   ├── rls_policies.sql # RLSポリシー
│   └── seed_data.sql   # シードデータ
├── doc/                 # ドキュメント
│   ├── 仕様書.md       # システム仕様書
│   └── 実装手順書.md   # 実装手順書
├── pages/               # フロントエンドページ
├── styles/              # CSS
└── scripts/             # JavaScript
```

---

## ドキュメント

- **仕様書**: `doc/仕様書.md`
- **実装手順書**: `doc/実装手順書.md`
- **実装状況**: `doc/IMPLEMENTATION_STATUS.md`
- **未実装機能リスト**: `doc/未実装機能リスト.md`
- **データベース設計**: `database/README.md`
- **APIドキュメント**: `backend/API_DOCUMENTATION.md`
- **Adminer使用方法**: `doc/ADMINER_USAGE.md`

---

## よくある質問

### Q: サーバーを停止するには？

A: サーバーが起動しているウィンドウで `Ctrl+C` を押してください。

### Q: 複数のサーバーを起動できますか？

A: はい、異なるポートを使用すれば可能です：
```cmd
php -S localhost:8080  # サーバー1
php -S localhost:8081  # サーバー2
```

### Q: サーバーをバックグラウンドで起動したい

A: PowerShellを使用：
```powershell
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd backend\public; php -S localhost:8080"
```

---

## ライセンス

（ライセンス情報をここに記載）

---

最終更新: 2025-01-15
