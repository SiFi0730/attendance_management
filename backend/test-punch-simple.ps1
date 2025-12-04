# 打刻APIの簡単なテストスクリプト

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

# 2. 出勤打刻
Write-Host "2. 出勤打刻中..." -ForegroundColor Yellow
$punchBody = @{
    type = "in"
    occurred_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    note = "出勤"
} | ConvertTo-Json

try {
    $punchResponse = Invoke-RestMethod -Uri "$baseUrl/punches" `
        -Method POST `
        -ContentType "application/json" `
        -WebSession $session `
        -Body $punchBody
    
    Write-Host "✓ 出勤打刻成功!" -ForegroundColor Green
    Write-Host "  打刻時刻: $($punchResponse.data.occurred_at)" -ForegroundColor Gray
    Write-Host "  打刻種類: $($punchResponse.data.type)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "✗ 出勤打刻失敗: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "  エラー詳細: $responseBody" -ForegroundColor Red
    }
    exit 1
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
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "  エラー詳細: $responseBody" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "=== テスト完了 ===" -ForegroundColor Cyan

