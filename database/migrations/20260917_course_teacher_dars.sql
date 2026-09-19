CREATE TABLE IF NOT EXISTS `course_teacher_dars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `dars_id` int(11) NOT NULL,
  `teacher_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_dars_teacher_unique` (`class_id`, `dars_id`, `teacher_username`),
  KEY `idx_course_teacher_dars_dars` (`dars_id`),
  KEY `idx_course_teacher_dars_creator` (`created_by`),
  KEY `idx_course_teacher_dars_assignment` (`class_id`, `teacher_username`),
  CONSTRAINT `ctd_assignment_fk` FOREIGN KEY (`class_id`, `teacher_username`)
    REFERENCES `course_teacher` (`class_id`, `teacher_username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ctd_dars_fk` FOREIGN KEY (`dars_id`)
    REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ctd_creator_fk` FOREIGN KEY (`created_by`)
    REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
