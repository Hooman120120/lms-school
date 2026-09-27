ALTER TABLE school_notifications
    ADD COLUMN IF NOT EXISTS recipient_username VARCHAR(15) NULL AFTER dedupe_key,
    ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) NULL AFTER recipient_username,
    ADD COLUMN IF NOT EXISTS priority VARCHAR(40) NULL AFTER attachment,
    ADD COLUMN IF NOT EXISTS target_type VARCHAR(30) NOT NULL DEFAULT 'all' AFTER priority,
    ADD COLUMN IF NOT EXISTS target_value VARCHAR(100) NULL AFTER target_type,
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'published' AFTER target_value,
    ADD COLUMN IF NOT EXISTS published_at DATETIME NULL AFTER status;

SET @recipient_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'school_notifications'
      AND index_name = 'idx_school_notifications_recipient'
);
SET @recipient_index_sql = IF(
    @recipient_index_exists = 0,
    'ALTER TABLE school_notifications ADD INDEX idx_school_notifications_recipient (recipient_username, event_type, created_at)',
    'SELECT 1'
);
PREPARE recipient_index_statement FROM @recipient_index_sql;
EXECUTE recipient_index_statement;
DEALLOCATE PREPARE recipient_index_statement;