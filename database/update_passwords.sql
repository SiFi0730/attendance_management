-- ============================================
-- パスワードハッシュ更新スクリプト（開発・テスト用）
-- ============================================
-- 
-- ⚠️ セキュリティ警告:
--   このファイルは開発・テスト環境専用です。
--   本番環境では絶対に使用しないでください。
--   テスト用のパスワード（Password123!）が含まれています。
--   GitHub等で公開する場合は、このファイルの内容を確認してください。
--
-- パスワード: Password123! (テスト用のみ)

-- システム管理者
UPDATE users 
SET password_hash = '$2y$10$FGEXTIkWL90qslEbGWBdkeHTFFQP8NIHjn3AhtrZcIEWu1eLFz2tO'
WHERE email = 'admin@system.local';

-- テスト用ユーザー
UPDATE users 
SET password_hash = '$2y$10$FGEXTIkWL90qslEbGWBdkeHTFFQP8NIHjn3AhtrZcIEWu1eLFz2tO'
WHERE email IN (
    'admin@sample.co.jp',
    'yamada@sample.co.jp',
    'sato@sample.co.jp'
);

