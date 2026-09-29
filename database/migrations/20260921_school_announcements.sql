CREATE TABLE IF NOT EXISTS `school_announcements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

    `title` VARCHAR(255) NOT NULL,

    `type` ENUM('announcement', 'circular')
        NOT NULL DEFAULT 'announcement',

    `content` TEXT NOT NULL,

    `attachment` VARCHAR(255) NULL,

    `status` ENUM('draft', 'published')
        NOT NULL DEFAULT 'draft',

    `target_type` ENUM('all', 'grade', 'class')
        NOT NULL DEFAULT 'all',

    `target_value` VARCHAR(100) NULL,

    `created_by` INT UNSIGNED NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    `published_at` DATETIME NULL,

    PRIMARY KEY (`id`),

    INDEX `idx_school_announcements_status` (`status`),
    INDEX `idx_school_announcements_type` (`type`),
    INDEX `idx_school_announcements_created_at` (`created_at`)
);