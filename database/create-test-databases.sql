-- ============================================
-- テスト用データベース作成SQL
-- ============================================
-- 実行方法:
--   psql -U postgres -f create-test-databases.sql
-- ============================================

-- テスト用データベースを作成
CREATE DATABASE attendance_management_test;
CREATE DATABASE attendance_management_test1;
CREATE DATABASE attendance_management_test2;
CREATE DATABASE attendance_management_test3;

-- ユーザーに権限を付与
GRANT ALL PRIVILEGES ON DATABASE attendance_management_test TO attendance_user;
GRANT ALL PRIVILEGES ON DATABASE attendance_management_test1 TO attendance_user;
GRANT ALL PRIVILEGES ON DATABASE attendance_management_test2 TO attendance_user;
GRANT ALL PRIVILEGES ON DATABASE attendance_management_test3 TO attendance_user;

-- 完了メッセージ
\echo '============================================'
\echo 'テスト用データベース作成完了'
\echo '============================================'
\echo ''
\echo '作成されたデータベース:'
\echo '  - attendance_management_test'
\echo '  - attendance_management_test1'
\echo '  - attendance_management_test2'
\echo '  - attendance_management_test3'
\echo ''
\echo '次のステップ:'
\echo '  各データベースにスキーマを適用してください:'
\echo '  psql -U attendance_user -d attendance_management_test -f schema.sql'
\echo '  psql -U attendance_user -d attendance_management_test -f rls_policies.sql'
\echo ''

