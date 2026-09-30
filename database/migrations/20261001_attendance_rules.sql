-- One row of attendance rules per school database. Nothing is filled into the
-- class register until the school turns `enabled` on.

CREATE TABLE IF NOT EXISTS `attendance_rules` (
    `id` TINYINT UNSIGNED NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `school_start` TIME NOT NULL DEFAULT '07:30:00',
    `late_grace` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `absent_cutoff` TIME NOT NULL DEFAULT '09:00:00',
    -- a day counts (and absences are written) only when the devices saw at
    -- least this many people, so a broken or unplugged device marks nobody absent
    `min_people_per_day` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    -- dates before this are never filled
    `active_from` DATE NULL,
    `updated_by` VARCHAR(100) NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `attendance_rules` (`id`) VALUES (1);

-- Marks class-register attendance cells written by the device rules. A
-- teacher's save clears it, and cleared cells are never changed automatically.
ALTER TABLE `register_class_attendance`
    ADD COLUMN IF NOT EXISTS `auto_source` VARCHAR(16) NULL DEFAULT NULL AFTER `status`;
