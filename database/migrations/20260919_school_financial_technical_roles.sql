-- Financial/technical school roles and granular staff-operation permissions.

INSERT INTO school_admin_roles (id, role_key, title, sort_order, is_active) VALUES
    (5, 'technical_deputy', 'معاون فنی', 7, 1),
    (6, 'financial_deputy', 'معاون مالی', 8, 1)
ON DUPLICATE KEY UPDATE
    role_key = VALUES(role_key),
    title = VALUES(title),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

UPDATE school_admin_roles SET sort_order = 6 WHERE id = 7;
UPDATE school_admin_roles SET sort_order = 9 WHERE id = 8;

ALTER TABLE dbadmin
    ADD COLUMN IF NOT EXISTS permission_staff_view TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS permission_staff_documents TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS permission_insurance TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS permission_monthly_payment TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS permission_assigned_tasks TINYINT(1) NOT NULL DEFAULT 0;

-- Existing accounts receive the same safe role defaults as newly saved forms.
UPDATE dbadmin a
INNER JOIN school_admin_roles r ON r.id = a.school_role_id
SET a.adminrole = 1,
    a.permision_viewbill = 0,
    a.permision_actfactor = 0,
    a.permision_notpay = 0,
    a.permision_actwallet = 0,
    a.permision_bill = 0,
    a.permision_financial_export = 0,
    a.permision_viewwallet = 0,
    a.installment_permission = 0,
    a.permission_monthly_payment = 0
WHERE r.role_key = 'counselor';

UPDATE dbadmin a
INNER JOIN school_admin_roles r ON r.id = a.school_role_id
SET a.permision_viewbill = 1,
    a.adminrole = 55,
    a.permission_staff_view = 1,
    a.permission_staff_documents = 1,
    a.permission_insurance = 1,
    a.permission_monthly_payment = 1
WHERE r.role_key = 'financial_deputy';

UPDATE dbadmin a
INNER JOIN school_admin_roles r ON r.id = a.school_role_id
SET a.permission_assigned_tasks = 1,
    a.adminrole = 54
WHERE r.role_key = 'technical_deputy';

CREATE TABLE IF NOT EXISTS school_staff_insurance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_username VARCHAR(50) NOT NULL,
    provider VARCHAR(150) NULL,
    policy_number VARCHAR(100) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_staff_insurance_user (admin_username),
    KEY idx_staff_insurance_status (status, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_staff_monthly_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_username VARCHAR(50) NOT NULL,
    payment_month CHAR(7) NOT NULL COMMENT 'Jalali YYYY/MM',
    amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    paid_at DATE NULL,
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_monthly_payment (admin_username, payment_month),
    KEY idx_staff_monthly_payment_month (payment_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_assigned_tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    assigned_to VARCHAR(50) NULL,
    due_date DATETIME NULL,
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    status ENUM('pending','in_progress','done','cancelled') NOT NULL DEFAULT 'pending',
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_tasks_assignee (assigned_to, status),
    KEY idx_school_tasks_due (due_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
