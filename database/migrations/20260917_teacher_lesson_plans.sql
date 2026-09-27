CREATE TABLE IF NOT EXISTS `teacher_lesson_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `class_id` int(11) NOT NULL,
  `dars_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `plan_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_lesson_plan_unique` (`teacher_username`, `class_id`, `dars_id`, `lesson_id`, `plan_date`),
  KEY `idx_teacher_lesson_plans_date` (`plan_date`),
  KEY `idx_teacher_lesson_plans_class` (`class_id`),
  KEY `idx_teacher_lesson_plans_dars` (`dars_id`),
  KEY `idx_teacher_lesson_plans_lesson` (`lesson_id`),
  CONSTRAINT `tlp_teacher_fk` FOREIGN KEY (`teacher_username`)
    REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tlp_class_fk` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tlp_dars_fk` FOREIGN KEY (`dars_id`)
    REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tlp_lesson_fk` FOREIGN KEY (`lesson_id`)
    REFERENCES `dblesson` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `teacher_lesson_plans`
  ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL AFTER `plan_date`;
