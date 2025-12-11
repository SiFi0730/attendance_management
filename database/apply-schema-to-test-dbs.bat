@echo off
REM テスト用データベースにスキーマを適用するスクリプト

echo ============================================
echo テスト用データベースにスキーマを適用
echo ============================================
echo.

set DB_NAMES=attendance_management_test attendance_management_test1 attendance_management_test2 attendance_management_test3

echo attendance_userのパスワードを入力してください: <データベース作成時に設定したパスワード>
echo.

for %%db in (%DB_NAMES%) do (
    echo.
    echo [適用中] %%db にスキーマを適用中...
    
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

echo.
echo ============================================
echo スキーマ適用完了
echo ============================================
echo.
pause

