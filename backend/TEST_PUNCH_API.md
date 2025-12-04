# 打刻API テストガイド

最終更新: 2025-12-02

## 前提条件

1. **サーバーが起動していること**
   ```cmd
   start-local-server.bat
   ```
   または
   ```cmd
   cd backend\public
   php -S localhost:8080
   ```

2. **データベースがセットアップされていること**
   - テナントとユーザーが作成されていること
   - 従業員プロファイルが作成されていること（打刻には従業員プロファイルが必要）

---

## テスト方法1: cURL（コマンドライン）

### PowerShellでの注意点

PowerShellでは、JSONのエスケープが問題になることがあります。**シングルクォート（`'`）でJSON全体を囲む**と、エスケープが不要になります。

詳細は `TEST_PUNCH_API_PWSH.md` を参照してください。

### ステップ1: ログインしてセッションを取得

```powershell
# Windows PowerShell（正しい方法）
curl.exe -X POST http://localhost:8080/api.php/auth/login `
  -H "Content-Type: application/json" `
  -d '{"email":"test@example.com","password":"Test1234!"}' `
  -c cookies.txt
```

**重要**: JSON全体をシングルクォート（`'`）で囲み、内部のダブルクォートはそのまま使用します。

**レスポンス例:**
```json
{
  "success": true,
  "message": "ログインに成功しました",
  "data": {
    "user": {
      "id": "uuid",
      "email": "test@example.com",
      "name": "テスト管理者",
      "tenant_id": "uuid",
      "role": "CompanyAdmin"
    },
    "session_id": "session_id"
  }
}
```

**重要:** `cookies.txt` ファイルにセッションCookieが保存されます。

### ステップ2: 出勤打刻

```bash
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{\"type\":\"in\",\"occurred_at\":\"2024-12-02 09:00:00\",\"note\":\"出勤\"}'
```

**レスポンス例:**
```json
{
  "success": true,
  "message": "打刻を記録しました",
  "data": {
    "id": "uuid",
    "tenant_id": "uuid",
    "employee_id": "uuid",
    "employee_code": "EMP001",
    "employee_name": "山田太郎",
    "type": "in",
    "occurred_at": "2024-12-02 09:00:00",
    "source": "web",
    "device": null,
    "note": "出勤",
    "proxy_user_id": null,
    "proxy_reason": null,
    "created_at": "2024-12-02 09:00:00",
    "updated_at": "2024-12-02 09:00:00"
  }
}
```

### ステップ3: 休憩開始打刻

```bash
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{\"type\":\"break_in\",\"occurred_at\":\"2024-12-02 12:00:00\",\"note\":\"休憩開始\"}'
```

### ステップ4: 休憩終了打刻

```bash
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{\"type\":\"break_out\",\"occurred_at\":\"2024-12-02 13:00:00\",\"note\":\"休憩終了\"}'
```

### ステップ5: 退勤打刻

```bash
curl.exe -X POST http://localhost:8080/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{\"type\":\"out\",\"occurred_at\":\"2024-12-02 18:00:00\",\"note\":\"退勤\"}'
```

### ステップ6: 打刻一覧取得

```bash
curl.exe -X GET "http://localhost:8080/api.php/punches?page=1&limit=20&from=2024-12-01&to=2024-12-31" `
  -b cookies.txt
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid",
        "tenant_id": "uuid",
        "employee_id": "uuid",
        "employee_code": "EMP001",
        "employee_name": "山田太郎",
        "type": "in",
        "occurred_at": "2024-12-02 09:00:00",
        "source": "web",
        "device": null,
        "note": "出勤",
        "proxy_user_id": null,
        "proxy_user_name": null,
        "proxy_reason": null,
        "created_at": "2024-12-02 09:00:00",
        "updated_at": "2024-12-02 09:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 4,
      "totalPages": 1
    }
  }
}
```

### ステップ7: 代理打刻（管理者のみ）

```bash
curl.exe -X POST http://localhost:8080/api.php/punches/proxy `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{\"employee_id\":\"<従業員ID>\",\"type\":\"in\",\"occurred_at\":\"2024-12-02 09:00:00\",\"proxy_reason\":\"従業員が忘れたため\",\"note\":\"代理打刻\"}'
```

---

## テスト方法2: Postman / Thunder Client（VS Code拡張機能）

### セットアップ

1. **コレクションを作成**
   - 新しいコレクションを作成（例: "勤怠管理API"）

2. **環境変数を設定**
   - `base_url`: `http://localhost:8080/api.php`
   - `session_id`: （ログイン後に設定）

### リクエスト1: ログイン

- **Method**: POST
- **URL**: `{{base_url}}/auth/login`
- **Headers**: 
  - `Content-Type: application/json`
- **Body** (raw JSON):
```json
{
  "email": "test@example.com",
  "password": "Test1234!"
}
```

**テストスクリプト（Postman）:**
```javascript
// セッションCookieを環境変数に保存
const cookies = pm.response.headers.get("Set-Cookie");
if (cookies) {
    const sessionMatch = cookies.match(/PHPSESSID=([^;]+)/);
    if (sessionMatch) {
        pm.environment.set("session_id", sessionMatch[1]);
    }
}
```

### リクエスト2: 出勤打刻

- **Method**: POST
- **URL**: `{{base_url}}/punches`
- **Headers**: 
  - `Content-Type: application/json`
  - `Cookie: PHPSESSID={{session_id}}`
- **Body** (raw JSON):
```json
{
  "type": "in",
  "occurred_at": "2024-12-02 09:00:00",
  "note": "出勤",
  "device": "Web Browser"
}
```

### リクエスト3: 打刻一覧取得

- **Method**: GET
- **URL**: `{{base_url}}/punches?page=1&limit=20&from=2024-12-01&to=2024-12-31`
- **Headers**: 
  - `Cookie: PHPSESSID={{session_id}}`

---

## テスト方法3: ブラウザの開発者ツール

### ステップ1: ブラウザでログイン

1. `http://localhost:8080/index.html` にアクセス
2. ログインする
3. 開発者ツール（F12）を開く
4. **Application** タブ（Chrome）または **Storage** タブ（Firefox）でセッションCookieを確認

### ステップ2: ConsoleでAPIを呼び出す

```javascript
// 出勤打刻
fetch('http://localhost:8080/api.php/punches', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  credentials: 'include', // Cookieを送信
  body: JSON.stringify({
    type: 'in',
    occurred_at: '2024-12-02 09:00:00',
    note: '出勤'
  })
})
.then(response => response.json())
.then(data => console.log('打刻成功:', data))
.catch(error => console.error('エラー:', error));
```

### ステップ3: Networkタブで確認

1. **Network** タブを開く
2. 打刻ボタンをクリック（フロントエンドが実装されている場合）
3. リクエストとレスポンスを確認

---

## テストシナリオ

### シナリオ1: 正常な打刻フロー

1. ✅ ログイン
2. ✅ 出勤打刻 (`type: "in"`)
3. ✅ 休憩開始打刻 (`type: "break_in"`)
4. ✅ 休憩終了打刻 (`type: "break_out"`)
5. ✅ 退勤打刻 (`type: "out"`)
6. ✅ 打刻一覧で確認

### シナリオ2: 打刻順序の検証

1. ✅ 出勤前に退勤を試みる → **エラー**: "出勤記録がないため退勤できません"
2. ✅ 出勤打刻
3. ✅ 休憩開始前に休憩終了を試みる → **エラー**: "休憩開始記録がないため休憩終了できません"
4. ✅ 休憩開始打刻
5. ✅ 休憩終了打刻
6. ✅ 退勤打刻

### シナリオ3: 重複打刻の検証

1. ✅ 出勤打刻
2. ✅ 同じ時刻で再度出勤打刻を試みる → **エラー**: "同じ打刻が既に記録されています" (409 Conflict)

### シナリオ4: 未来日時の検証

1. ✅ 未来の日時で打刻を試みる → **エラー**: "未来日時の打刻はできません"

### シナリオ5: 代理打刻の検証

1. ✅ 管理者でログイン
2. ✅ 代理打刻（過去30日以内） → **成功**
3. ✅ 代理打刻（過去30日より前） → **エラー**: "過去30日以内の打刻のみ代理打刻できます"
4. ✅ 代理打刻（理由なし） → **エラー**: "代理打刻理由は必須です"

### シナリオ6: 権限の検証

1. ✅ Employeeでログイン
2. ✅ 自分の打刻 → **成功**
3. ✅ 代理打刻を試みる → **エラー**: "代理打刻の権限がありません" (403 Forbidden)

---

## エラーレスポンスの確認

### バリデーションエラー

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "打刻種類と打刻日時は必須です"
  }
}
```

### 権限エラー

```json
{
  "error": {
    "code": "FORBIDDEN",
    "message": "代理打刻の権限がありません"
  }
}
```

### 重複エラー

```json
{
  "error": {
    "code": "CONFLICT",
    "message": "同じ打刻が既に記録されています"
  }
}
```

---

## トラブルシューティング

### 問題1: セッションが切れている

**症状**: `UNAUTHORIZED` エラーが返る

**解決方法**:
- 再度ログインしてセッションCookieを取得
- `cookies.txt` を削除して再作成

### 問題2: 従業員プロファイルが見つからない

**症状**: `NOT_FOUND` エラー: "従業員プロファイルが見つかりません"

**解決方法**:
1. 従業員プロファイルを作成する必要があります
2. `/employees` APIで従業員を作成
3. ユーザーアカウントと従業員プロファイルを紐付ける

### 問題3: 打刻順序エラー

**症状**: "出勤記録がないため退勤できません"

**解決方法**:
- 正しい順序で打刻する: 出勤 → 休憩開始 → 休憩終了 → 退勤

### 問題4: データベース接続エラー

**症状**: `INTERNAL_ERROR` エラー

**解決方法**:
1. データベースが起動しているか確認
2. `.env` ファイルの設定を確認
3. `database/setup-database.bat` を実行

---

## 便利なテストスクリプト

### PowerShellスクリプト（test-punch.ps1）

```powershell
# テスト用のPowerShellスクリプト

$baseUrl = "http://localhost:8080/api.php"
$cookiesFile = "cookies.txt"

# ログイン
Write-Host "1. ログイン中..." -ForegroundColor Yellow
$loginResponse = Invoke-RestMethod -Uri "$baseUrl/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body '{"email":"test@example.com","password":"Test1234!"}' `
    -SessionVariable session

Write-Host "ログイン成功!" -ForegroundColor Green

# 出勤打刻
Write-Host "2. 出勤打刻中..." -ForegroundColor Yellow
$punchIn = Invoke-RestMethod -Uri "$baseUrl/punches" `
    -Method POST `
    -ContentType "application/json" `
    -WebSession $session `
    -Body '{"type":"in","occurred_at":"2024-12-02 09:00:00","note":"出勤"}'

Write-Host "出勤打刻成功!" -ForegroundColor Green
Write-Host ($punchIn | ConvertTo-Json -Depth 10)

# 打刻一覧取得
Write-Host "3. 打刻一覧取得中..." -ForegroundColor Yellow
$punches = Invoke-RestMethod -Uri "$baseUrl/punches?page=1&limit=20" `
    -Method GET `
    -WebSession $session

Write-Host "打刻一覧取得成功!" -ForegroundColor Green
Write-Host ($punches | ConvertTo-Json -Depth 10)
```

**実行方法:**
```powershell
.\test-punch.ps1
```

---

## 参考資料

- **APIドキュメント**: `backend/API_DOCUMENTATION.md`
- **仕様書**: `doc/仕様書.md` (第5.4節: 打刻・申請・承認)
- **実装状況**: `doc/IMPLEMENTATION_STATUS.md`

---

最終更新: 2025-12-02

