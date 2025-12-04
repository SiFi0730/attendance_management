# テスト用データベース作成スクリプト（簡易版）
# attendance_userにデータベース作成権限を付与してから実行

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "テスト用データベース作成" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# テスト用データベース名のリスト
$dbNames = @(
    "attendance_management_test",
    "attendance_management_test1",
    "attendance_management_test2",
    "attendance_management_test3"
)

# attendance_userのパスワードを設定
$env:PGPASSWORD = "attendance_password"

Write-Host "データベースを作成中..." -ForegroundColor Cyan
Write-Host "注意: データベース作成にはスーパーユーザー権限が必要です。" -ForegroundColor Yellow
Write-Host "postgresユーザーのパスワードを入力してください。" -ForegroundColor Yellow
Write-Host ""

# postgresユーザーのパスワードを取得
$postgresPassword = Read-Host "PostgreSQLのpostgresユーザーのパスワードを入力してください" -AsSecureString
$postgresPasswordPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($postgresPassword)
)
$env:PGPASSWORD = $postgresPasswordPlain

# 各データベースを作成
foreach ($dbName in $dbNames) {
    Write-Host ""
    Write-Host "[作成中] $dbName データベースを作成中..." -ForegroundColor Yellow
    
    # データベースの存在確認
    $exists = psql -U postgres -lqt 2>&1 | Select-String -Pattern "^\s*$dbName\s"
    
    if ($exists) {
        Write-Host "[スキップ] $dbName は既に存在します。" -ForegroundColor Yellow
    } else {
        # データベースの作成
        $result = psql -U postgres -c "CREATE DATABASE $dbName;" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[OK] $dbName を作成しました。" -ForegroundColor Green
            
            # ユーザーに権限を付与
            $grantResult = psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE $dbName TO attendance_user;" 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[OK] attendance_user に権限を付与しました。" -ForegroundColor Green
            } else {
                Write-Host "[警告] 権限の付与に失敗しました。" -ForegroundColor Yellow
            }
        } else {
            Write-Host "[エラー] $dbName の作成に失敗しました。" -ForegroundColor Red
            Write-Host $result
        }
    }
}

# スキーマを適用するか確認
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "スキーマを適用しますか？" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
$applySchema = Read-Host "各テストデータベースにスキーマを適用する場合は Y を入力してください"

if ($applySchema -eq "Y" -or $applySchema -eq "y") {
    Write-Host ""
    Write-Host "スキーマを適用中..." -ForegroundColor Cyan
    
    # attendance_userのパスワードを設定
    $env:PGPASSWORD = "attendance_password"
    
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
    
    foreach ($dbName in $dbNames) {
        Write-Host ""
        Write-Host "[適用中] $dbName にスキーマを適用中..." -ForegroundColor Yellow
        
        # スキーマの適用
        $schemaResult = psql -U attendance_user -d $dbName -f "$scriptDir\schema.sql" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[OK] $dbName にスキーマを適用しました。" -ForegroundColor Green
            
            # RLSポリシーも適用
            $rlsResult = psql -U attendance_user -d $dbName -f "$scriptDir\rls_policies.sql" 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[OK] $dbName にRLSポリシーを適用しました。" -ForegroundColor Green
            } else {
                Write-Host "[警告] RLSポリシーの適用に失敗しました。" -ForegroundColor Yellow
            }
        } else {
            Write-Host "[エラー] $dbName へのスキーマ適用に失敗しました。" -ForegroundColor Red
        }
    }
} else {
    Write-Host "スキーマの適用をスキップしました。" -ForegroundColor Yellow
}

# 作成されたデータベース一覧を表示
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "作成されたデータベース一覧" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
$env:PGPASSWORD = $postgresPasswordPlain
psql -U postgres -c "\l" | Select-String -Pattern "attendance_management"

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "テストデータベース作成完了" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "作成されたデータベース:" -ForegroundColor Green
foreach ($dbName in $dbNames) {
    Write-Host "  - $dbName" -ForegroundColor White
}
Write-Host ""
Write-Host "接続方法:" -ForegroundColor Green
Write-Host "  psql -U attendance_user -d attendance_management_test" -ForegroundColor White
Write-Host "  psql -U attendance_user -d attendance_management_test1" -ForegroundColor White
Write-Host "  psql -U attendance_user -d attendance_management_test2" -ForegroundColor White
Write-Host "  psql -U attendance_user -d attendance_management_test3" -ForegroundColor White
Write-Host ""

# パスワードをクリア
$env:PGPASSWORD = ""

