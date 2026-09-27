-- ادغام شِمای دامپ myhonar.sql (HeidiSQL, ساختار بدون داده) با دیتابیس محلی.
-- دامپ و دیتابیس محلی هر دو ۱۳۲ جدول دارند؛ هیچ جدولی اضافه یا حذف نمی شود.
-- تفاوت واقعی: ۱۰ ستون در dbadmin، ۱۲ کلید خارجی، auto_increment جدول موقت و یک کامنت خراب.
-- ستون setting_public.institution_type محلی است و در دامپ نیست — دست نخورده می ماند.

SET NAMES utf8mb4;

-- ۱) ستون های تازهٔ dbadmin: سه فایل پیوست + هفت دسترسی معلم
ALTER TABLE `dbadmin`
    ADD COLUMN IF NOT EXISTS `document_file` varchar(255) DEFAULT NULL AFTER `adminpic`,
    ADD COLUMN IF NOT EXISTS `resume_file`   varchar(255) DEFAULT NULL AFTER `document_file`,
    ADD COLUMN IF NOT EXISTS `identity_file` varchar(255) DEFAULT NULL AFTER `resume_file`;

ALTER TABLE `dbadmin`
    ADD COLUMN IF NOT EXISTS `teacher_perm_courses`      tinyint(1) NOT NULL DEFAULT 1 AFTER `register_class_permission`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_online_class` tinyint(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_courses`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_students`     tinyint(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_online_class`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_attendance`   tinyint(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_students`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_homework`     tinyint(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_attendance`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_content`      tinyint(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_homework`,
    ADD COLUMN IF NOT EXISTS `teacher_perm_messages`     tinyint(1) NOT NULL DEFAULT 1 AFTER `teacher_perm_content`;

-- ۲) auto_increment که در نسخهٔ محلی جا افتاده بود (جدول خالی است)
ALTER TABLE `temporary_personal`
    MODIFY COLUMN `id` int(10) unsigned NOT NULL AUTO_INCREMENT;

-- ۲ب) هم سان سازی عرض نمایش (بدون اثر کارکردی؛ مقادیر فقط ۰ و ۱ هستند)
ALTER TABLE `dbadmin`
    MODIFY COLUMN `register_class_permission` tinyint(1) NOT NULL DEFAULT 0;

-- ۳) کامنت ستون type که در نسخهٔ محلی دوبار انکد شده بود
ALTER TABLE `register_class_events`
    MODIFY COLUMN `type` enum('0','1','2','3','4') NOT NULL
    COMMENT '0=حضور دستی، 1=نمره کلاسی، 2=حضور آنلاین، 3=توضیحات، 4=تراز آزمون آنلاین';

-- ۴) کلیدهای خارجی ماژول «ثبت کلاس» که در نسخهٔ محلی ساخته نشده بودند.
--    هر کلید یک دستور جداست تا در صورت اجرای دوباره فقط همان یکی خطای تکراری بدهد.
--    (MariaDB برای ADD FOREIGN KEY با IF NOT EXISTS نام ایندکس را می سنجد نه نام قید،
--     بنابراین به عمد از IF NOT EXISTS استفاده نشده است.)

ALTER TABLE `register_class_events`
    ADD CONSTRAINT `register_class_events_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_events`
    ADD CONSTRAINT `register_class_events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_events`
    ADD CONSTRAINT `register_class_events_ibfk_3` FOREIGN KEY (`reviewer`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_events`
    ADD CONSTRAINT `register_class_events_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_attendance`
    ADD CONSTRAINT `register_class_attendance_ibfk_1` FOREIGN KEY (`register_class_events_id`) REFERENCES `register_class_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_attendance`
    ADD CONSTRAINT `register_class_attendance_ibfk_2` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_attendance`
    ADD CONSTRAINT `register_class_attendance_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_discipline`
    ADD CONSTRAINT `register_class_discipline_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_discipline`
    ADD CONSTRAINT `register_class_discipline_ibfk_2` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_discipline`
    ADD CONSTRAINT `register_class_discipline_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_evaluation`
    ADD CONSTRAINT `register_class_evaluation_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_evaluation`
    ADD CONSTRAINT `register_class_evaluation_ibfk_3` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ۵) کلید خارجی سیزدهم عمداً اینجا نیست:
--    register_class_evaluation_ibfk_1  (register_class_events_id -> register_class_events.id)
--    هر سه سطر موجود این جدول به رویدادهای حذف شدهٔ ۲۵ و ۲۶ اشاره می کنند و افزودن کلید را
--    رد می کند. پس از حذف آن سه سطر آزمایشی، دستور زیر آن را کامل می کند:
--
--    DELETE FROM `register_class_evaluation`
--     WHERE `register_class_events_id` NOT IN (SELECT `id` FROM `register_class_events`);
--    ALTER TABLE `register_class_evaluation`
--        ADD CONSTRAINT `register_class_evaluation_ibfk_1` FOREIGN KEY (`register_class_events_id`)
--        REFERENCES `register_class_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
