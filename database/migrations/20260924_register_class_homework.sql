-- Add homework score columns and persist its notification audience.
ALTER TABLE register_class_events
    MODIFY COLUMN type ENUM('0','1','2','3','4','5') NOT NULL,
    ADD COLUMN IF NOT EXISTS homework_notification_target VARCHAR(20) NOT NULL DEFAULT 'family'
        AFTER type;