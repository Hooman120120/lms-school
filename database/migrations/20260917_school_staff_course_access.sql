-- School staff roles and grade/course scoping for grade deputies and counselors.

INSERT INTO school_admin_roles (id, role_key, title, sort_order, is_active) VALUES
    (1, 'manager', 'مدیر', 1, 1),
    (9, 'general_deputy', 'معاون کل', 2, 1),
    (2, 'grade_deputy', 'معاون پایه', 3, 1),
    (10, 'executive_deputy', 'معاون اجرایی', 4, 1),
    (11, 'cultural_disciplinary_deputy', 'معاون فرهنگی انضباطی', 5, 1),
    (7, 'counselor', 'مشاور', 6, 1),
    (8, 'teacher', 'معلم', 7, 1)
ON DUPLICATE KEY UPDATE
    role_key = VALUES(role_key),
    title = VALUES(title),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

-- The former disciplinary and cultural deputy roles are both represented by
-- the new combined role. Technology and financial deputy records have no
-- unambiguous replacement, so they are left unassigned for an administrator
-- to classify explicitly instead of being silently given the wrong role.
UPDATE dbadmin SET school_role_id = 11 WHERE school_role_id IN (3, 4);
UPDATE dbadmin SET school_role_id = NULL WHERE school_role_id IN (5, 6);
UPDATE school_admin_roles SET is_active = 0 WHERE id IN (3, 4, 5, 6);

CREATE TABLE IF NOT EXISTS school_staff_courses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_username VARCHAR(12) NOT NULL,
    codecourse INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_school_staff_course (admin_username, codecourse),
    KEY idx_school_staff_courses_course (codecourse),
    CONSTRAINT fk_school_staff_courses_admin
        FOREIGN KEY (admin_username) REFERENCES dbadmin (username)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_school_staff_courses_course
        FOREIGN KEY (codecourse) REFERENCES codtbl_course (codecourse)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
