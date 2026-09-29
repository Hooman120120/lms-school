-- Attendance from any face / fingerprint / card system. Each school registers
-- its devices; a device pushes events (national code + time) to
-- api/attendance/push.php with its own key. Events are kept raw and never
-- edited; presence is worked out from them later.

CREATE TABLE IF NOT EXISTS `attendance_devices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('face', 'finger', 'card', 'other') NOT NULL DEFAULT 'other',
    `location` ENUM('gate', 'classroom') NOT NULL DEFAULT 'gate',
    `location_note` VARCHAR(100) NULL,
    -- sha256 of the device key; the key itself is shown once when created
    `key_hash` CHAR(64) NOT NULL,
    `key_hint` VARCHAR(8) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_seen_at` DATETIME NULL,
    `last_ip` VARCHAR(45) NULL,
    `created_by` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendance_devices_key` (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `device_id` INT UNSIGNED NOT NULL,
    `codemeli` VARCHAR(20) NOT NULL,
    -- NULL while the national code matches no student (kept so it can be fixed later)
    `idpersonal` INT NULL,
    `event_time` DATETIME NOT NULL,
    `direction` ENUM('in', 'out', 'unknown') NOT NULL DEFAULT 'unknown',
    `score` DECIMAL(6,2) NULL,
    `source` ENUM('push', 'import', 'simulator') NOT NULL DEFAULT 'push',
    `raw` TEXT NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- devices resend records; the same person/time/device is stored once
    UNIQUE KEY `uq_attendance_events` (`device_id`, `codemeli`, `event_time`),
    KEY `idx_attendance_events_person` (`idpersonal`, `event_time`),
    KEY `idx_attendance_events_time` (`event_time`),
    CONSTRAINT `fk_attendance_events_device` FOREIGN KEY (`device_id`)
        REFERENCES `attendance_devices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
