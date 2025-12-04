-- ============================================
-- 勤怠管理システム データベーススキーマ
-- PostgreSQL 12以上対応
-- ============================================

-- 拡張機能の有効化
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm"; -- 全文検索用

-- ============================================
-- 1. テナント（企業）
-- ============================================
CREATE TABLE tenants (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Tokyo',
    locale VARCHAR(10) NOT NULL DEFAULT 'ja',
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active, suspended, deleted
    logo_path VARCHAR(500),
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE,
    CONSTRAINT tenants_status_check CHECK (status IN ('active', 'suspended', 'deleted'))
);

CREATE INDEX idx_tenants_status ON tenants(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_tenants_deleted_at ON tenants(deleted_at) WHERE deleted_at IS NOT NULL;

-- ============================================
-- 2. ユーザー
-- ============================================
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    mfa_enabled BOOLEAN NOT NULL DEFAULT false,
    mfa_secret VARCHAR(255), -- TOTP秘密鍵
    backup_codes JSONB, -- バックアップコード配列
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active, inactive, suspended
    last_login_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE,
    CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive', 'suspended'))
);

CREATE INDEX idx_users_email ON users(email) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_status ON users(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_deleted_at ON users(deleted_at) WHERE deleted_at IS NOT NULL;

-- ============================================
-- 3. パスワードリセットトークン
-- ============================================
CREATE TABLE password_reset_tokens (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    used_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT password_reset_tokens_token_unique UNIQUE (token)
);

CREATE INDEX idx_password_reset_tokens_user_id ON password_reset_tokens(user_id);
CREATE INDEX idx_password_reset_tokens_token ON password_reset_tokens(token);
CREATE INDEX idx_password_reset_tokens_expires_at ON password_reset_tokens(expires_at) WHERE used_at IS NULL;

-- ============================================
-- 4. セッション
-- ============================================
CREATE TABLE sessions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT sessions_token_unique UNIQUE (token)
);

CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_tenant_id ON sessions(tenant_id);
CREATE INDEX idx_sessions_token ON sessions(token);
CREATE INDEX idx_sessions_expires_at ON sessions(expires_at) WHERE expires_at IS NOT NULL;

-- ============================================
-- 5. 役割割り当て
-- ============================================
CREATE TABLE role_assignments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE, -- NULL可（SystemAdminは全テナントにアクセス可能）
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) NOT NULL, -- SystemAdmin, CompanyAdmin, Professional, Manager, Employee
    scope_type VARCHAR(50), -- dept, employee, all
    scope_id UUID, -- 部署IDまたは従業員ID
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE,
    CONSTRAINT role_assignments_role_check CHECK (role IN ('SystemAdmin', 'CompanyAdmin', 'Professional', 'Manager', 'Employee')),
    CONSTRAINT role_assignments_scope_type_check CHECK (scope_type IN ('dept', 'employee', 'all') OR scope_type IS NULL)
);

CREATE INDEX idx_role_assignments_tenant_user ON role_assignments(tenant_id, user_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_role_assignments_tenant_role ON role_assignments(tenant_id, role) WHERE deleted_at IS NULL;
CREATE INDEX idx_role_assignments_scope ON role_assignments(scope_type, scope_id) WHERE deleted_at IS NULL AND scope_id IS NOT NULL;

-- ============================================
-- 6. 部署
-- ============================================
CREATE TABLE departments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50), -- 部署コード（テナント内で一意）
    parent_id UUID REFERENCES departments(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE,
    CONSTRAINT departments_tenant_code_unique UNIQUE (tenant_id, code)
);

CREATE INDEX idx_departments_tenant_id ON departments(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_departments_parent_id ON departments(parent_id) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX idx_departments_tenant_code_unique ON departments(tenant_id, code) WHERE deleted_at IS NULL AND code IS NOT NULL;

-- 循環参照防止のための制約（トリガーで実装）

-- ============================================
-- 7. 従業員プロファイル
-- ============================================
CREATE TABLE employee_profiles (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    employee_code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    dept_id UUID REFERENCES departments(id) ON DELETE SET NULL,
    employment_type VARCHAR(50) NOT NULL, -- full_time, part_time, contract, etc.
    hire_date DATE NOT NULL,
    leave_date DATE,
    work_location_tz VARCHAR(50) DEFAULT 'Asia/Tokyo',
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active, on_leave, retired
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE,
    CONSTRAINT employee_profiles_tenant_code_unique UNIQUE (tenant_id, employee_code),
    CONSTRAINT employee_profiles_status_check CHECK (status IN ('active', 'on_leave', 'retired'))
);

CREATE INDEX idx_employee_profiles_tenant_id ON employee_profiles(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_employee_profiles_user_id ON employee_profiles(user_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_employee_profiles_dept_id ON employee_profiles(dept_id) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX idx_employee_profiles_tenant_code_unique ON employee_profiles(tenant_id, employee_code) WHERE deleted_at IS NULL;
CREATE INDEX idx_employee_profiles_status ON employee_profiles(status) WHERE deleted_at IS NULL;

-- ============================================
-- 8. 営業時間
-- ============================================
CREATE TABLE business_hours (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    scope_type VARCHAR(50) NOT NULL, -- company, dept, employee
    scope_id UUID, -- 部署IDまたは従業員ID（companyの場合はNULL）
    weekday INTEGER NOT NULL, -- 0=日曜日, 1=月曜日, ..., 6=土曜日
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_policy_id UUID, -- 休憩ポリシーID（将来拡張用）
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT business_hours_scope_type_check CHECK (scope_type IN ('company', 'dept', 'employee')),
    CONSTRAINT business_hours_weekday_check CHECK (weekday >= 0 AND weekday <= 6)
);

CREATE INDEX idx_business_hours_tenant_scope ON business_hours(tenant_id, scope_type, scope_id);
CREATE INDEX idx_business_hours_weekday ON business_hours(weekday);

-- ============================================
-- 9. 就業ルールセット
-- ============================================
CREATE TABLE rule_sets (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    effective_from DATE NOT NULL,
    effective_to DATE,
    config JSONB NOT NULL, -- ルール設定（丸め、残業計算等）
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT rule_sets_effective_period_check CHECK (effective_to IS NULL OR effective_to >= effective_from)
);

CREATE INDEX idx_rule_sets_tenant_id ON rule_sets(tenant_id);
CREATE INDEX idx_rule_sets_effective_period ON rule_sets(effective_from, effective_to);
CREATE INDEX idx_rule_sets_tenant_name ON rule_sets(tenant_id, name);

-- ============================================
-- 10. 祝日カレンダー
-- ============================================
CREATE TABLE holiday_calendars (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    scope_type VARCHAR(50) NOT NULL, -- country, company, dept, employee
    scope_id UUID, -- 部署IDまたは従業員ID（country/companyの場合はNULL）
    date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- holiday, company_holiday, special
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT holiday_calendars_scope_type_check CHECK (scope_type IN ('country', 'company', 'dept', 'employee')),
    CONSTRAINT holiday_calendars_type_check CHECK (type IN ('holiday', 'company_holiday', 'special'))
);

CREATE INDEX idx_holiday_calendars_tenant_scope ON holiday_calendars(tenant_id, scope_type, scope_id);
CREATE INDEX idx_holiday_calendars_date ON holiday_calendars(date);
CREATE INDEX idx_holiday_calendars_tenant_date ON holiday_calendars(tenant_id, date);

-- ============================================
-- 11. 打刻記録
-- ============================================
CREATE TABLE punch_records (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    employee_id UUID NOT NULL REFERENCES employee_profiles(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL, -- in, out, break_in, break_out
    occurred_at TIMESTAMP WITH TIME ZONE NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'web', -- web, api, mobile, etc.
    device VARCHAR(255),
    note TEXT,
    proxy_user_id UUID REFERENCES users(id) ON DELETE SET NULL, -- 代理打刻者
    proxy_reason TEXT, -- 代理打刻理由
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT punch_records_type_check CHECK (type IN ('in', 'out', 'break_in', 'break_out')),
    CONSTRAINT punch_records_tenant_employee_type_occurred_unique UNIQUE (tenant_id, employee_id, type, occurred_at)
);

CREATE INDEX idx_punch_records_tenant_employee ON punch_records(tenant_id, employee_id);
CREATE INDEX idx_punch_records_occurred_at ON punch_records(occurred_at);
CREATE INDEX idx_punch_records_tenant_occurred ON punch_records(tenant_id, occurred_at);
CREATE INDEX idx_punch_records_proxy_user ON punch_records(proxy_user_id) WHERE proxy_user_id IS NOT NULL;
-- 冪等キー用のユニークインデックス（上記のUNIQUE制約で実現）

-- ============================================
-- 12. 勤務セッション
-- ============================================
CREATE TABLE work_sessions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    employee_id UUID NOT NULL REFERENCES employee_profiles(id) ON DELETE CASCADE,
    start_at TIMESTAMP WITH TIME ZONE NOT NULL,
    end_at TIMESTAMP WITH TIME ZONE,
    total_break_minutes INTEGER NOT NULL DEFAULT 0,
    work_minutes INTEGER, -- 実働時間（分）
    anomalies JSONB, -- 異常情報（未打刻、順序エラー等）
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT work_sessions_end_after_start_check CHECK (end_at IS NULL OR end_at >= start_at)
);

CREATE INDEX idx_work_sessions_tenant_employee ON work_sessions(tenant_id, employee_id);
CREATE INDEX idx_work_sessions_start_at ON work_sessions(start_at);
CREATE INDEX idx_work_sessions_tenant_start ON work_sessions(tenant_id, start_at);
CREATE INDEX idx_work_sessions_employee_date ON work_sessions(employee_id, start_at);

-- ============================================
-- 13. タイムシート
-- ============================================
CREATE TABLE timesheets (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    employee_id UUID NOT NULL REFERENCES employee_profiles(id) ON DELETE CASCADE,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft', -- draft, submitted, approved, rejected
    totals JSONB, -- 集計結果（実働時間、残業時間等）
    submitted_at TIMESTAMP WITH TIME ZONE,
    approved_by UUID REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP WITH TIME ZONE,
    rejection_reason TEXT,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT timesheets_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'rejected')),
    CONSTRAINT timesheets_period_check CHECK (period_to >= period_from)
);

CREATE INDEX idx_timesheets_tenant_employee ON timesheets(tenant_id, employee_id);
CREATE INDEX idx_timesheets_period ON timesheets(period_from, period_to);
CREATE INDEX idx_timesheets_status ON timesheets(status);
CREATE INDEX idx_timesheets_tenant_period ON timesheets(tenant_id, period_from, period_to);

-- ============================================
-- 14. 承認フロー
-- ============================================
CREATE TABLE approval_flows (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- request, timesheet
    steps JSONB NOT NULL, -- 承認ステップの配列
    active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT approval_flows_type_check CHECK (type IN ('request', 'timesheet'))
);

CREATE INDEX idx_approval_flows_tenant_type ON approval_flows(tenant_id, type);
CREATE INDEX idx_approval_flows_active ON approval_flows(active) WHERE active = true;

-- ============================================
-- 15. 申請
-- ============================================
CREATE TABLE requests (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    employee_id UUID NOT NULL REFERENCES employee_profiles(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- edit, overtime, leave, others
    target_date DATE NOT NULL,
    payload JSONB NOT NULL, -- 申請内容の詳細
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, approved, rejected
    attachment_paths JSONB, -- 添付ファイルパスの配列
    approver_id UUID REFERENCES users(id) ON DELETE SET NULL,
    decided_at TIMESTAMP WITH TIME ZONE,
    reason TEXT NOT NULL, -- 申請理由
    rejection_reason TEXT, -- 差戻し理由
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT requests_type_check CHECK (type IN ('edit', 'overtime', 'leave', 'others')),
    CONSTRAINT requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))
);

CREATE INDEX idx_requests_tenant_employee ON requests(tenant_id, employee_id);
CREATE INDEX idx_requests_target_date ON requests(target_date);
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_requests_approver ON requests(approver_id) WHERE approver_id IS NOT NULL;
CREATE INDEX idx_requests_tenant_status ON requests(tenant_id, status);

-- ============================================
-- 16. インポートジョブ
-- ============================================
CREATE TABLE import_jobs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- employees, punches
    params JSONB NOT NULL, -- インポートパラメータ
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, processing, completed, failed
    file_path VARCHAR(500) NOT NULL,
    result JSONB, -- インポート結果（成功/失敗行数等）
    error_message TEXT,
    started_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT import_jobs_type_check CHECK (type IN ('employees', 'punches')),
    CONSTRAINT import_jobs_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed'))
);

CREATE INDEX idx_import_jobs_tenant_id ON import_jobs(tenant_id);
CREATE INDEX idx_import_jobs_status ON import_jobs(status);
CREATE INDEX idx_import_jobs_created_at ON import_jobs(created_at);

-- ============================================
-- 17. エクスポートジョブ
-- ============================================
CREATE TABLE export_jobs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- employees, attendance, summary, punches
    params JSONB NOT NULL, -- エクスポートパラメータ
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, processing, completed, failed
    file_path VARCHAR(500),
    result JSONB, -- エクスポート結果
    error_message TEXT,
    started_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT export_jobs_type_check CHECK (type IN ('employees', 'attendance', 'summary', 'punches')),
    CONSTRAINT export_jobs_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed'))
);

CREATE INDEX idx_export_jobs_tenant_id ON export_jobs(tenant_id);
CREATE INDEX idx_export_jobs_status ON export_jobs(status);
CREATE INDEX idx_export_jobs_created_at ON export_jobs(created_at);

-- ============================================
-- 18. 監査ログ
-- ============================================
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID REFERENCES tenants(id) ON DELETE SET NULL, -- SystemAdmin操作の場合はNULL
    actor_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL, -- user.login, employee.create, etc.
    entity VARCHAR(50) NOT NULL, -- user, employee, punch, etc.
    entity_id UUID,
    before JSONB, -- 変更前の値
    after JSONB, -- 変更後の値
    ip_address INET,
    user_agent TEXT,
    occurred_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_logs_tenant_id ON audit_logs(tenant_id) WHERE tenant_id IS NOT NULL;
CREATE INDEX idx_audit_logs_actor_user ON audit_logs(actor_user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_entity ON audit_logs(entity, entity_id);
CREATE INDEX idx_audit_logs_occurred_at ON audit_logs(occurred_at);
CREATE INDEX idx_audit_logs_tenant_occurred ON audit_logs(tenant_id, occurred_at) WHERE tenant_id IS NOT NULL;

-- ============================================
-- 19. 通知
-- ============================================
CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- request_approved, request_rejected, reminder, etc.
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    channel VARCHAR(20) NOT NULL, -- email, in_app
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT notifications_channel_check CHECK (channel IN ('email', 'in_app'))
);

CREATE INDEX idx_notifications_tenant_user ON notifications(tenant_id, user_id);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, read_at);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);
CREATE INDEX idx_notifications_unread ON notifications(user_id, created_at) WHERE read_at IS NULL;

-- ============================================
-- 20. ファイルストレージ
-- ============================================
CREATE TABLE file_storage (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    entity_type VARCHAR(50) NOT NULL, -- tenant_logo, request_attachment, etc.
    entity_id UUID, -- 関連エンティティのID
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL, -- バイト単位
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT file_storage_entity_type_check CHECK (entity_type IN ('tenant_logo', 'request_attachment', 'other'))
);

CREATE INDEX idx_file_storage_tenant_id ON file_storage(tenant_id);
CREATE INDEX idx_file_storage_entity ON file_storage(entity_type, entity_id);
CREATE INDEX idx_file_storage_uploaded_by ON file_storage(uploaded_by);

-- ============================================
-- タイムスタンプ更新用のトリガー関数
-- ============================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- updated_atを自動更新するテーブルにトリガーを設定
CREATE TRIGGER update_tenants_updated_at BEFORE UPDATE ON tenants
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_role_assignments_updated_at BEFORE UPDATE ON role_assignments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_departments_updated_at BEFORE UPDATE ON departments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_employee_profiles_updated_at BEFORE UPDATE ON employee_profiles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_business_hours_updated_at BEFORE UPDATE ON business_hours
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_rule_sets_updated_at BEFORE UPDATE ON rule_sets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_holiday_calendars_updated_at BEFORE UPDATE ON holiday_calendars
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_punch_records_updated_at BEFORE UPDATE ON punch_records
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_work_sessions_updated_at BEFORE UPDATE ON work_sessions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_timesheets_updated_at BEFORE UPDATE ON timesheets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_approval_flows_updated_at BEFORE UPDATE ON approval_flows
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_requests_updated_at BEFORE UPDATE ON requests
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_import_jobs_updated_at BEFORE UPDATE ON import_jobs
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_export_jobs_updated_at BEFORE UPDATE ON export_jobs
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- 部署の循環参照防止用の関数
-- ============================================
CREATE OR REPLACE FUNCTION check_department_circular_reference()
RETURNS TRIGGER AS $$
DECLARE
    current_id UUID;
    found_parent_id UUID;
    depth INTEGER := 0;
BEGIN
    -- 自分自身を親に設定しようとしている場合
    IF NEW.parent_id = NEW.id THEN
        RAISE EXCEPTION '部署は自分自身を親に設定できません';
    END IF;
    
    -- 親がNULLの場合は問題なし
    IF NEW.parent_id IS NULL THEN
        RETURN NEW;
    END IF;
    
    -- 循環参照チェック（最大5階層まで）
    current_id := NEW.parent_id;
    WHILE current_id IS NOT NULL AND depth < 5 LOOP
        SELECT d.parent_id INTO found_parent_id FROM departments d WHERE d.id = current_id AND d.deleted_at IS NULL;
        
        -- 循環参照を検出
        IF found_parent_id = NEW.id THEN
            RAISE EXCEPTION '循環参照が検出されました。部署の階層構造を確認してください。';
        END IF;
        
        current_id := found_parent_id;
        depth := depth + 1;
    END LOOP;
    
    -- 最大階層数を超えた場合
    IF depth >= 5 THEN
        RAISE EXCEPTION '部署の階層は最大5階層までです';
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER check_department_circular_reference_trigger
    BEFORE INSERT OR UPDATE ON departments
    FOR EACH ROW
    WHEN (NEW.parent_id IS NOT NULL)
    EXECUTE FUNCTION check_department_circular_reference();

-- ============================================
-- コメント（テーブル説明）
-- ============================================
COMMENT ON TABLE tenants IS 'テナント（企業）情報';
COMMENT ON TABLE users IS 'ユーザーアカウント情報';
COMMENT ON TABLE password_reset_tokens IS 'パスワードリセットトークン';
COMMENT ON TABLE sessions IS 'セッション管理';
COMMENT ON TABLE role_assignments IS '役割割り当て（RBAC）';
COMMENT ON TABLE departments IS '部署情報（階層構造対応）';
COMMENT ON TABLE employee_profiles IS '従業員プロファイル';
COMMENT ON TABLE business_hours IS '営業時間設定';
COMMENT ON TABLE rule_sets IS '就業ルールセット（バージョン管理）';
COMMENT ON TABLE holiday_calendars IS '祝日カレンダー';
COMMENT ON TABLE punch_records IS '打刻記録（冪等性保証）';
COMMENT ON TABLE work_sessions IS '勤務セッション（1日の勤務単位）';
COMMENT ON TABLE timesheets IS 'タイムシート（日/週/月次集計）';
COMMENT ON TABLE approval_flows IS '承認フロー定義';
COMMENT ON TABLE requests IS '申請（修正申請、残業申請等）';
COMMENT ON TABLE import_jobs IS 'CSVインポートジョブ';
COMMENT ON TABLE export_jobs IS 'CSVエクスポートジョブ';
COMMENT ON TABLE audit_logs IS '監査ログ（改ざん防止）';
COMMENT ON TABLE notifications IS '通知（メール/アプリ内）';
COMMENT ON TABLE file_storage IS 'ファイルストレージ（ロゴ、添付ファイル等）';

