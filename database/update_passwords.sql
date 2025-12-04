-- ============================================
-- パスワードハッシュ更新スクリプト
-- ============================================
-- パスワード: Password123!

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

