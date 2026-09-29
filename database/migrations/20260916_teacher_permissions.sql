ALTER TABLE `dbadmin`
    ADD COLUMN IF NOT EXISTS `teacher_perm_courses` TINYINT(1) NOT NULL DEFAULT 1 AFTER `school_role_id`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_online_class` TINYINT(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_courses`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_students` TINYINT(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_online_class`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_attendance` TINYINT(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_students`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_homework` TINYINT(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_attendance`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_content` TINYINT(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_homework`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_messages` TINYINT(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_content`;

-- DEFAULT 1 preserves the previous capabilities of existing teachers when
-- each column is first added. No UPDATE is used, so rerunning this migration
-- cannot overwrite permissions that an administrator has since changed.
