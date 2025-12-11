# セキュリティチェックリスト

**最終確認日**: 2025-01-15

## ✅ GitHub公開前の確認事項

### 機密情報の確認

#### ✅ 環境変数ファイル
- [x] `.env`ファイルが`.gitignore`に含まれている
- [x] `.env.local`ファイルが`.gitignore`に含まれている
- [x] 実際の`.env`ファイルがリポジトリに含まれていない

#### ✅ データベース認証情報
- [x] README.mdから実際のパスワードを削除（プレースホルダーに置き換え済み）
- [x] コード内にハードコードされた認証情報がない
- [x] すべての認証情報が環境変数から読み込まれる

#### ✅ テスト用データ
- [x] `database/seed_data.sql`にテスト用パスワードが含まれている（開発用として適切）
- [x] `database/update_passwords.sql`にテスト用パスワードが含まれている（開発用として適切）
- [x] 両ファイルにセキュリティ警告コメントを追加済み

#### ✅ 個人情報
- [x] README.mdから実際のパスを削除（プレースホルダーに置き換え済み）
- [x] 実際のメールアドレスや個人情報が含まれていない
- [x] テスト用のメールアドレス（`test@example.com`など）のみ使用

### 開発用ファイルの確認

#### ✅ 開発環境専用ファイル
- [x] `backend/public/index.php` - 開発環境専用として適切にマーク
- [x] `backend/public/adminer-login.php` - セキュリティ警告を追加済み
- [x] `backend/public/adminer-wrapper.php` - 開発環境専用
- [x] `backend/public/adminer.php` - 開発環境専用

#### ✅ スクリプトファイル
- [x] `backend/setup-punch-test.ps1` - テスト用認証情報に警告を追加済み
- [x] すべてのスクリプトが開発・テスト用として適切にマーク

### コード品質の確認

#### ✅ デバッグコード
- [x] 本番環境でデバッグコードが無効化される設定
- [x] `console.debug`が本番環境で無効化される設定
- [x] エラーメッセージが本番環境で隠蔽される設定

#### ✅ セキュリティ設定
- [x] CORS設定が環境変数で制御される
- [x] パスワードリセットトークンが本番環境でレスポンスに含まれない
- [x] セッションIDがレスポンスに含まれない

### 依存関係の確認

#### ✅ Composer依存関係
- [x] `vendor/`ディレクトリが`.gitignore`に含まれている
- [x] `composer.lock`がコミットされている（推奨）
- [x] `composer.json`に機密情報が含まれていない

### ドキュメントの確認

#### ✅ ドキュメント内の機密情報
- [x] README.mdから実際のパスワードを削除
- [x] README.mdから実際のパスを削除
- [x] APIドキュメントにテスト用の認証情報のみ記載（適切）

## ⚠️ 注意事項

### 本番環境で削除すべきファイル

以下のファイルは本番環境では削除するか、アクセス制限を設定してください：

1. `backend/public/index.php` - 開発環境情報表示ページ
2. `backend/public/adminer-login.php` - データベース管理ツール
3. `backend/public/adminer-wrapper.php` - データベース管理ツール
4. `backend/public/adminer.php` - データベース管理ツール
5. `backend/src/Controllers/DevController.php` - 開発用コントローラー

### 本番環境で使用しないファイル

以下のファイルは開発・テスト環境専用です：

1. `database/seed_data.sql` - テスト用データ
2. `database/update_passwords.sql` - テスト用パスワード更新
3. `backend/setup-punch-test.ps1` - テスト用スクリプト
4. `backend/update-employee-user-id.ps1` - 開発用スクリプト

## ✅ 最終確認

- [x] すべての機密情報が環境変数に移行されている
- [x] 実際のパスワードや認証情報がコードに含まれていない
- [x] 個人情報が含まれていない
- [x] 開発用ファイルに適切な警告が追加されている
- [x] `.gitignore`が適切に設定されている

---

**確認者**: AI Security Review  
**最終更新**: 2025-01-15

