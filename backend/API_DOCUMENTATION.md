# API ドキュメント

最終更新: 2025-01-15

## ベースURL

```
http://localhost:8080/api.php
```

## 認証

### 会員登録
```http
POST /auth/register
Content-Type: application/json

{
  "tenant_name": "株式会社サンプル",
  "name": "管理者名",
  "email": "admin@example.com",
  "password": "Password123!",
  "timezone": "Asia/Tokyo",
  "locale": "ja"
}
```

**レスポンス:**
```json
{
  "success": true,
  "message": "会員登録が完了しました",
  "data": {
    "user": {
      "id": "uuid",
      "email": "admin@example.com",
      "name": "管理者名",
      "tenant_id": "uuid",
      "role": "CompanyAdmin"
    },
    "tenant": {
      "id": "uuid",
      "name": "株式会社サンプル",
      "timezone": "Asia/Tokyo",
      "locale": "ja"
    }
  }
}
```

### ログイン
```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "Password123!"
}
```

**レスポンス:**
```json
{
  "success": true,
  "message": "ログインに成功しました",
  "data": {
    "user": {
      "id": "uuid",
      "email": "admin@example.com",
      "name": "管理者名",
      "tenant_id": "uuid",
      "role": "CompanyAdmin"
    },
    "session_id": "session_id"
  }
}
```

### ログアウト
```http
POST /auth/logout
Cookie: PHPSESSID=<session_id>
```

### 現在のユーザー情報取得
```http
GET /auth/me
Cookie: PHPSESSID=<session_id>
```

### パスワードリセット要求
```http
POST /auth/password-reset
Content-Type: application/json

{
  "email": "admin@example.com"
}
```

### パスワードリセット実行
```http
POST /auth/password-reset/verify
Content-Type: application/json

{
  "token": "reset_token",
  "password": "NewPassword123!"
}
```

---

## テナント管理

### テナント一覧取得
```http
GET /tenants
Cookie: PHPSESSID=<session_id>
```

### テナント詳細取得
```http
GET /tenants/{id}
Cookie: PHPSESSID=<session_id>
```

### テナント更新
```http
PUT /tenants/{id}
Content-Type: application/json
Cookie: PHPSESSID=<session_id>

{
  "name": "更新後の企業名",
  "timezone": "Asia/Tokyo",
  "locale": "ja"
}
```

---

## 従業員管理

### 従業員一覧取得
```http
GET /employees?page=1&limit=20&status=active&dept_id=uuid
Cookie: PHPSESSID=<session_id>
```

**クエリパラメータ:**
- `page`: ページ番号（デフォルト: 1）
- `limit`: 1ページあたりの件数（デフォルト: 20）
- `status`: ステータスでフィルタ（active, on_leave, retired）
- `dept_id`: 部署IDでフィルタ

**レスポンス:**
```json
{
  "success": true,
  "data": {
    "items": [...],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 100,
      "totalPages": 5
    }
  }
}
```

### 従業員詳細取得
```http
GET /employees/{id}
Cookie: PHPSESSID=<session_id>
```

### 従業員作成
```http
POST /employees
Content-Type: application/json
Cookie: PHPSESSID=<session_id>

{
  "employee_code": "EMP001",
  "name": "山田太郎",
  "email": "yamada@example.com",
  "dept_id": "uuid",
  "employment_type": "full_time",
  "hire_date": "2024-01-01",
  "work_location_tz": "Asia/Tokyo"
}
```

### 従業員更新
```http
PUT /employees/{id}
Content-Type: application/json
Cookie: PHPSESSID=<session_id>

{
  "name": "更新後の名前",
  "email": "newemail@example.com",
  "dept_id": "uuid",
  "status": "active"
}
```

### 従業員削除
```http
DELETE /employees/{id}
Cookie: PHPSESSID=<session_id>
```

---

## 部署管理

### 部署一覧取得
```http
GET /departments
Cookie: PHPSESSID=<session_id>
```

### 部署詳細取得
```http
GET /departments/{id}
Cookie: PHPSESSID=<session_id>
```

### 部署作成
```http
POST /departments
Content-Type: application/json
Cookie: PHPSESSID=<session_id>

{
  "name": "営業部",
  "code": "SALES",
  "parent_id": "uuid"
}
```

### 部署更新
```http
PUT /departments/{id}
Content-Type: application/json
Cookie: PHPSESSID=<session_id>

{
  "name": "更新後の部署名",
  "code": "NEW_CODE",
  "parent_id": "uuid"
}
```

### 部署削除
```http
DELETE /departments/{id}
Cookie: PHPSESSID=<session_id>
```

---

## 打刻管理

### 打刻記録作成
```http
POST /punches
Content-Type: application/json
Cookie: PHPSESSID=<session_id>

{
  "type": "in",
  "occurred_at": "2024-01-15 09:00:00",
  "note": "出勤",
  "device": "Web Browser"
}
```

**リクエストボディ:**
- `type` (必須): 打刻種類 (`in`, `out`, `break_in`, `break_out`)
- `occurred_at` (必須): 打刻日時 (ISO8601形式、例: `2024-01-15 09:00:00`)
- `note` (任意): 備考
- `device` (任意): デバイス情報

**レスポンス:**
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
    "occurred_at": "2024-01-15 09:00:00",
    "source": "web",
    "device": "Web Browser",
    "note": "出勤",
    "proxy_user_id": null,
    "proxy_reason": null,
    "created_at": "2024-01-15 09:00:00",
    "updated_at": "2024-01-15 09:00:00"
  }
}
```

**バリデーション:**
- 打刻種類は `in`, `out`, `break_in`, `break_out` のいずれか
- 未来日時の打刻は不可
- 打刻順序の検証（出勤→退勤、休憩開始→休憩終了）
- 重複打刻の検証（冪等性保証）

### 代理打刻
```http
POST /punches/proxy
Content-Type: application/json
Cookie: PHPSESSID=<session_id>

{
  "employee_id": "uuid",
  "type": "in",
  "occurred_at": "2024-01-15 09:00:00",
  "proxy_reason": "従業員が忘れたため",
  "note": "代理打刻",
  "device": "Web Browser"
}
```

**リクエストボディ:**
- `employee_id` (必須): 従業員ID
- `type` (必須): 打刻種類 (`in`, `out`, `break_in`, `break_out`)
- `occurred_at` (必須): 打刻日時
- `proxy_reason` (必須): 代理打刻理由
- `note` (任意): 備考
- `device` (任意): デバイス情報

**権限:**
- CompanyAdmin, Manager, Professional のみ実行可能
- Managerの場合は配下の従業員のみ（将来実装）

**制限:**
- 未来日時の打刻は不可
- 過去30日以内の打刻のみ代理打刻可能

**レスポンス:**
```json
{
  "success": true,
  "message": "代理打刻を記録しました",
  "data": {
    "id": "uuid",
    "tenant_id": "uuid",
    "employee_id": "uuid",
    "employee_code": "EMP001",
    "employee_name": "山田太郎",
    "type": "in",
    "occurred_at": "2024-01-15 09:00:00",
    "source": "web",
    "device": "Web Browser",
    "note": "代理打刻",
    "proxy_user_id": "uuid",
    "proxy_user_name": "管理者名",
    "proxy_reason": "従業員が忘れたため",
    "created_at": "2024-01-15 09:00:00",
    "updated_at": "2024-01-15 09:00:00"
  }
}
```

### 打刻一覧取得
```http
GET /punches?page=1&limit=20&employee_id=uuid&type=in&from=2024-01-01&to=2024-01-31
Cookie: PHPSESSID=<session_id>
```

**クエリパラメータ:**
- `page`: ページ番号（デフォルト: 1）
- `limit`: 1ページあたりの件数（デフォルト: 20）
- `employee_id`: 従業員IDでフィルタ（Employeeの場合は自動的に自分のID）
- `type`: 打刻種類でフィルタ (`in`, `out`, `break_in`, `break_out`)
- `from`: 開始日時（ISO8601形式）
- `to`: 終了日時（ISO8601形式）

**レスポンス:**
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
        "occurred_at": "2024-01-15 09:00:00",
        "source": "web",
        "device": "Web Browser",
        "note": "出勤",
        "proxy_user_id": null,
        "proxy_user_name": null,
        "proxy_reason": null,
        "created_at": "2024-01-15 09:00:00",
        "updated_at": "2024-01-15 09:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 100,
      "totalPages": 5
    }
  }
}
```

**権限:**
- Employeeの場合は自分の打刻のみ表示
- CompanyAdmin, Manager, Professional は全従業員の打刻を表示可能

---

## エラーレスポンス

```json
{
  "error": {
    "code": "ERR_XXX",
    "message": "エラーメッセージ",
    "details": {}
  }
}
```

### エラーコード

- `VALIDATION_ERROR`: バリデーションエラー
- `INVALID_CREDENTIALS`: 認証情報が無効
- `UNAUTHORIZED`: 認証が必要
- `FORBIDDEN`: 権限が不足
- `NOT_FOUND`: リソースが見つからない
- `CONFLICT`: 競合エラー（重複等）
- `RATE_LIMIT`: レート制限
- `INTERNAL_ERROR`: 内部エラー

---

## 認証が必要なエンドポイント

以下のエンドポイントは認証が必要です：
- `/auth/me`
- `/tenants/*`
- `/employees/*`
- `/departments/*`
- `/punches` (GET, POST)
- `/punches/proxy` (POST)

認証にはセッションCookie（`PHPSESSID`）を使用します。

---

## テスト方法

### cURL でのテスト例

```bash
# 会員登録
curl -X POST http://localhost:8080/api.php/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_name": "テスト株式会社",
    "name": "テスト管理者",
    "email": "test@example.com",
    "password": "Test1234!",
    "timezone": "Asia/Tokyo"
  }'

# ログイン
curl -X POST http://localhost:8080/api.php/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "Test1234!"
  }' \
  -c cookies.txt

# 従業員一覧取得（セッションCookieを使用）
curl -X GET http://localhost:8080/api.php/employees \
  -b cookies.txt

# 打刻記録作成
curl -X POST http://localhost:8080/api.php/punches \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "type": "in",
    "occurred_at": "2024-01-15 09:00:00",
    "note": "出勤"
  }'

# 打刻一覧取得
curl -X GET "http://localhost:8080/api.php/punches?page=1&limit=20&from=2024-01-01&to=2024-01-31" \
  -b cookies.txt
```

---

最終更新: 2025-12-02

