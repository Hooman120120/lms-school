CREATE TABLE IF NOT EXISTS `class_dars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `dars_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_dars_unique` (`class_id`, `dars_id`),
  KEY `idx_class_dars_dars` (`dars_id`),
  CONSTRAINT `class_dars_class_fk` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `class_dars_dars_fk` FOREIGN KEY (`dars_id`)
    REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Preserve the former course-wide behavior for existing data. From now on,
-- new and edited subjects are explicitly assigned through the management UI.
INSERT IGNORE INTO `class_dars` (`class_id`, `dars_id`)
SELECT c.id, d.id
FROM `classes` c
INNER JOIN `dbdars` d ON d.codecourse = c.codecourse;
