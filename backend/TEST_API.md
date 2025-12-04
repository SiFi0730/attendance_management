# API動作確認ガイド

## サーバー起動

```cmd
cd backend\public
php -S localhost:8000
```

## テスト用エンドポイント

### 1. ログイン
```bash
POST http://localhost:8000/api.php/auth/login
Content-Type: application/json

{
  "email": "admin@sample.co.jp",
  "password": "Password123!"
}
```

### 2. 現在のユーザー情報取得（認証必要）
```bash
GET http://localhost:8000/api.php/auth/me
Cookie: PHPSESSID=<session_id>
```

### 3. ログアウト
```bash
POST http://localhost:8000/api.php/auth/logout
Cookie: PHPSESSID=<session_id>
```

## 打刻APIのテスト

詳細なテスト手順は `TEST_PUNCH_API.md` を参照してください。

### 簡単なテスト例

```bash
# ログイン（セッションCookieを保存）
curl.exe -X POST http://localhost:8000/api.php/auth/login `
  -H "Content-Type: application/json" `
  -d '{\"email\":\"test@example.com\",\"password\":\"Test1234!\"}' `
  -c cookies.txt

# 出勤打刻
curl.exe -X POST http://localhost:8000/api.php/punches `
  -H "Content-Type: application/json" `
  -b cookies.txt `
  -d '{\"type\":\"in\",\"occurred_at\":\"2024-12-02 09:00:00\",\"note\":\"出勤\"}'

# 打刻一覧取得
curl.exe -X GET "http://localhost:8000/api.php/punches?page=1&limit=20" `
  -b cookies.txt
```

## 注意事項

- シードデータのパスワードハッシュを更新する必要があります
- セッション管理はCookieベースで動作します
- CORS設定は開発環境用です（本番環境では適切に設定してください）
- 打刻APIを使用するには、従業員プロファイルが作成されている必要があります

