-- Role-aware notifications for the school LMS.
-- Notifications are stored once and filtered at read time by school role and
-- assigned grade. The actor is always excluded by the application query.

ALTER TABLE calendar_events
    ADD COLUMN IF NOT EXISTS is_academic TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '0: تقویم اجرایی، 1: تقویم آموزشی';

CREATE TABLE IF NOT EXISTS school_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    actor_admin_id INT(11) NULL,
    actor_username VARCHAR(12) NULL,
    codecourse INT(11) NULL,
    class_id INT(11) NULL,
    student_username VARCHAR(15) NULL,
    target_url VARCHAR(500) NULL,
    source_type VARCHAR(60) NULL,
    source_id VARCHAR(100) NULL,
    dedupe_key VARCHAR(190) NULL,
    recipient_username VARCHAR(15) NULL,
    attachment VARCHAR(255) NULL,
    priority VARCHAR(40) NULL,
    target_type VARCHAR(30) NOT NULL DEFAULT 'all',
    target_value VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_school_notifications_dedupe (dedupe_key),
    KEY idx_school_notifications_type_created (event_type, created_at),
    KEY idx_school_notifications_course_created (codecourse, created_at),
    KEY idx_school_notifications_actor (actor_username, actor_admin_id),
    KEY idx_school_notifications_recipient (recipient_username, event_type, created_at),
    CONSTRAINT fk_school_notifications_course
        FOREIGN KEY (codecourse) REFERENCES codtbl_course (codecourse)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_school_notifications_class
        FOREIGN KEY (class_id) REFERENCES classes (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_notification_reads (
    notification_id BIGINT UNSIGNED NOT NULL,
    admin_username VARCHAR(12) NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, admin_username),
    KEY idx_school_notification_reads_admin (admin_username, read_at),
    CONSTRAINT fk_school_notification_reads_notification
        FOREIGN KEY (notification_id) REFERENCES school_notifications (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    -- No FK to dbadmin.username: older installations use a different charset
    -- for that legacy column. Application writes still validate the session user.
    KEY idx_school_notification_reads_user_notification (admin_username, notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
