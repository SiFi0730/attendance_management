-- ============================================
-- Row-Level Security (RLS) ポリシー
-- テナント分離をデータベースレベルで保証
-- ============================================

-- 注意: 本番環境では、アプリケーションレベルでのテナントチェックも必須です。
-- RLSは防御の一層として機能します。

-- セッション変数でテナントIDを設定する関数
CREATE OR REPLACE FUNCTION set_tenant_id(tenant_uuid UUID)
RETURNS VOID AS $$
BEGIN
    PERFORM set_config('app.current_tenant_id', tenant_uuid::TEXT, false);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 現在のテナントIDを取得する関数
CREATE OR REPLACE FUNCTION get_current_tenant_id()
RETURNS UUID AS $$
BEGIN
    RETURN COALESCE(
        NULLIF(current_setting('app.current_tenant_id', true), '')::UUID,
        NULL
    );
END;
$$ LANGUAGE plpgsql STABLE;

-- ============================================
-- RLS有効化
-- ============================================

-- 各テーブルでRLSを有効化
ALTER TABLE role_assignments ENABLE ROW LEVEL SECURITY;
ALTER TABLE employee_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE departments ENABLE ROW LEVEL SECURITY;
ALTER TABLE business_hours ENABLE ROW LEVEL SECURITY;
ALTER TABLE rule_sets ENABLE ROW LEVEL SECURITY;
ALTER TABLE holiday_calendars ENABLE ROW LEVEL SECURITY;
ALTER TABLE punch_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE work_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE timesheets ENABLE ROW LEVEL SECURITY;
ALTER TABLE approval_flows ENABLE ROW LEVEL SECURITY;
ALTER TABLE requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE import_jobs ENABLE ROW LEVEL SECURITY;
ALTER TABLE export_jobs ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE file_storage ENABLE ROW LEVEL SECURITY;

-- ============================================
-- RLSポリシー定義
-- ============================================

-- role_assignments
CREATE POLICY tenant_isolation_role_assignments ON role_assignments
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- employee_profiles
CREATE POLICY tenant_isolation_employee_profiles ON employee_profiles
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- departments
CREATE POLICY tenant_isolation_departments ON departments
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- business_hours
CREATE POLICY tenant_isolation_business_hours ON business_hours
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- rule_sets
CREATE POLICY tenant_isolation_rule_sets ON rule_sets
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- holiday_calendars
CREATE POLICY tenant_isolation_holiday_calendars ON holiday_calendars
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- punch_records
CREATE POLICY tenant_isolation_punch_records ON punch_records
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- work_sessions
CREATE POLICY tenant_isolation_work_sessions ON work_sessions
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- timesheets
CREATE POLICY tenant_isolation_timesheets ON timesheets
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- approval_flows
CREATE POLICY tenant_isolation_approval_flows ON approval_flows
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- requests
CREATE POLICY tenant_isolation_requests ON requests
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- import_jobs
CREATE POLICY tenant_isolation_import_jobs ON import_jobs
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- export_jobs
CREATE POLICY tenant_isolation_export_jobs ON export_jobs
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- audit_logs（SystemAdminは全テナント可視）
CREATE POLICY tenant_isolation_audit_logs ON audit_logs
    FOR ALL
    USING (
        tenant_id = get_current_tenant_id() 
        OR get_current_tenant_id() IS NULL
        -- SystemAdminの場合は全テナント可視（アプリケーションレベルで制御）
    );

-- notifications
CREATE POLICY tenant_isolation_notifications ON notifications
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- file_storage
CREATE POLICY tenant_isolation_file_storage ON file_storage
    FOR ALL
    USING (tenant_id = get_current_tenant_id() OR get_current_tenant_id() IS NULL);

-- ============================================
-- 注意事項
-- ============================================
-- 1. アプリケーションレベルでのテナントチェックも必須です
-- 2. SystemAdminの場合は、get_current_tenant_id()をNULLに設定することで全テナントアクセス可能
-- 3. 本番環境では、RLSポリシーをさらに厳格化することを推奨します
-- 4. パフォーマンステストを実施し、RLSによる影響を確認してください

