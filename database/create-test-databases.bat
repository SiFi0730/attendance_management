@echo off
REM テスト用データベース作成スクリプト

echo ============================================
echo テスト用データベース作成
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
        echo スクリプトを中止します。
        pause
        exit /b 1
    )
)

echo.
echo PostgreSQLのpostgresユーザーのパスワードを入力してください:
echo.

REM テスト用データベース名のリスト
set DB_NAMES=attendance_management_test attendance_management_test1 attendance_management_test2 attendance_management_test3

REM 各データベースを作成
for %%db in (%DB_NAMES%) do (
    echo.
    echo [作成中] %%db データベースを作成中...
    
    REM データベースの作成
    psql -U postgres -c "CREATE DATABASE %%db;" 2>nul
    if %errorLevel% neq 0 (
        echo [スキップ] %%db は既に存在します。
    ) else (
        echo [OK] %%db を作成しました。
        
        REM ユーザーに権限を付与
        psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE %%db TO attendance_user;" 2>nul
        if %errorLevel% equ 0 (
            echo [OK] attendance_user に権限を付与しました。
        ) else (
            echo [警告] 権限の付与に失敗しました。
        )
    )
)

echo.
echo ============================================
echo スキーマを適用しますか？
echo ============================================
echo 各テストデータベースにスキーマを適用する場合は Y を入力してください。
set /p apply_schema=
if /i "%apply_schema%"=="Y" (
    echo.
    echo スキーマを適用中...
    
    for %%db in (%DB_NAMES%) do (
        echo.
        echo [適用中] %%db にスキーマを適用中...
        echo attendance_userのパスワードを入力してください: attendance_password
        
        cd %~dp0
        psql -U attendance_user -d %%db -f schema.sql
        if %errorLevel% equ 0 (
            echo [OK] %%db にスキーマを適用しました。
            
            REM RLSポリシーも適用
            psql -U attendance_user -d %%db -f rls_policies.sql
            if %errorLevel% equ 0 (
                echo [OK] %%db にRLSポリシーを適用しました。
            ) else (
                echo [警告] RLSポリシーの適用に失敗しました。
            )
        ) else (
            echo [エラー] %%db へのスキーマ適用に失敗しました。
        )
    )
) else (
    echo スキーマの適用をスキップしました。
)

echo.
echo ============================================
echo 作成されたデータベース一覧
echo ============================================
psql -U postgres -c "\l" | findstr "attendance_management"

echo.
echo ============================================
echo テストデータベース作成完了
echo ============================================
echo.
echo 作成されたデータベース:
for %%db in (%DB_NAMES%) do (
    echo   - %%db
)
echo.
echo 接続方法:
echo   psql -U attendance_user -d attendance_management_test
echo   psql -U attendance_user -d attendance_management_test1
echo   psql -U attendance_user -d attendance_management_test2
echo   psql -U attendance_user -d attendance_management_test3
echo.
pause

