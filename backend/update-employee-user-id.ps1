# 従業員プロファイルにuser_idを紐付けるスクリプト
# Adminerを使用する方法（パスワード不要）

param(
    [Parameter(Mandatory=$true)]
    [string]$UserId,
    
    [Parameter(Mandatory=$true)]
    [string]$EmployeeId,
    
    [Parameter(Mandatory=$true)]
    [string]$TenantId
)

Write-Host "=== 従業員プロファイルにuser_idを紐付け ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "以下の情報を使用します:" -ForegroundColor Yellow
Write-Host "  User ID: $UserId" -ForegroundColor Gray
Write-Host "  Employee ID: $EmployeeId" -ForegroundColor Gray
Write-Host "  Tenant ID: $TenantId" -ForegroundColor Gray
Write-Host ""

# 方法1: Adminerを使用（推奨）
Write-Host "【方法1】Adminerを使用する場合（推奨）:" -ForegroundColor Green
Write-Host "  1. ブラウザで http://localhost:8080/adminer-login.php にアクセス" -ForegroundColor Cyan
Write-Host "  2. データベースに接続（パスワードは自動入力されます）" -ForegroundColor Cyan
Write-Host "  3. SQLタブで以下のSQLを実行:" -ForegroundColor Cyan
Write-Host ""
Write-Host "UPDATE employee_profiles SET user_id = '$UserId' WHERE id = '$EmployeeId' AND tenant_id = '$TenantId';" -ForegroundColor Yellow
Write-Host ""

# 方法2: 環境変数からパスワードを取得してpsqlを使用
Write-Host "【方法2】psqlを使用する場合:" -ForegroundColor Green

# .envファイルからパスワードを読み込む
$envFile = Join-Path $PSScriptRoot ".env"
if (Test-Path $envFile) {
    Write-Host "  .envファイルからパスワードを読み込み中..." -ForegroundColor Yellow
    $envContent = Get-Content $envFile
    $dbPassword = ($envContent | Select-String "DB_PASSWORD=(.+)").Matches.Groups[1].Value
    
    if ($dbPassword) {
        Write-Host "  パスワードが見つかりました。" -ForegroundColor Green
        Write-Host ""
        Write-Host "  以下のコマンドを実行しますか？ (Y/N)" -ForegroundColor Cyan
        $confirm = Read-Host
        
        if ($confirm -eq "Y" -or $confirm -eq "y") {
            $env:PGPASSWORD = $dbPassword
            $sql = "UPDATE employee_profiles SET user_id = '$UserId' WHERE id = '$EmployeeId' AND tenant_id = '$TenantId';"
            
            try {
                psql -U attendance_user -d attendance_management -c $sql
                Write-Host ""
                Write-Host "✓ user_idの紐付けが完了しました!" -ForegroundColor Green
            } catch {
                Write-Host "✗ エラーが発生しました: $($_.Exception.Message)" -ForegroundColor Red
            }
        }
    } else {
        Write-Host "  .envファイルにDB_PASSWORDが見つかりませんでした。" -ForegroundColor Yellow
        Write-Host "  デフォルトパスワード 'attendance_password' を使用しますか？ (Y/N)" -ForegroundColor Cyan
        $useDefault = Read-Host
        
        if ($useDefault -eq "Y" -or $useDefault -eq "y") {
            $env:PGPASSWORD = "attendance_password"
            $sql = "UPDATE employee_profiles SET user_id = '$UserId' WHERE id = '$EmployeeId' AND tenant_id = '$TenantId';"
            
            try {
                psql -U attendance_user -d attendance_management -c $sql
                Write-Host ""
                Write-Host "✓ user_idの紐付けが完了しました!" -ForegroundColor Green
            } catch {
                Write-Host "✗ エラーが発生しました: $($_.Exception.Message)" -ForegroundColor Red
                Write-Host "  Adminerを使用する方法（方法1）を試してください。" -ForegroundColor Yellow
            }
        }
    }
} else {
    Write-Host "  .envファイルが見つかりませんでした。" -ForegroundColor Yellow
    Write-Host "  デフォルトパスワード 'attendance_password' を使用しますか？ (Y/N)" -ForegroundColor Cyan
    $useDefault = Read-Host
    
    if ($useDefault -eq "Y" -or $useDefault -eq "y") {
        $env:PGPASSWORD = "attendance_password"
        $sql = "UPDATE employee_profiles SET user_id = '$UserId' WHERE id = '$EmployeeId' AND tenant_id = '$TenantId';"
        
        try {
            psql -U attendance_user -d attendance_management -c $sql
            Write-Host ""
            Write-Host "✓ user_idの紐付けが完了しました!" -ForegroundColor Green
        } catch {
            Write-Host "✗ エラーが発生しました: $($_.Exception.Message)" -ForegroundColor Red
            Write-Host "  Adminerを使用する方法（方法1）を試してください。" -ForegroundColor Yellow
        }
    }
}

Write-Host ""
Write-Host "=== 完了 ===" -ForegroundColor Cyan

