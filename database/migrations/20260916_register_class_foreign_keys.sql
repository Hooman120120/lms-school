ALTER TABLE `register_class_attendance` ADD CONSTRAINT `register_class_attendance_ibfk_1` FOREIGN KEY IF NOT EXISTS `register_class_attendance_ibfk_1` (`register_class_events_id`) REFERENCES `register_class_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_attendance` ADD CONSTRAINT `register_class_attendance_ibfk_2` FOREIGN KEY IF NOT EXISTS `register_class_attendance_ibfk_2` (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_attendance` ADD CONSTRAINT `register_class_attendance_ibfk_3` FOREIGN KEY IF NOT EXISTS `register_class_attendance_ibfk_3` (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_discipline` ADD CONSTRAINT `register_class_discipline_ibfk_1` FOREIGN KEY IF NOT EXISTS `register_class_discipline_ibfk_1` (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_discipline` ADD CONSTRAINT `register_class_discipline_ibfk_2` FOREIGN KEY IF NOT EXISTS `register_class_discipline_ibfk_2` (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_discipline` ADD CONSTRAINT `register_class_discipline_ibfk_3` FOREIGN KEY IF NOT EXISTS `register_class_discipline_ibfk_3` (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_evaluation` ADD CONSTRAINT `register_class_evaluation_ibfk_1` FOREIGN KEY IF NOT EXISTS `register_class_evaluation_ibfk_1` (`register_class_events_id`) REFERENCES `register_class_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_evaluation` ADD CONSTRAINT `register_class_evaluation_ibfk_2` FOREIGN KEY IF NOT EXISTS `register_class_evaluation_ibfk_2` (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_evaluation` ADD CONSTRAINT `register_class_evaluation_ibfk_3` FOREIGN KEY IF NOT EXISTS `register_class_evaluation_ibfk_3` (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `register_class_events` ADD CONSTRAINT `register_class_events_ibfk_1` FOREIGN KEY IF NOT EXISTS `register_class_events_ibfk_1` (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_events` ADD CONSTRAINT `register_class_events_ibfk_2` FOREIGN KEY IF NOT EXISTS `register_class_events_ibfk_2` (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_events` ADD CONSTRAINT `register_class_events_ibfk_3` FOREIGN KEY IF NOT EXISTS `register_class_events_ibfk_3` (`reviewer`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `register_class_events` ADD CONSTRAINT `register_class_events_ibfk_4` FOREIGN KEY IF NOT EXISTS `register_class_events_ibfk_4` (`teacher_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE;
