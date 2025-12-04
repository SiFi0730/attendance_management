@echo off
REM スタンドアロン開発環境セットアップスクリプト（XAMPP不使用）

echo ============================================
echo 勤怠管理システム スタンドアロン開発環境セットアップ
echo ============================================
echo.

REM 管理者権限の確認
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [警告] 一部の操作には管理者権限が必要です。
    echo.
)

REM PHPの確認
echo [1/7] PHPの確認中...
php -v >nul 2>&1
if %errorLevel% neq 0 (
    echo [エラー] PHPがインストールされていません。
    echo.
    echo PHPのインストール手順:
    echo 1. https://windows.php.net/download/ からPHP 8.2 Thread Safe x64をダウンロード
    echo 2. C:\php に解凍
    echo 3. 環境変数PATHに C:\php を追加
    echo 4. C:\php\php.ini-development を C:\php\php.ini にコピー
    echo 5. php.iniで以下の拡張を有効化:
    echo    extension=pdo_pgsql
    echo    extension=pgsql
    echo    extension=mbstring
    echo    extension=curl
    echo    extension=openssl
    echo.
    pause
    exit /b 1
) else (
    echo [OK] PHPがインストールされています。
    php -v
    echo.
    
    REM PHP拡張の確認
    echo PHP拡張の確認中...
    php -m | findstr "pdo_pgsql" >nul
    if %errorLevel% neq 0 (
        echo [警告] pdo_pgsql拡張が読み込まれていません。
        echo php.iniで extension=pdo_pgsql を有効化してください。
    ) else (
        echo [OK] pdo_pgsql拡張が読み込まれています。
    )
)
echo.

REM PostgreSQLの確認
echo [2/7] PostgreSQLの確認中...
psql --version >nul 2>&1
if %errorLevel% neq 0 (
    echo [エラー] PostgreSQLがインストールされていません。
    echo.
    echo PostgreSQLのインストール手順:
    echo 1. https://www.postgresql.org/download/windows/ からダウンロード
    echo 2. インストーラーを実行してインストール
    echo 3. 環境変数PATHに C:\Program Files\PostgreSQL\18\bin を追加
    echo.
    pause
    exit /b 1
) else (
    echo [OK] PostgreSQLがインストールされています。
    psql --version
)
echo.

REM Composerの確認
echo [3/7] Composerの確認中...
composer --version >nul 2>&1
if %errorLevel% neq 0 (
    echo [エラー] Composerがインストールされていません。
    echo.
    echo Composerのインストール手順:
    echo 1. https://getcomposer.org/download/ からComposer-Setup.exeをダウンロード
    echo 2. インストーラーを実行してインストール
    echo.
    pause
    exit /b 1
) else (
    echo [OK] Composerがインストールされています。
    composer --version
)
echo.

REM PostgreSQLサービスの確認
echo [4/7] PostgreSQLサービスの確認中...
sc query postgresql-x64-18 >nul 2>&1
if %errorLevel% neq 0 (
    echo [警告] PostgreSQLサービスが見つかりません。
    echo サービス名が異なる可能性があります。
    echo 手動でPostgreSQLサービスを起動してください。
) else (
    sc query postgresql-x64-18 | findstr "RUNNING" >nul
    if %errorLevel% neq 0 (
        echo [警告] PostgreSQLサービスが起動していません。
        echo サービスを起動しますか？ (Y/N)
        set /p start_service=
        if /i "%start_service%"=="Y" (
            net start postgresql-x64-18
            if %errorLevel% equ 0 (
                echo [OK] PostgreSQLサービスを起動しました。
            ) else (
                echo [エラー] サービスの起動に失敗しました。管理者権限が必要な可能性があります。
            )
        )
    ) else (
        echo [OK] PostgreSQLサービスが起動しています。
    )
)
echo.

REM .envファイルの確認
echo [5/7] 環境変数ファイルの確認中...
if not exist "backend\.env" (
    echo [警告] backend\.env ファイルが見つかりません。
    if exist "backend\.env.example" (
        echo backend\.env.example をコピーして backend\.env を作成します。
        copy backend\.env.example backend\.env
        echo [OK] backend\.env を作成しました。
        echo.
        echo [重要] backend\.env を編集して、データベース接続情報を確認してください。
    ) else (
        echo [エラー] backend\.env.example も見つかりません。
    )
) else (
    echo [OK] backend\.env が存在します。
)
echo.

REM データベースのセットアップ確認
echo [6/7] データベースのセットアップ確認中...
echo データベースとユーザーが作成されているか確認します...
psql -U postgres -c "\l" 2>nul | findstr "attendance_management" >nul
if %errorLevel% neq 0 (
    echo [警告] データベース 'attendance_management' が見つかりません。
    echo.
    echo データベースをセットアップしますか？ (Y/N)
    set /p setup_db=
    if /i "%setup_db%"=="Y" (
        echo.
        echo PostgreSQLのpostgresユーザーのパスワードを入力してください:
        psql -U postgres -c "CREATE DATABASE attendance_management;" 2>nul
        if %errorLevel% neq 0 (
            echo [警告] データベースは既に存在するか、作成に失敗しました。
        ) else (
            echo [OK] データベースを作成しました。
        )
        
        psql -U postgres -c "CREATE USER attendance_user WITH PASSWORD 'attendance_password';" 2>nul
        if %errorLevel% neq 0 (
            echo [警告] ユーザーは既に存在するか、作成に失敗しました。
        ) else (
            echo [OK] ユーザーを作成しました。
        )
        
        psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE attendance_management TO attendance_user;"
        if %errorLevel% equ 0 (
            echo [OK] 権限を付与しました。
        )
    )
) else (
    echo [OK] データベース 'attendance_management' が存在します。
)
echo.

REM Composer依存関係のインストール
echo [7/7] Composer依存関係のインストール中...
cd backend
if exist "composer.json" (
    composer install
    if %errorLevel% neq 0 (
        echo [警告] Composer依存関係のインストールに失敗しました。
    ) else (
        echo [OK] Composer依存関係をインストールしました。
    )
) else (
    echo [警告] composer.json が見つかりません。
)
cd ..
echo.

echo ============================================
echo セットアップ完了
echo ============================================
echo.
echo 次のステップ:
echo 1. backend\.env を編集してデータベース接続情報を確認
echo 2. データベーススキーマを適用:
echo    database\setup-database.bat
echo 3. 開発サーバーを起動:
echo    start-local-server.bat
echo 4. ブラウザで http://localhost:8080 にアクセス
echo.
pause

