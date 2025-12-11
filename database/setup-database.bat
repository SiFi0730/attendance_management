@echo off
REM データベースセットアップスクリプト

echo ============================================
echo データベースセットアップ
echo ============================================
echo.

REM PostgreSQLサービスの確認
sc query postgresql-x64-18 | findstr "RUNNING" >nul
if %errorLevel% neq 0 (
    echo PostgreSQLサービスが起動していません。
    echo サービスを起動しますか？ (Y/N)
    set /p start_service=
    if /i "%start_service%"=="Y" (
        net start postgresql-x64-18
    ) else (
        echo セットアップを中止します。
        pause
        exit /b 1
    )
)

echo.
echo PostgreSQLのpostgresユーザーのパスワードを入力してください:
echo.

REM データベースの作成
echo [1/4] データベースを作成中...
psql -U postgres -c "CREATE DATABASE attendance_management;" 2>nul
if %errorLevel% neq 0 (
    echo データベースは既に存在するか、作成に失敗しました。
) else (
    echo [OK] データベースを作成しました。
)

REM ユーザーの作成
echo [2/4] ユーザーを作成中...
echo データベースユーザーのパスワードを入力してください:
set /p db_password=
psql -U postgres -c "CREATE USER attendance_user WITH PASSWORD '%db_password%';" 2>nul
if %errorLevel% neq 0 (
    echo ユーザーは既に存在するか、作成に失敗しました。
) else (
    echo [OK] ユーザーを作成しました。
)

REM 権限の付与
echo [3/4] 権限を付与中...
psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE attendance_management TO attendance_user;"
if %errorLevel% neq 0 (
    echo [エラー] 権限の付与に失敗しました。
    pause
    exit /b 1
) else (
    echo [OK] 権限を付与しました。
)

REM スキーマの適用
echo [4/4] スキーマを適用中...
echo attendance_userのパスワードを入力してください: <上記で設定したパスワード>
cd %~dp0
psql -U attendance_user -d attendance_management -f schema.sql
if %errorLevel% neq 0 (
    echo [エラー] スキーマの適用に失敗しました。
    pause
    exit /b 1
) else (
    echo [OK] スキーマを適用しました。
)

REM RLSポリシーの適用
echo RLSポリシーを適用中...
psql -U attendance_user -d attendance_management -f rls_policies.sql
if %errorLevel% neq 0 (
    echo [警告] RLSポリシーの適用に失敗しました。
) else (
    echo [OK] RLSポリシーを適用しました。
)

REM シードデータの投入
echo シードデータを投入しますか？ (Y/N)
set /p seed_data=
if /i "%seed_data%"=="Y" (
    psql -U attendance_user -d attendance_management -f seed_data.sql
    if %errorLevel% neq 0 (
        echo [警告] シードデータの投入に失敗しました。
    ) else (
        echo [OK] シードデータを投入しました。
    )
)

echo.
echo ============================================
echo データベースセットアップ完了
echo ============================================
echo.
pause

