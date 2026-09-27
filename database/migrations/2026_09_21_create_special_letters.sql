-- =========================================================
-- Migration: Create Special Letters & Notifications
-- Date: 2026-09-21
-- =========================================================

-- =========================================================
-- جدول نامه‌های ویژه
-- =========================================================

CREATE TABLE IF NOT EXISTS special_letters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    student_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,

    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,

    is_read TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_special_letters_student (student_id),
    INDEX idx_special_letters_admin (admin_id),
    INDEX idx_special_letters_read (student_id, is_read),
    INDEX idx_special_letters_created (created_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- جدول اعلان‌ها
-- =========================================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id INT UNSIGNED NOT NULL,

    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,

    type VARCHAR(50) NOT NULL DEFAULT 'general',

    reference_id INT UNSIGNED DEFAULT NULL,

    is_read TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_user_read (user_id, is_read),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_reference (reference_id),
    INDEX idx_notifications_created (created_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;