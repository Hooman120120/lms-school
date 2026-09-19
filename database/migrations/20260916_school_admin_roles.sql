CREATE TABLE IF NOT EXISTS school_admin_roles (
    id TINYINT UNSIGNED NOT NULL,
    role_key VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_school_admin_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO school_admin_roles (id, role_key, title, sort_order, is_active) VALUES
    (1, 'manager', 'مدیر', 1, 1),
    (2, 'grade_deputy', 'معاون پایه', 2, 1),
    (3, 'disciplinary_deputy', 'معاون انضباطی', 3, 1),
    (4, 'cultural_deputy', 'معاون فرهنگی/پرورشی', 4, 1),
    (5, 'technology_deputy', 'معاون فناوری', 5, 1),
    (6, 'financial_deputy', 'معاون مالی', 6, 1),
    (7, 'counselor', 'مشاور', 7, 1),
    (8, 'teacher', 'معلم', 8, 1)
ON DUPLICATE KEY UPDATE
    role_key = VALUES(role_key),
    title = VALUES(title),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

ALTER TABLE dbadmin
    ADD COLUMN IF NOT EXISTS school_role_id TINYINT UNSIGNED NULL
    COMMENT 'سمت سازمانی مدرسه؛ مرجع: school_admin_roles.id'
    AFTER adminrole;

CREATE INDEX IF NOT EXISTS idx_dbadmin_school_role_id
    ON dbadmin (school_role_id);

-- Only mappings with an unambiguous equivalent are migrated automatically.
UPDATE dbadmin
SET school_role_id = 1
WHERE adminrole = 0 AND school_role_id IS NULL;

UPDATE dbadmin
SET school_role_id = 8
WHERE adminrole = 3 AND school_role_id IS NULL;
