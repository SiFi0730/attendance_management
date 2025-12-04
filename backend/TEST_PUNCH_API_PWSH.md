# 打刻API テストガイド（PowerShell版）

最終更新: 2025-12-02

## PowerShellでの正しいテスト方法

PowerShellでは、JSONのエスケープが問題になることがあります。以下の方法を推奨します。

---

## 方法1: シングルクォートでJSONを囲む（推奨）

PowerShellでは、シングルクォート（`'`）で囲むとエスケープが不要です。

### ログイン

```powershell
curl.exe -X POST http://localhost:8080/api.php/auth/login `
  -H "Content-Type: application/json" `
  -d '{"email":"test@example.com","password":"Test1234!"}' `
  -c cookies.txt
```

**重要**: JSON全体をシングルクォート（`'`）で囲み、内部のダブルクォートはそのまま使用します。

### 出勤打刻

```powershell
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{"type":"in","occurred_at":"2024-12-02 09:00:00","note":"出勤"}'
```

### 打刻一覧取得

```powershell
curl.exe -X GET "http://localhost:8080/api.php/punches?page=1&limit=20" `
  -b cookies.txt
```

---

## 方法2: JSONファイルを使用する

JSONをファイルに保存して、`@`記号で読み込む方法です。

### 1. JSONファイルを作成

**login.json:**
```json
{
  "email": "test@example.com",
  "password": "Test1234!"
}
```

**punch-in.json:**
```json
{
  "type": "in",
  "occurred_at": "2024-12-02 09:00:00",
  "note": "出勤"
}
```

### 2. コマンドを実行

```powershell
# ログイン
curl.exe -X POST http://localhost:8080/api.php/auth/login `
  -H "Content-Type: application/json" `
  -d "@login.json" `
  -c cookies.txt

# 出勤打刻
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d "@punch-in.json"
```

---

## 方法3: Invoke-RestMethodを使用（PowerShellネイティブ）

PowerShellのネイティブコマンドレットを使用する方法です。

### ログイン

```powershell
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
Write-Host ($loginResponse | ConvertTo-Json -Depth 10)
```

### 出勤打刻

```powershell
$punchBody = @{
    type = "in"
    occurred_at = "2024-12-02 09:00:00"
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

### 打刻一覧取得

```powershell
$punches = Invoke-RestMethod -Uri "http://localhost:8080/api.php/punches?page=1&limit=20" `
    -Method GET `
    -WebSession $session

Write-Host "打刻一覧取得成功!" -ForegroundColor Green
Write-Host ($punches | ConvertTo-Json -Depth 10)
```

---

## 完全なテストスクリプト

**test-punch.ps1:**

```powershell
# 打刻APIテストスクリプト（PowerShell版）

$baseUrl = "http://localhost:8080/api.php"

Write-Host "=== 打刻APIテスト ===" -ForegroundColor Cyan
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
    
    Write-Host "✓ ログイン成功!" -ForegroundColor Green
    Write-Host "  ユーザー: $($loginResponse.data.user.name)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "✗ ログイン失敗: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# 2. 出勤打刻
Write-Host "2. 出勤打刻中..." -ForegroundColor Yellow
$punchInBody = @{
    type = "in"
    occurred_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    note = "出勤"
} | ConvertTo-Json

try {
    $punchInResponse = Invoke-RestMethod -Uri "$baseUrl/punches" `
        -Method POST `
        -ContentType "application/json" `
        -WebSession $session `
        -Body $punchInBody
    
    Write-Host "✓ 出勤打刻成功!" -ForegroundColor Green
    Write-Host "  打刻時刻: $($punchInResponse.data.occurred_at)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "✗ 出勤打刻失敗: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "  エラー詳細: $responseBody" -ForegroundColor Red
    }
}

# 3. 打刻一覧取得
Write-Host "3. 打刻一覧取得中..." -ForegroundColor Yellow
try {
    $punches = Invoke-RestMethod -Uri "$baseUrl/punches?page=1&limit=20" `
        -Method GET `
        -WebSession $session
    
    Write-Host "✓ 打刻一覧取得成功!" -ForegroundColor Green
    Write-Host "  総件数: $($punches.data.pagination.total)" -ForegroundColor Gray
    Write-Host ""
    
    # 打刻一覧を表示
    Write-Host "打刻一覧:" -ForegroundColor Cyan
    foreach ($punch in $punches.data.items) {
        $typeName = switch ($punch.type) {
            "in" { "出勤" }
            "out" { "退勤" }
            "break_in" { "休憩開始" }
            "break_out" { "休憩終了" }
            default { $punch.type }
        }
        Write-Host "  - $($punch.occurred_at) [$typeName] $($punch.note)" -ForegroundColor White
    }
} catch {
    Write-Host "✗ 打刻一覧取得失敗: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== テスト完了 ===" -ForegroundColor Cyan
```

**実行方法:**
```powershell
.\test-punch.ps1
```

---

## トラブルシューティング

### 問題: "メールアドレスとパスワードは必須です" エラー

**原因**: JSONが正しく送信されていない

**解決方法1**: シングルクォートを使用
```powershell
# ❌ 間違い（エスケープが複雑）
-d '{\"email\":\"test@example.com\",\"password\":\"Test1234!\"}'

# ✅ 正しい（シングルクォートで囲む）
-d '{"email":"test@example.com","password":"Test1234!"}'
```

**解決方法2**: Invoke-RestMethodを使用
```powershell
$body = @{
    email = "test@example.com"
    password = "Test1234!"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8080/api.php/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $body `
    -SessionVariable session
```

### 問題: セッションが保持されない

**解決方法**: `-SessionVariable` を使用してセッションを変数に保存
```powershell
# ログイン時にセッションを保存
Invoke-RestMethod ... -SessionVariable session

# 以降のリクエストでセッションを使用
Invoke-RestMethod ... -WebSession $session
```

---

## クイックリファレンス

### ログイン
```powershell
curl.exe -X POST http://localhost:8080/api.php/auth/login `
  -H "Content-Type: application/json" `
  -d '{"email":"test@example.com","password":"Test1234!"}' `
  -c cookies.txt
```

### 出勤打刻
```powershell
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{"type":"in","occurred_at":"2024-12-02 09:00:00","note":"出勤"}'
```

### 休憩開始打刻
```powershell
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{"type":"break_in","occurred_at":"2024-12-02 12:00:00","note":"休憩開始"}'
```

### 休憩終了打刻
```powershell
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{"type":"break_out","occurred_at":"2024-12-02 13:00:00","note":"休憩終了"}'
```

### 退勤打刻
```powershell
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{"type":"out","occurred_at":"2024-12-02 18:00:00","note":"退勤"}'
```

### 打刻一覧取得
```powershell
curl.exe -X GET "http://localhost:8080/api.php/punches?page=1&limit=20&from=2024-12-01&to=2024-12-31" `
  -b cookies.txt
```

---

最終更新: 2025-12-02

