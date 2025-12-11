# 打刻テスト用セットアップスクリプト

$baseUrl = "http://localhost:8080/api.php"

Write-Host "=== 打刻テスト用セットアップ ===" -ForegroundColor Cyan
Write-Host ""

# 1. ログイン
# 注意: テスト用の認証情報です。本番環境では使用しないでください。
Write-Host "1. ログイン中..." -ForegroundColor Yellow
$loginBody = @{
    email = "test@example.com"  # テスト用メールアドレス
    password = "Test1234!"      # テスト用パスワード（本番では使用不可）
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody `
        -SessionVariable session
    
    $userId = $loginResponse.data.user.id
    $tenantId = $loginResponse.data.user.tenant_id
    
    Write-Host "✓ ログイン成功!" -ForegroundColor Green
    Write-Host "  User ID: $userId" -ForegroundColor Gray
    Write-Host "  Tenant ID: $tenantId" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "✗ ログイン失敗: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "  エラー詳細: $responseBody" -ForegroundColor Red
    }
    exit 1
}

# 2. 現在のユーザー情報を取得
Write-Host "2. ユーザー情報取得中..." -ForegroundColor Yellow
try {
    $meResponse = Invoke-RestMethod -Uri "$baseUrl/auth/me" `
        -Method GET `
        -WebSession $session
    
    Write-Host "✓ ユーザー情報取得成功!" -ForegroundColor Green
    Write-Host "  名前: $($meResponse.data.user.name)" -ForegroundColor Gray
    Write-Host "  メール: $($meResponse.data.user.email)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "✗ ユーザー情報取得失敗: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# 3. 従業員プロファイルを確認
Write-Host "3. 従業員プロファイル確認中..." -ForegroundColor Yellow
try {
    $employeesResponse = Invoke-RestMethod -Uri "$baseUrl/employees?limit=100" `
        -Method GET `
        -WebSession $session
    
    # 現在のユーザーに紐付いた従業員プロファイルを検索
    $myEmployee = $employeesResponse.data.items | Where-Object { $_.user_id -eq $userId }
    
    if ($myEmployee) {
        Write-Host "✓ 従業員プロファイルが見つかりました!" -ForegroundColor Green
        Write-Host "  Employee ID: $($myEmployee.id)" -ForegroundColor Gray
        Write-Host "  従業員コード: $($myEmployee.employee_code)" -ForegroundColor Gray
        Write-Host ""
        Write-Host "セットアップ完了! 打刻APIをテストできます。" -ForegroundColor Green
        Write-Host ""
        Write-Host "次のコマンドで打刻をテストできます:" -ForegroundColor Cyan
        Write-Host '  $baseUrl = "http://localhost:8080/api.php"' -ForegroundColor Gray
        Write-Host '  $punchBody = @{ type = "in"; occurred_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss"); note = "出勤" } | ConvertTo-Json' -ForegroundColor Gray
        Write-Host '  Invoke-RestMethod -Uri "$baseUrl/punches" -Method POST -ContentType "application/json" -WebSession $session -Body $punchBody' -ForegroundColor Gray
        Write-Host ""
        Write-Host "または、完全なURLを使用:" -ForegroundColor Cyan
        Write-Host '  $punchBody = @{ type = "in"; occurred_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss"); note = "出勤" } | ConvertTo-Json' -ForegroundColor Gray
        Write-Host '  Invoke-RestMethod -Uri "http://localhost:8080/api.php/punches" -Method POST -ContentType "application/json" -WebSession $session -Body $punchBody' -ForegroundColor Gray
    } else {
        Write-Host "⚠ 従業員プロファイルが見つかりませんでした。" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "4. 従業員プロファイル作成中..." -ForegroundColor Yellow
        
        # 従業員プロファイルを作成
        $employeeCode = "EMP" + (Get-Random -Minimum 1000 -Maximum 9999)
        $employeeBody = @{
            employee_code = $employeeCode
            name = $meResponse.data.user.name
            email = $meResponse.data.user.email
            employment_type = "full_time"
            hire_date = (Get-Date -Format "yyyy-MM-dd")
            work_location_tz = "Asia/Tokyo"
        } | ConvertTo-Json
        
        try {
            $employeeResponse = Invoke-RestMethod -Uri "$baseUrl/employees" `
                -Method POST `
                -ContentType "application/json" `
                -WebSession $session `
                -Body $employeeBody
            
            $employeeId = $employeeResponse.data.id
            Write-Host "✓ 従業員プロファイル作成成功!" -ForegroundColor Green
            Write-Host "  Employee ID: $employeeId" -ForegroundColor Gray
            Write-Host "  従業員コード: $employeeCode" -ForegroundColor Gray
            Write-Host ""
            Write-Host "⚠ 注意: 従業員プロファイルにuser_idを紐付ける必要があります。" -ForegroundColor Yellow
            Write-Host ""
            Write-Host "以下のいずれかの方法でuser_idを紐付けてください:" -ForegroundColor Cyan
            Write-Host ""
            Write-Host "【方法1】Adminerを使用する場合:" -ForegroundColor Yellow
            Write-Host "  1. http://localhost:8080/adminer-login.php にアクセス" -ForegroundColor Gray
            Write-Host "  2. データベースに接続" -ForegroundColor Gray
            Write-Host "  3. 以下のSQLを実行:" -ForegroundColor Gray
            Write-Host "     UPDATE employee_profiles SET user_id = '$userId' WHERE id = '$employeeId' AND tenant_id = '$tenantId';" -ForegroundColor Cyan
            Write-Host ""
            Write-Host "【方法2】psqlを使用する場合:" -ForegroundColor Yellow
            Write-Host "  psql -U attendance_user -d attendance_management -c `"UPDATE employee_profiles SET user_id = '$userId' WHERE id = '$employeeId' AND tenant_id = '$tenantId';`"" -ForegroundColor Cyan
            Write-Host ""
            Write-Host "user_idを紐付けた後、再度このスクリプトを実行してください。" -ForegroundColor Yellow
        } catch {
            Write-Host "✗ 従業員プロファイル作成失敗: $($_.Exception.Message)" -ForegroundColor Red
            if ($_.Exception.Response) {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $responseBody = $reader.ReadToEnd()
                Write-Host "  エラー詳細: $responseBody" -ForegroundColor Red
            }
            exit 1
        }
    }
} catch {
    Write-Host "✗ 従業員プロファイル確認失敗: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "  エラー詳細: $responseBody" -ForegroundColor Red
    }
    exit 1
}

Write-Host ""
Write-Host "=== セットアップ完了 ===" -ForegroundColor Cyan

