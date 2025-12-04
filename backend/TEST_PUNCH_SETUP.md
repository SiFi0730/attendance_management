# 打刻APIテスト前のセットアップガイド

最終更新: 2025-12-02

## 問題

打刻APIを実行すると以下のエラーが発生します：
```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "従業員プロファイルが見つかりません"
  }
}
```

**原因**: ログインしたユーザーに対応する従業員プロファイルが作成されていないためです。

---

## 解決方法

打刻を行うには、**従業員プロファイルを作成し、ユーザーアカウントと紐付ける**必要があります。

### ステップ1: 現在のユーザー情報を取得

```powershell
# ログイン
$loginBody = @{
    email = "test@example.com"
    password = "Test1234!"
} | ConvertTo-Json

$loginResponse = Invoke-RestMethod -Uri "http://localhost:8080/api.php/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $loginBody `
    -SessionVariable session

Write-Host "ログイン成功!" -ForegroundColor Green
Write-Host "User ID: $($loginResponse.data.user.id)" -ForegroundColor Cyan
Write-Host "Tenant ID: $($loginResponse.data.user.tenant_id)" -ForegroundColor Cyan

# ユーザーIDとテナントIDを変数に保存
$userId = $loginResponse.data.user.id
$tenantId = $loginResponse.data.user.tenant_id
```

### ステップ2: 従業員プロファイルを作成

```powershell
# 従業員プロファイル作成
$employeeBody = @{
    employee_code = "EMP001"
    name = "テスト管理者"
    email = "test@example.com"
    employment_type = "full_time"
    hire_date = "2024-01-01"
    work_location_tz = "Asia/Tokyo"
} | ConvertTo-Json

$employeeResponse = Invoke-RestMethod -Uri "http://localhost:8080/api.php/employees" `
    -Method POST `
    -ContentType "application/json" `
    -WebSession $session `
    -Body $employeeBody

Write-Host "従業員プロファイル作成成功!" -ForegroundColor Green
Write-Host "Employee ID: $($employeeResponse.data.id)" -ForegroundColor Cyan

# 従業員IDを変数に保存
$employeeId = $employeeResponse.data.id
```

### ステップ3: 従業員プロファイルにユーザーIDを紐付け（更新）

現在のAPIでは、従業員作成時にuser_idを指定できません。以下の方法で紐付けます：

**方法A: 従業員更新APIを使用（user_idフィールドがサポートされている場合）**

```powershell
# 従業員プロファイルを更新してuser_idを紐付け
$updateBody = @{
    user_id = $userId
} | ConvertTo-Json

# 注意: 現在のAPIではuser_idの更新がサポートされていない可能性があります
# その場合は方法Bを使用してください
```

**方法B: 直接SQLで更新（推奨）**

Adminerまたはpsqlを使用して、従業員プロファイルのuser_idを更新します：

```sql
-- 従業員プロファイルのuser_idを更新
UPDATE employee_profiles 
SET user_id = '<ユーザーID>' 
WHERE id = '<従業員ID>' 
AND tenant_id = '<テナントID>';
```

**PowerShellからSQLを実行する場合:**

```powershell
# PostgreSQLに接続してSQLを実行
# 注意: psqlがPATHに含まれている必要があります
$sql = "UPDATE employee_profiles SET user_id = '$userId' WHERE id = '$employeeId' AND tenant_id = '$tenantId';"
# psqlコマンドを実行（環境に応じて調整）
```

### ステップ4: 打刻を実行

```powershell
# 出勤打刻
$punchBody = @{
    type = "in"
    occurred_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    note = "出勤"
} | ConvertTo-Json

$punchResponse = Invoke-RestMethod -Uri "http://localhost:8080/api.php/punches" `
    -Method POST `
    -ContentType "application/json" `
    -WebSession $session `
    -Body $punchBody

Write-Host "打刻成功!" -ForegroundColor Green
Write-Host ($punchResponse | ConvertTo-Json -Depth 10)
```

---

## 完全なセットアップスクリプト

**setup-punch-test.ps1:**

```powershell
# 打刻テスト用セットアップスクリプト

$baseUrl = "http://localhost:8080/api.php"

Write-Host "=== 打刻テスト用セットアップ ===" -ForegroundColor Cyan
Write-Host ""

# 1. ログイン
Write-Host "1. ログイン中..." -ForegroundColor Yellow
$loginBody = @{
    email = "test@example.com"
    password = "Test1234!"
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
    } else {
        Write-Host "⚠ 従業員プロファイルが見つかりませんでした。" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "4. 従業員プロファイル作成中..." -ForegroundColor Yellow
        
        # 従業員プロファイルを作成
        $employeeBody = @{
            employee_code = "EMP" + (Get-Random -Minimum 1000 -Maximum 9999)
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
            Write-Host ""
            Write-Host "⚠ 注意: 従業員プロファイルにuser_idを紐付ける必要があります。" -ForegroundColor Yellow
            Write-Host "  以下のSQLを実行してください:" -ForegroundColor Yellow
            Write-Host "  UPDATE employee_profiles SET user_id = '$userId' WHERE id = '$employeeId';" -ForegroundColor Cyan
            Write-Host ""
        } catch {
            Write-Host "✗ 従業員プロファイル作成失敗: $($_.Exception.Message)" -ForegroundColor Red
            if ($_.Exception.Response) {
                $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
                $responseBody = $reader.ReadToEnd()
                Write-Host "  エラー詳細: $responseBody" -ForegroundColor Red
            }
        }
    }
} catch {
    Write-Host "✗ 従業員プロファイル確認失敗: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== セットアップ完了 ===" -ForegroundColor Cyan
```

**実行方法:**
```powershell
.\setup-punch-test.ps1
```

---

## Adminerを使用した手動セットアップ

1. **Adminerにアクセス**
   - `http://localhost:8080/adminer-login.php`

2. **データベースに接続**

3. **従業員プロファイルを確認**
   ```sql
   SELECT * FROM employee_profiles 
   WHERE tenant_id = '<テナントID>' 
   AND deleted_at IS NULL;
   ```

4. **従業員プロファイルが存在しない場合、作成**
   ```sql
   INSERT INTO employee_profiles (
       tenant_id, employee_code, name, email, 
       employment_type, hire_date, status
   ) VALUES (
       '<テナントID>', 'EMP001', 'テスト管理者', 'test@example.com',
       'full_time', '2024-01-01', 'active'
   )
   RETURNING id;
   ```

5. **従業員プロファイルにuser_idを紐付け**
   ```sql
   UPDATE employee_profiles 
   SET user_id = '<ユーザーID>' 
   WHERE id = '<従業員ID>' 
   AND tenant_id = '<テナントID>';
   ```

---

## トラブルシューティング

### 問題: 従業員プロファイル作成時に権限エラー

**エラー**: `FORBIDDEN` - "従業員を作成する権限がありません"

**解決方法**: CompanyAdminまたはSystemAdminでログインしていることを確認してください。

### 問題: user_idの更新ができない

**解決方法**: 現在のAPIでは、従業員更新時にuser_idを更新できない可能性があります。その場合は、直接SQLで更新してください。

---

最終更新: 2025-12-02

