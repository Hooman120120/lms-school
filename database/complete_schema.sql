
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `active_calls` (
  `cuid` varchar(255) NOT NULL,
  `exten` varchar(10) NOT NULL,
  `caller_number` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'ringing',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`cuid`),
  KEY `exten_idx` (`exten`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `active_event_answers` (
  `event_id` int(11) NOT NULL,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `option_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`,`username`),
  KEY `fk_active_event_answers_dbpersonal_codemeli` (`username`),
  KEY `fk_active_event_answers_dbadmin_idadmin` (`created_by`),
  KEY `fk_active_event_answers_active_event_options_id` (`option_id`),
  CONSTRAINT `fk_active_event_answers_active_event_options_id` FOREIGN KEY (`option_id`) REFERENCES `active_event_options` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_active_event_answers_calendar_events_id` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_active_event_answers_dbadmin_idadmin` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_active_event_answers_dbpersonal_codemeli` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `active_event_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `events_option` text NOT NULL,
  `needs_reason` tinyint(4) NOT NULL COMMENT 'آپشن مورد نظر دلیل میخواد یا نه',
  PRIMARY KEY (`id`),
  KEY `active_event_options_calendar_events_id` (`event_id`),
  CONSTRAINT `active_event_options_calendar_events_id` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `addon_class` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `addon_class_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `addon_class_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `dbaddon` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=365 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `admin_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_ticket_id` bigint(20) unsigned NOT NULL,
  `sender_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seen_at` datetime DEFAULT NULL,
  `seen_by` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL COMMENT 'username who marked as seen',
  PRIMARY KEY (`id`),
  KEY `idx_admin_ticket_created_at` (`admin_ticket_id`,`created_at`),
  KEY `idx_sender_username` (`sender_username`),
  KEY `idx_seen_at` (`seen_at`),
  KEY `seen_by` (`seen_by`),
  CONSTRAINT `admin_messages_ibfk_1` FOREIGN KEY (`sender_username`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `admin_messages_ibfk_2` FOREIGN KEY (`seen_by`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admin_messages_ticket` FOREIGN KEY (`admin_ticket_id`) REFERENCES `admin_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `admin_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `ticket_creator` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `ticket_assigned_to` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `student_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `first_suporter_assigned_at` datetime DEFAULT NULL COMMENT 'Batch timestamp for courses from dbcourse.first_suporter_assigned_at',
  `ticket_status` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0=open,1=done,2=check needed,3=ongoing\r\n,4=disagree',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_ticket_status` (`ticket_status`),
  KEY `idx_ticket_creator` (`ticket_creator`),
  KEY `idx_ticket_assigned_to` (`ticket_assigned_to`),
  KEY `idx_updated_at` (`updated_at`),
  KEY `student_username` (`student_username`),
  CONSTRAINT `admin_tickets_ibfk_1` FOREIGN KEY (`student_username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `admin_tickets_ibfk_2` FOREIGN KEY (`ticket_assigned_to`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `admin_tickets_ibfk_3` FOREIGN KEY (`ticket_creator`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=509 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `api_request_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requested_by` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `target_type` varchar(50) NOT NULL COMMENT 'نوع موجودیت هدف: course, class, user, quiz, certificate, ...',
  `target_id` varchar(12) NOT NULL COMMENT 'شناسه آن موجودیت در LMS',
  `event_type` varchar(50) NOT NULL COMMENT 'نوع عملیات: create_course, register_user_to_course, sync_user, ...',
  `request_method` varchar(10) NOT NULL,
  `request_url` text NOT NULL,
  `request_headers` text DEFAULT NULL,
  `request_body` text DEFAULT NULL,
  `response_status_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `is_success` tinyint(1) NOT NULL DEFAULT 0,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_retry_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_success` (`is_success`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_event_type` (`event_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1943 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `autodialer_blacklist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(32) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phone` (`phone`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_created_by_created_at` (`created_by`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `autodialer_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor` varchar(50) NOT NULL,
  `action` varchar(300) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entity_type_created_at` (`entity_type`,`created_at`),
  KEY `idx_actor_created_at` (`actor`,`created_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_entity_actor_created` (`entity_type`,`actor`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=419 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `blacklist_ip` (
  `id_blackList_ip` int(11) NOT NULL AUTO_INCREMENT,
  `ip_blackList_ip` varchar(20) NOT NULL,
  `os_blackList_ip` varchar(255) DEFAULT NULL,
  `date_blacklist_ip` datetime NOT NULL,
  PRIMARY KEY (`id_blackList_ip`),
  UNIQUE KEY `ip_blackList_ip` (`ip_blackList_ip`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `bulk_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `filename` varchar(255) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `bulk_uploads_ibfk_1` FOREIGN KEY (`deleted_by`) REFERENCES `dbadmin` (`username`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_academic_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL COMMENT 'شناسه دوره',
  `class_id` int(11) NOT NULL COMMENT 'شناسه کلاس',
  `teacher_id` int(11) NOT NULL COMMENT 'شناسه استاد (idadmin از dbadmin)',
  `lesson_id` int(11) NOT NULL COMMENT 'شناسه درس (id از dbdars)',
  `delivery_type` enum('online','offline','animation') NOT NULL DEFAULT 'online' COMMENT 'نوع برگزاری',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `course_id` (`course_id`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `fk_acad_event` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_admin_role_targets` (
  `event_id` int(11) NOT NULL,
  `adminrole` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`,`adminrole`),
  CONSTRAINT `fk_calendar_admin_role_targets__calendar_events_id` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_admin_user_targets` (
  `event_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`,`admin_id`),
  KEY `fk_calendar_admin_user_targets__dbadmin_idadmin` (`admin_id`),
  CONSTRAINT `fk_calendar_admin_user_targets__calendar_events_id` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_admin_user_targets__dbadmin_idadmin` FOREIGN KEY (`admin_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_course_targets` (
  `event_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `is_academic` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`course_id`,`event_id`),
  KEY `fk_calendar_course_targets_calendar_events_id` (`event_id`),
  CONSTRAINT `fk_calendar_course_targets_calendar_events_id` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_course_targets_codtbl_course_codecourse` FOREIGN KEY (`course_id`) REFERENCES `codtbl_course` (`idcourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_event_teacher_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cetg` (`event_id`,`group_id`),
  KEY `idx_cetg_group` (`group_id`),
  CONSTRAINT `fk_cetg_group` FOREIGN KEY (`group_id`) REFERENCES `teacher_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `personal_note` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `calendar_groups_id` int(11) DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `target_type` enum('admin','user','both') NOT NULL,
  `event_type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 = passive \r\n1 = active \r\n',
  `visibility` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 = everyone sees no changes.\r\n1 = users/student cant see the event.',
  `overlap` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'if event is considered overlap it needs to be checked against other overlapping events to insert so the timelines would not disturb eachother. \r\n0 = not overlapping event\r\n1 = overlapping event',
  `is_academic` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: اجرایی, 1: آموزشی',
  PRIMARY KEY (`id`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_target_type` (`target_type`),
  KEY `fk_calendar_events_dbadmin_adminid` (`creator_id`),
  KEY `fk_calendar_events_update_dbadmin_adminid` (`updated_by_id`),
  KEY `fk_calendar_events_calendar_groups_id` (`calendar_groups_id`),
  CONSTRAINT `fk_calendar_events_calendar_groups_id` FOREIGN KEY (`calendar_groups_id`) REFERENCES `calendar_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_events_dbadmin_adminid` FOREIGN KEY (`creator_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_events_update_dbadmin_adminid` FOREIGN KEY (`updated_by_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calendar_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `color` char(7) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_calendar_groups_dbadmin_idadmin_create` (`created_by`),
  KEY `fk_calendar_groups_dbadmin_idadmin_update` (`updated_by`),
  CONSTRAINT `fk_calendar_groups_dbadmin_idadmin_create` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_groups_dbadmin_idadmin_update` FOREIGN KEY (`updated_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `calls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cuid` varchar(64) NOT NULL,
  `direction` enum('in','out') NOT NULL,
  `exten` smallint(5) unsigned NOT NULL COMMENT 'if exten = 0 caller didnt get connected to any operator (got stuck behind office number)',
  `remote_number` varchar(20) NOT NULL,
  `codemeli` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `talk_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `answered` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cuid` (`cuid`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_exten_date` (`exten`,`created_at`),
  KEY `idx_codemeli_date` (`codemeli`,`created_at`),
  KEY `idx_remote_date` (`remote_number`,`created_at`),
  KEY `idx_answered` (`answered`,`created_at`),
  KEY `idx_codemeli` (`codemeli`),
  CONSTRAINT `fk_calls_codemeli_dbpersonal_codemeli` FOREIGN KEY (`codemeli`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17409 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `cancel_student_operator_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_username` varchar(15) NOT NULL COMMENT 'username دانش آموز',
  `requested_by` varchar(15) NOT NULL COMMENT 'username اپراتور ارسال کننده',
  `requested_mode` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '1=cancel,0=activate',
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0=pending,1=approved,2=rejected',
  `reviewed_by` varchar(15) DEFAULT NULL COMMENT 'username ادمین بررسی کننده',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'idadmin',
  `reviewed_at` datetime DEFAULT NULL COMMENT 'زمان بررسی درخواست',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reject_reason` varchar(255) DEFAULT NULL,
  `seen_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cancel_student_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_cancel_student_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `dbadmin` (`idadmin`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=329 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `card_server` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `card_num` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `card_num` (`card_num`),
  CONSTRAINT `card_server_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `card_server_ibfk_2` FOREIGN KEY (`card_num`) REFERENCES `db_card` (`card_num`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `change_expert_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `requested_by` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `suggested_admin` varchar(12) NOT NULL,
  `status` enum('pending','accepted','rejected','') NOT NULL DEFAULT 'pending',
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `checkjoin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codemeli` varchar(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=692 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `checkout` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL COMMENT 'نام کاربری',
  `coursecode` int(11) NOT NULL COMMENT 'کد دوره',
  `fee` int(11) NOT NULL COMMENT 'مبلغ',
  `price_type` enum('with','without') DEFAULT 'with',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `coursecode` (`coursecode`),
  CONSTRAINT `checkout_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checkout_ibfk_2` FOREIGN KEY (`coursecode`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `checkout_collague` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `useradder` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'همکار ایجاد کننده',
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'دانش آموز',
  `codecourse` int(11) NOT NULL COMMENT 'کد دوره',
  `fee` bigint(20) NOT NULL COMMENT 'مبلغ دوره',
  PRIMARY KEY (`id`),
  KEY `checkout_collague_ibfk_1` (`codecourse`),
  KEY `checkout_collague_ibfk_2` (`useradder`),
  KEY `checkout_collague_ibfk_3` (`username`),
  CONSTRAINT `checkout_collague_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checkout_collague_ibfk_2` FOREIGN KEY (`useradder`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checkout_collague_ibfk_3` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8 COLLATE=utf8_persian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `city` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `state_id` (`state_id`),
  CONSTRAINT `city_ibfk_2` FOREIGN KEY (`state_id`) REFERENCES `state` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1031 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `class_dars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `dars_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_dars_unique` (`class_id`,`dars_id`),
  KEY `idx_class_dars_dars` (`dars_id`),
  CONSTRAINT `class_dars_class_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `class_dars_dars_fk` FOREIGN KEY (`dars_id`) REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `class_lesson` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `class_lesson_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `class_lesson_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `dblesson` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=390 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `class_movie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `movie_id` (`movie_id`),
  CONSTRAINT `class_movie_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `class_movie_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `dbmovie` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1054 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `is_active` tinyint(4) NOT NULL,
  `act_class` tinyint(4) NOT NULL DEFAULT 0,
  `act_ostadclass` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'فعالسازی کلاس برای استاد',
  `act_general` tinyint(4) NOT NULL DEFAULT 0,
  `act_quiz` tinyint(4) NOT NULL DEFAULT 0,
  `link_eita` varchar(255) NOT NULL,
  `ugid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `codecourse` (`codecourse`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `codtbl_course` (
  `idcourse` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL COMMENT 'کد دوره',
  `coursename` varchar(255) NOT NULL COMMENT 'نام دوره',
  `coursemuted` varchar(200) DEFAULT NULL,
  `coursemeta` text DEFAULT NULL,
  `paye` varchar(255) DEFAULT NULL,
  `piclink` varchar(255) DEFAULT NULL,
  `detail_link` varchar(255) DEFAULT NULL COMMENT 'توضیحات بیشتر',
  `coursefee` int(11) DEFAULT NULL COMMENT 'مبلغ دوره',
  `coursefee_without` int(11) DEFAULT NULL COMMENT 'مبلغ دوره بدون پشتیبان',
  `courseactive` tinyint(1) NOT NULL DEFAULT 1 COMMENT '  ثبت نام فعال/غیرفعال',
  `activecourse` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'فعالسازی دوره برای همه',
  `datestart` date DEFAULT NULL COMMENT 'تاریخ شروع دوره',
  `dateend` date DEFAULT NULL COMMENT 'تاریخ پایان دوره',
  `link_class` varchar(255) NOT NULL DEFAULT '#' COMMENT 'لینک کلاس',
  `link_general` varchar(255) DEFAULT NULL,
  `link_quiz` varchar(255) DEFAULT NULL COMMENT 'لینک آزمون',
  `link_eita` varchar(255) NOT NULL DEFAULT '#' COMMENT 'لینک ایتا',
  `act_class` tinyint(1) DEFAULT 0 COMMENT 'فعالسازی لینک کلاس',
  `act_ostadclass` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'فعالسازی کلاس برای استاد',
  `act_general` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'فعالسازی کلاس عمومی',
  `act_quiz` tinyint(1) DEFAULT 0 COMMENT 'فعالسازی لینک آزمون',
  `datacreated` date NOT NULL COMMENT 'تاریخ ایجاد',
  `timecreated` time NOT NULL COMMENT 'ساعت ایجاد',
  `useradder` varchar(12) DEFAULT NULL COMMENT 'کاربر ایجاد کننده',
  `course_type` tinyint(3) unsigned NOT NULL,
  `girls_group` varchar(255) DEFAULT NULL,
  `guys_group` varchar(255) DEFAULT NULL,
  `gender` tinyint(1) DEFAULT NULL COMMENT 'جنسیت',
  `ugid` int(11) DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL COMMENT 'connecting the course to the related package in \r\ntest website.',
  PRIMARY KEY (`idcourse`),
  UNIQUE KEY `codecourse` (`codecourse`),
  KEY `useradder` (`useradder`),
  CONSTRAINT `codtbl_course_ibfk_1` FOREIGN KEY (`useradder`) REFERENCES `dbadmin` (`username`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `course_demo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `coursename` varchar(255) NOT NULL,
  `coursemuted` varchar(255) DEFAULT NULL,
  `paye` varchar(255) DEFAULT NULL,
  `link_class` varchar(255) DEFAULT NULL,
  `act_class` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codecourse` (`codecourse`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `course_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'شناسه',
  `student_id` varchar(12) NOT NULL,
  `exam_type` varchar(50) NOT NULL COMMENT 'نوع آزمون',
  `registered` varchar(20) NOT NULL COMMENT 'ثبت‌نام کرده یا نکرده',
  `attended` varchar(80) DEFAULT NULL,
  `result` varchar(20) DEFAULT NULL,
  `not_registered_reason` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'تاریخ ایجاد',
  `olympiad_fields` text DEFAULT NULL,
  `not_attended_reason` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_course_status` (`student_id`),
  CONSTRAINT `fk_course_status` FOREIGN KEY (`student_id`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3357 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `course_teacher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `teacher_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_teacher_unique` (`class_id`,`teacher_username`),
  KEY `codecourse` (`class_id`),
  KEY `teacher_username` (`teacher_username`),
  KEY `course_teacher_dbadmin_id` (`created_by`),
  CONSTRAINT `course_teacher_classes_id` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `course_teacher_dbadmin_id` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `course_teacher_ibfk_2` FOREIGN KEY (`teacher_username`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `course_teacher_dars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `dars_id` int(11) NOT NULL,
  `teacher_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_dars_teacher_unique` (`class_id`,`dars_id`,`teacher_username`),
  KEY `idx_course_teacher_dars_dars` (`dars_id`),
  KEY `idx_course_teacher_dars_creator` (`created_by`),
  KEY `idx_course_teacher_dars_assignment` (`class_id`,`teacher_username`),
  CONSTRAINT `ctd_assignment_fk` FOREIGN KEY (`class_id`, `teacher_username`) REFERENCES `course_teacher` (`class_id`, `teacher_username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ctd_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ctd_dars_fk` FOREIGN KEY (`dars_id`) REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `db_card` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `card_num` varchar(50) NOT NULL COMMENT 'شماره کارت',
  `card_name` varchar(100) NOT NULL COMMENT 'نام صاحب حساب',
  `card_bank` varchar(150) NOT NULL COMMENT 'نزد بانک',
  `sheba` varchar(24) DEFAULT NULL COMMENT 'شماره شبا',
  `card_pic` varchar(255) DEFAULT NULL COMMENT 'تصویر کارت',
  PRIMARY KEY (`id`),
  UNIQUE KEY `card_num` (`card_num`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `db_smsun` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) DEFAULT NULL,
  `olgo` varchar(100) NOT NULL,
  `text` varchar(255) NOT NULL,
  `tel` varchar(11) NOT NULL,
  `response` varchar(255) DEFAULT NULL COMMENT 'گزارش ارسال',
  `dateresponse` date DEFAULT NULL COMMENT 'تاریخ ارسال',
  `timeresponse` time DEFAULT NULL COMMENT 'ساعت ارسال',
  `ip` varchar(30) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18986 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `db_wallet` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `fee` int(11) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'منتظر پرداخت',
  `datecreted` date NOT NULL,
  `timecreated` time DEFAULT NULL,
  `detail_wallet` varchar(100) NOT NULL,
  `mode` tinyint(1) NOT NULL COMMENT 'افزایش(1)/کاهش(0)/غ برگشت (2)',
  `factornum` int(11) DEFAULT NULL COMMENT 'شماره فاکتور',
  `ref_id` varchar(100) DEFAULT NULL COMMENT 'شماره تراکنش/پیگیری',
  `card_pan` varchar(50) DEFAULT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  `useractive` varchar(12) DEFAULT NULL COMMENT 'کاربر تایید کننده',
  `dateactive` date DEFAULT NULL COMMENT 'تاریخ تایید',
  `timeactive` time DEFAULT NULL COMMENT 'ساعت تایید',
  `ipactive` varchar(30) DEFAULT NULL COMMENT 'آی پی تایید کننده',
  `osactive` varchar(255) DEFAULT NULL COMMENT 'سیستم عامل تایید کننده',
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `ref_id` (`ref_id`),
  KEY `username` (`username`),
  KEY `useractive` (`useractive`),
  KEY `factornum` (`factornum`),
  CONSTRAINT `db_wallet_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `db_wallet_ibfk_2` FOREIGN KEY (`useractive`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3384 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbaddon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `dars_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `docname` varchar(100) NOT NULL COMMENT 'نام جزوه',
  `typedoc` varchar(100) NOT NULL COMMENT 'فرمت فایل',
  `linkdoc` varchar(255) NOT NULL COMMENT 'لینک فایل',
  `host` varchar(50) NOT NULL COMMENT 'حجم فایل',
  `view` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد بازدید',
  `useradder` varchar(12) NOT NULL,
  `datecreate` date NOT NULL,
  `timecreate` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `codecourse` (`codecourse`),
  KEY `lesson_id` (`lesson_id`),
  KEY `dars_id` (`dars_id`),
  CONSTRAINT `dbaddon_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbaddon_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `dblesson` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `dbaddon_ibfk_3` FOREIGN KEY (`dars_id`) REFERENCES `dbdars` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=887 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbaddon_demo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `docname` varchar(100) NOT NULL,
  `typedoc` varchar(100) NOT NULL,
  `linkdoc` varchar(255) NOT NULL,
  `host` varchar(50) NOT NULL,
  `view` int(11) NOT NULL DEFAULT 0,
  `datecreate` date NOT NULL,
  `timecreate` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `codecourse` (`codecourse`),
  CONSTRAINT `dbaddon_demo_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `course_demo` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbadmin` (
  `idadmin` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL COMMENT 'نام کاربری',
  `password` varchar(255) NOT NULL COMMENT 'رمز عبور',
  `fname` varchar(50) NOT NULL COMMENT 'نام',
  `lname` varchar(50) NOT NULL COMMENT 'نام خانوادگی',
  `adminactive` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'فعال/غیرفعال',
  `adminrole` int(11) DEFAULT NULL COMMENT 'نقش کاربری | 1 پشتیبان,2مدیرمیانی,3استاد, مدیریت0 ,کارشناس فروش6, معاونت اجرایی 51, معاونت آموزشی52, معاونت فروش53 ,معاونت فنی54 ,',
  `school_role_id` tinyint(3) unsigned DEFAULT NULL COMMENT 'سمت سازمانی مدرسه؛ مرجع: school_admin_roles.id',
  `mobile` varchar(11) DEFAULT NULL COMMENT 'موبایل',
  `dakheli` varchar(6) DEFAULT NULL COMMENT 'شماره داخلی',
  `adminpic` varchar(255) DEFAULT NULL COMMENT 'آدرس تصویر',
  `document_file` varchar(255) DEFAULT NULL,
  `resume_file` varchar(255) DEFAULT NULL,
  `identity_file` varchar(255) DEFAULT NULL,
  `gender` varchar(10) NOT NULL,
  `per_skyroom` tinyint(1) DEFAULT 1 COMMENT 'نوع دسترسی اتاق',
  `permision_actfactor` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی تایید فاکتور',
  `permision_notpay` tinyint(1) DEFAULT 0 COMMENT 'فاکتور پرداخت نشده',
  `permision_actwallet` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی تایید کیف پول',
  `permision_bill` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی صورتحساب',
  `permision_financial_export` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی ورودی و خروجی مالی',
  `permision_viewcall` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو تماس امن',
  `permision_viewstudent` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو دانش آموزان',
  `permision_viewcollague` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو همکاران',
  `permision_reportcollague` tinyint(1) DEFAULT 0 COMMENT 'گزارش آماری همکاران',
  `permision_viewcourse` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو دوره ها',
  `permision_viewbill` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو حسابداری',
  `permision_viewsearch` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو استعلامات',
  `permision_viewwallet` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو کیف پول',
  `permision_viewalert` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو اعلان',
  `permision_viewsupport` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو پشتیبانی',
  `permision_viewdocument` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو مستندات',
  `permision_viewsession` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو نشست ها',
  `permision_viewemploy` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو کارمندان',
  `permision_viewsetting` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مشاهده منو تنظیمات',
  `permision_viewtest` tinyint(1) DEFAULT 0 COMMENT 'منو آزمون',
  `permision_deltest` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'حذف آزمون',
  `permision_addstudent` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی ثبت دانش آموز',
  `permision_editstudent` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی ویرایش دانش آموز',
  `permision_addcourse_student` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی ایجاد دوره برای دانش آموز',
  `permision_reportstudent` tinyint(1) DEFAULT 0 COMMENT 'گزارش آماری دانش آموزان',
  `permision_refer` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ارجاع دهنده پیام',
  `permision_allticket` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی مشاهده همه تیکت ها',
  `permision_deletcourse` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی حذف دوره',
  `permision_discount` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی کد تخفیف',
  `permision_addcall` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی به ثبت تماس',
  `permision_managecall` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'مدیریت کل تماس امن',
  `permision_suporter` tinyint(1) NOT NULL DEFAULT 0,
  `permision_manage_suporter` tinyint(1) NOT NULL DEFAULT 0,
  `permision_manage_chat` tinyint(4) NOT NULL DEFAULT 0,
  `skill_permission` tinyint(1) NOT NULL,
  `excel_permission` tinyint(1) NOT NULL,
  `reportcall_permission` tinyint(1) NOT NULL COMMENT 'گزارش تماس و فایل صوتی',
  `installment_permission` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی ادمینی قسط',
  `olympiad_permission` tinyint(4) NOT NULL DEFAULT 0,
  `olympiad_support_permission` tinyint(4) NOT NULL DEFAULT 0,
  `simple_operator` tinyint(4) NOT NULL DEFAULT 0,
  `administrator` tinyint(1) DEFAULT NULL COMMENT 'سرپرست',
  `history_permission` tinyint(1) NOT NULL COMMENT 'دسترسی کنترل سوابق',
  `permission_quiz` tinyint(4) NOT NULL DEFAULT 0,
  `permission_is_not_supporter` tinyint(4) NOT NULL DEFAULT 0,
  `can_impersonate` tinyint(1) NOT NULL DEFAULT 0,
  `autodialer` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی به تماس گروهی (auto dialer)',
  `permission_calendar` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'تقویم رویداد',
  `permission_news_notif` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دسترسی اخبار و اعلان روزانه',
  `register_class_permission` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_perm_courses` tinyint(1) NOT NULL DEFAULT 1,
  `teacher_perm_online_class` tinyint(1) NOT NULL DEFAULT 1,
  `teacher_perm_students` tinyint(1) NOT NULL DEFAULT 1,
  `teacher_perm_attendance` tinyint(1) NOT NULL DEFAULT 1,
  `teacher_perm_homework` tinyint(1) NOT NULL DEFAULT 1,
  `teacher_perm_content` tinyint(1) NOT NULL DEFAULT 1,
  `teacher_perm_messages` tinyint(1) NOT NULL DEFAULT 1,
  `permission_staff_view` tinyint(1) NOT NULL DEFAULT 0,
  `permission_staff_documents` tinyint(1) NOT NULL DEFAULT 0,
  `permission_insurance` tinyint(1) NOT NULL DEFAULT 0,
  `permission_monthly_payment` tinyint(1) NOT NULL DEFAULT 0,
  `permission_assigned_tasks` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`idadmin`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `dakheli` (`dakheli`),
  KEY `idx_dbadmin_school_role_id` (`school_role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbattendance` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `coding` int(11) DEFAULT NULL,
  `username` varchar(13) NOT NULL,
  `timehozor` varchar(100) NOT NULL DEFAULT 'غایب',
  `status` varchar(100) NOT NULL DEFAULT 'غایب',
  PRIMARY KEY (`id`),
  KEY `coding` (`coding`),
  CONSTRAINT `dbattendance_ibfk_1` FOREIGN KEY (`coding`) REFERENCES `tbl_attendanced` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=217103 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbcourse` (
  `idcourse` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(15) NOT NULL,
  `codecourse` int(11) NOT NULL COMMENT 'کد دوره',
  `class_id` int(11) DEFAULT NULL,
  `activityuser` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'فعال/غیرفعال',
  `numfactor` int(11) DEFAULT NULL COMMENT 'شماره فاکتور',
  `suporter` varchar(200) DEFAULT NULL COMMENT 'پشتیبان',
  `suporter_updated_at` datetime DEFAULT NULL COMMENT 'تاریخ عوض شدن اپراتور',
  `type_process` tinyint(1) NOT NULL DEFAULT 0 COMMENT ' 	0 auto/1 wait/2 manual ',
  `color` tinyint(1) NOT NULL DEFAULT 0 COMMENT ' 	0 red/1 orange/2 yellow/3 green 	',
  `color_wait` tinyint(1) DEFAULT NULL,
  `color_detail` text DEFAULT NULL,
  `first_suporter_assigned_at` datetime DEFAULT NULL,
  `has_operator` tinyint(4) NOT NULL DEFAULT 1,
  `group_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`idcourse`),
  KEY `numfactor` (`numfactor`),
  KEY `username` (`username`),
  KEY `codecourse` (`codecourse`),
  KEY `dbcourse_ibfk_4` (`class_id`),
  KEY `idx_suporter_suporter_updated_at` (`suporter`,`suporter_updated_at`),
  KEY `idx_suporter_updated_at` (`suporter_updated_at`),
  KEY `idx_suporter` (`suporter`),
  KEY `idx_first_suporter_assigned_at` (`first_suporter_assigned_at`),
  KEY `group_id` (`group_id`),
  KEY `idx_suporter_activity` (`suporter`,`activityuser`),
  KEY `idx_suporter_color` (`suporter`,`color`),
  KEY `idx_suporter_codecourse` (`suporter`,`codecourse`),
  CONSTRAINT `dbcourse_ibfk_1` FOREIGN KEY (`numfactor`) REFERENCES `dbfactor` (`idfactor`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbcourse_ibfk_2` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbcourse_ibfk_3` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON UPDATE CASCADE,
  CONSTRAINT `dbcourse_ibfk_4` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `dbcourse_ibfk_5` FOREIGN KEY (`group_id`) REFERENCES `handhug_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11031 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbcourse_demo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(15) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `codecourse` int(11) NOT NULL,
  `useradder` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `codecourse` (`codecourse`),
  CONSTRAINT `dbcourse_demo_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbcourse_demo_ibfk_2` FOREIGN KEY (`codecourse`) REFERENCES `course_demo` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2017 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbdars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dars` varchar(50) NOT NULL,
  `codecourse` int(11) DEFAULT NULL,
  `link_class` varchar(255) DEFAULT NULL COMMENT 'Ï┤┘åÏºÏ│┘ç ÏºÏ¬Ïº┘é ┌å┘çÏºÏ▒Ï»█î┘êÏºÏ▒█î (ÏºÏ│┌®Ïº█îÔÇîÏ▒┘ê┘à) ÏºÏ«Ï¬ÏÁÏºÏÁ█î Ï»Ï▒Ï│',
  PRIMARY KEY (`id`),
  KEY `codecourse` (`codecourse`),
  CONSTRAINT `dbdars_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbdiscount` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_discount` varchar(100) NOT NULL,
  `discount` varchar(30) NOT NULL COMMENT 'کد تخفیف',
  `value_discount` float NOT NULL COMMENT 'مقدار تخفیف',
  `type_discount` tinyint(1) NOT NULL COMMENT 'درصد(1)/ثابت(0)',
  `role` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'دانش آموز (1)/همکار(2)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `discount` (`discount`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbfactor` (
  `idfactor` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL COMMENT 'کاربر',
  `sumitem` int(11) DEFAULT NULL COMMENT 'جمع موارد',
  `discountcode` varchar(50) DEFAULT NULL COMMENT 'کد تخفیف',
  `discountfee` int(11) DEFAULT 0 COMMENT 'مبلغ تخفیف',
  `wage` int(11) DEFAULT 0 COMMENT 'کارمزد همکار',
  `subtotal` int(11) DEFAULT 0 COMMENT 'قیمت کل(بعد از تخفیف)',
  `wallet` int(11) DEFAULT NULL COMMENT 'مبلغ کیف پول',
  `factorfee` int(11) NOT NULL COMMENT 'مبلغ فاکتور',
  `datecreate` date DEFAULT NULL COMMENT 'تاریخ ایجاد',
  `timecreate` time DEFAULT NULL COMMENT 'ساعت ایجاد',
  `factoradder` varchar(12) DEFAULT NULL COMMENT 'کاربر ایجاد کننده',
  `useradder` varchar(12) DEFAULT NULL COMMENT 'همکار ایجاد کننده',
  `status` varchar(20) NOT NULL DEFAULT 'منتظر پرداخت',
  `metod` varchar(20) DEFAULT NULL COMMENT 'روش پرداخت',
  `dateresponse` date DEFAULT NULL,
  `timeresponse` time DEFAULT NULL,
  `ref_id` varchar(20) DEFAULT NULL COMMENT 'شناسه تراکنش',
  `card_pan` varchar(50) DEFAULT NULL COMMENT 'شماره کارت',
  `ip` varchar(20) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  `useractive` varchar(12) DEFAULT NULL COMMENT 'کاربر تایید کننده',
  `dateactive` date DEFAULT NULL COMMENT 'تاریخ تایید',
  `timeactive` time DEFAULT NULL COMMENT 'ساعت تایید',
  `ipactive` varchar(30) DEFAULT NULL COMMENT 'آی پی تایید کننده',
  `osactive` varchar(255) DEFAULT NULL COMMENT 'سیستم عامل تایید کننده',
  PRIMARY KEY (`idfactor`),
  KEY `username` (`username`),
  KEY `useradder` (`useradder`),
  CONSTRAINT `dbfactor_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbfactor_ibfk_2` FOREIGN KEY (`useradder`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9119 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbh_admin` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `datelogin` date NOT NULL,
  `timelogin` time NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip` varchar(30) NOT NULL,
  `os` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  CONSTRAINT `dbh_admin_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12093 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbh_login` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL COMMENT 'نام کاربری',
  `datelogin` date NOT NULL,
  `timelogin` time NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `os` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  CONSTRAINT `dbh_login_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101311 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dblesson` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `dars_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `codecourse` (`codecourse`),
  KEY `dars_id` (`dars_id`),
  CONSTRAINT `dblesson_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dblesson_ibfk_2` FOREIGN KEY (`dars_id`) REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=319 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dblogin` (
  `iduser` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` int(11) NOT NULL DEFAULT 1 COMMENT 'دانش آموز/ همکار',
  `activity` tinyint(1) NOT NULL DEFAULT 1,
  `firstlogin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'اولین ورود',
  `keyactive` int(11) DEFAULT NULL COMMENT 'کد فعالسازی',
  `true_key` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'تاییدیه کد',
  PRIMARY KEY (`iduser`),
  UNIQUE KEY `username` (`username`),
  CONSTRAINT `dblogin_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7681 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbmovie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL COMMENT 'کد دوره',
  `dars_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `filmname` varchar(255) NOT NULL COMMENT 'نام فیلم',
  `typefilm` varchar(100) NOT NULL COMMENT 'فرمت فیلم',
  `linkfilm` varchar(255) NOT NULL COMMENT 'لینک فیلم',
  `host` varchar(100) DEFAULT NULL COMMENT 'حجم',
  `view` int(11) DEFAULT 0 COMMENT 'تعداد بازدید',
  `useradder` varchar(12) DEFAULT NULL COMMENT 'کاربر ایجاد کننده',
  `datecreate` date DEFAULT NULL COMMENT 'تاریخ ایجاد',
  `timecreate` time DEFAULT NULL COMMENT 'زمان ایجاد',
  PRIMARY KEY (`id`),
  KEY `codecourse` (`codecourse`),
  KEY `dbmovie_ibfk_2` (`useradder`),
  KEY `lesson_id` (`lesson_id`),
  KEY `dars_id` (`dars_id`),
  CONSTRAINT `dbmovie_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbmovie_ibfk_2` FOREIGN KEY (`useradder`) REFERENCES `dbadmin` (`username`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `dbmovie_ibfk_3` FOREIGN KEY (`lesson_id`) REFERENCES `dblesson` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `dbmovie_ibfk_4` FOREIGN KEY (`dars_id`) REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1807 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbmovie_demo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `filmname` varchar(255) NOT NULL,
  `typefilm` varchar(100) NOT NULL,
  `linkfilm` varchar(255) NOT NULL,
  `host` varchar(100) DEFAULT NULL,
  `view` int(11) DEFAULT 0,
  `datecreate` date DEFAULT NULL,
  `timecreate` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `codecourse` (`codecourse`),
  CONSTRAINT `dbmovie_demo_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `course_demo` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbpersonal` (
  `idpersonal` int(11) NOT NULL AUTO_INCREMENT,
  `codemeli` varchar(12) NOT NULL COMMENT 'کدملی',
  `fname` varchar(50) NOT NULL COMMENT 'نام',
  `lname` varchar(100) NOT NULL COMMENT 'نام خانوادگی',
  `fathername` varchar(50) DEFAULT NULL COMMENT 'نام پدر',
  `vip` int(11) DEFAULT NULL COMMENT 'دانش آموز خاص',
  `tel1` varchar(11) NOT NULL COMMENT 'تلفن دانش آموز/همکار',
  `tel2` varchar(11) DEFAULT NULL COMMENT 'تلفن مادر/رابط',
  `tel3` varchar(11) DEFAULT NULL COMMENT 'تلفن پدر',
  `melliat` varchar(10) DEFAULT 'ایرانی',
  `personalcode` varchar(100) DEFAULT NULL COMMENT 'کد پرسنلی همکار',
  `birthdate` date NOT NULL DEFAULT '0000-00-00' COMMENT 'تاریخ تولد',
  `state` varchar(50) DEFAULT NULL COMMENT 'استان ',
  `city` varchar(70) DEFAULT NULL COMMENT 'شهر',
  `nameschool` varchar(150) DEFAULT NULL COMMENT 'نام مدرسه',
  `typeschool` varchar(50) DEFAULT NULL COMMENT 'نوع مدرسه',
  `paye` int(11) DEFAULT NULL COMMENT 'پایه',
  `reshtestudent` varchar(20) DEFAULT NULL COMMENT 'رشته تحصیلی دانش آموز',
  `area` int(11) DEFAULT NULL COMMENT 'منطقه آموزش و پرورش',
  `gender` varchar(5) NOT NULL COMMENT 'جنسیت',
  `shift` tinyint(4) DEFAULT NULL COMMENT 'صفر:صبح|یک:بعدازظهر|دو:گردشی',
  `workname` varchar(200) DEFAULT NULL COMMENT 'نام محل کار',
  `semat` varchar(200) DEFAULT NULL COMMENT 'سمت ',
  `maghta` varchar(20) DEFAULT NULL COMMENT 'مقطع تحصیلی همکار',
  `reshtehamkar` varchar(50) DEFAULT NULL COMMENT 'رشته تحصیلی همکار',
  `presented` varchar(100) DEFAULT NULL COMMENT 'نحوه آشنایی',
  `pic` varchar(255) DEFAULT NULL COMMENT 'آدرس عکس',
  `datecreated` date NOT NULL COMMENT 'تاریخ ایجاد',
  `timecreated` time NOT NULL COMMENT 'ساعت ایجاد',
  `ipcreated` varchar(30) DEFAULT NULL COMMENT 'آدرس آی پی',
  `oscreated` varchar(255) DEFAULT NULL COMMENT 'سیستم عامل ایجاد کننده',
  `creator` varchar(100) DEFAULT NULL COMMENT 'ایجاد کننده',
  `tel_home` varchar(11) DEFAULT NULL COMMENT 'تلفن منزل',
  `email` varchar(255) DEFAULT NULL COMMENT 'ایمیل',
  `bio` text DEFAULT NULL COMMENT 'بیوگرافی',
  `hand` varchar(15) DEFAULT NULL COMMENT 'دست غالب',
  `famili_pedar` varchar(255) DEFAULT NULL COMMENT 'نام خانوادگی پدر',
  `codemeli_pedar` varchar(12) DEFAULT NULL COMMENT 'کدملی پدر',
  `shenasname_pedar` varchar(50) DEFAULT NULL COMMENT 'شماره شناسنامه پدر',
  `birthday_pedar` date DEFAULT NULL COMMENT 'تاریخ تولد پدر',
  `job_pedar` varchar(255) DEFAULT NULL COMMENT 'شغل پدر',
  `education_pedar` varchar(50) DEFAULT NULL COMMENT 'تحصیلات پدر',
  `hayat_pedar` varchar(30) DEFAULT NULL COMMENT 'حیات پدر',
  `name_madar` varchar(75) DEFAULT NULL,
  `famili_madar` varchar(150) DEFAULT NULL,
  `codemeli_madar` varchar(12) DEFAULT NULL,
  `shenasname_madar` varchar(25) DEFAULT NULL,
  `birthdate_madar` date DEFAULT NULL,
  `job_madar` varchar(255) DEFAULT NULL,
  `education_madar` varchar(50) DEFAULT NULL,
  `hayat_madar` varchar(20) DEFAULT NULL,
  `living_condition` varchar(100) DEFAULT NULL COMMENT 'وضعیت زندگی',
  `bime_status` varchar(10) DEFAULT NULL COMMENT 'وضعیت بیمه درمان',
  `all_baby` int(11) DEFAULT NULL COMMENT 'تعداد فرزند در خانواده',
  `life_rosta` varchar(100) DEFAULT NULL COMMENT 'روستا محل سکونت',
  `address` varchar(255) DEFAULT NULL COMMENT 'آدرس سکونت',
  `zipcode` int(11) DEFAULT NULL COMMENT 'کدپستی',
  `type_life` varchar(30) DEFAULT NULL COMMENT 'نوع محل سکونت',
  `life_status` varchar(30) DEFAULT NULL COMMENT 'وضعیت سکونت',
  `life_baft` varchar(20) DEFAULT NULL COMMENT 'نوع بافت سکونت',
  `life_metr` int(11) DEFAULT NULL COMMENT 'متراژ محل سکونت',
  `modir_name` varchar(150) DEFAULT NULL COMMENT 'نام مدیر مدرسه',
  `sheba` varchar(24) DEFAULT NULL,
  `name_sheba` varchar(255) DEFAULT NULL,
  `bank_sheba` varchar(100) DEFAULT NULL,
  `card_bank` varchar(20) DEFAULT NULL,
  `name_card` varchar(255) DEFAULT NULL,
  `useradder` varchar(12) DEFAULT NULL,
  `dateupdate` date DEFAULT NULL COMMENT 'تاریخ بروزرسانی',
  `timeupdate` time DEFAULT NULL COMMENT 'ساعت بروزرسانی',
  `updator` varchar(100) DEFAULT NULL COMMENT 'بروز کننده',
  `aboutsuporter` text DEFAULT NULL,
  `moaref` varchar(100) DEFAULT NULL,
  `bulk_upload_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `suspension_mode` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'حالت تعلیق',
  `suspension_reason` varchar(511) DEFAULT NULL COMMENT 'علت تعلیق',
  PRIMARY KEY (`idpersonal`),
  UNIQUE KEY `codemeli` (`codemeli`) USING BTREE,
  KEY `useradder` (`useradder`),
  KEY `vip` (`vip`),
  KEY `bulk_upload_id` (`bulk_upload_id`),
  CONSTRAINT `dbpersonal_ibfk_1` FOREIGN KEY (`useradder`) REFERENCES `dbpersonal` (`codemeli`) ON UPDATE CASCADE,
  CONSTRAINT `dbpersonal_ibfk_2` FOREIGN KEY (`vip`) REFERENCES `type_vip` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `dbpersonal_ibfk_3` FOREIGN KEY (`bulk_upload_id`) REFERENCES `bulk_uploads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7681 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbpersonal_type_vip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dbpersonal_id` int(11) NOT NULL,
  `type_vip_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dbpersonal_id` (`dbpersonal_id`),
  KEY `type_vip_id` (`type_vip_id`),
  CONSTRAINT `dbpersonal_type_vip_ibfk_1` FOREIGN KEY (`dbpersonal_id`) REFERENCES `dbpersonal` (`idpersonal`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbpersonal_type_vip_ibfk_2` FOREIGN KEY (`type_vip_id`) REFERENCES `type_vip` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbquiz` (
  `idquiz` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `dars` int(11) DEFAULT NULL COMMENT 'کد درس',
  `tasalsol` int(11) DEFAULT NULL COMMENT 'تسلسل',
  `quizname` varchar(50) NOT NULL,
  `resultpdf` varchar(255) DEFAULT NULL COMMENT 'لینک پاسخنامه',
  `datestart` date DEFAULT NULL COMMENT 'تاریخ برگزاری',
  `tid` int(11) DEFAULT NULL,
  PRIMARY KEY (`idquiz`),
  KEY `codecourse` (`codecourse`),
  KEY `dars` (`dars`),
  CONSTRAINT `dbquiz_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `dbquiz_ibfk_2` FOREIGN KEY (`dars`) REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=359 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `dbschedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dbschedule_ibfk_1` (`codecourse`),
  CONSTRAINT `dbschedule_ibfk_1` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `deposit_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requested_by` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `factor_id` int(11) NOT NULL,
  `card` varchar(20) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `ref_id` varchar(100) NOT NULL,
  `status` enum('pending','accepted','rejected','') NOT NULL,
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `requested_by` (`requested_by`),
  KEY `factor_id` (`factor_id`),
  CONSTRAINT `deposit_verifications_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `deposit_verifications_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `deposit_verifications_ibfk_3` FOREIGN KEY (`factor_id`) REFERENCES `dbfactor` (`idfactor`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `documention` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `namedoc` varchar(255) NOT NULL,
  `meta` text NOT NULL,
  `url` varchar(255) NOT NULL,
  `view` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'وضعیت مشاهده',
  `datecreate` date NOT NULL,
  `timecreate` time NOT NULL,
  `ip` varchar(30) NOT NULL,
  `os` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  CONSTRAINT `documention_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `handhug_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codecourse` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `leader_username` varchar(12) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `link_room` varchar(255) DEFAULT NULL,
  `supporter` varchar(50) DEFAULT NULL,
  `max_users` int(10) unsigned NOT NULL DEFAULT 5,
  `creator` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_handhug_codecourse` (`codecourse`),
  KEY `idx_handhug_leader` (`leader_username`),
  KEY `idx_handhug_supporter` (`supporter`),
  CONSTRAINT `fk_handhug_course` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_handhug_supporter` FOREIGN KEY (`supporter`) REFERENCES `dbadmin` (`username`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `handhug_groups_ibfk_1` FOREIGN KEY (`leader_username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `handhug_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `useradder` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `status` enum('pending','accepted','rejected','') NOT NULL,
  `accepted_by` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `useradder` (`useradder`),
  KEY `accepted_by` (`accepted_by`),
  CONSTRAINT `handhug_uploads_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `handhug_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `handhug_uploads_ibfk_2` FOREIGN KEY (`useradder`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `handhug_uploads_ibfk_3` FOREIGN KEY (`accepted_by`) REFERENCES `dbadmin` (`username`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `hf_control_state` (
  `id` tinyint(3) unsigned NOT NULL,
  `license_json` longtext DEFAULT NULL,
  `license_signature` char(64) DEFAULT NULL,
  `allowed` tinyint(1) DEFAULT NULL,
  `reason` varchar(80) DEFAULT NULL,
  `expires_at` varchar(40) DEFAULT NULL,
  `capacity_total` int(10) unsigned DEFAULT NULL,
  `capacity_used` int(10) unsigned NOT NULL DEFAULT 0,
  `fetched_at` datetime DEFAULT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `homework_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `admin_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `approved_by` varchar(50) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `homework_type` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'type 0 = normal (for everyone) \r\n',
  `description` varchar(255) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `video_link` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `grade_type` enum('numeric','scale') NOT NULL,
  `min_grade` int(11) DEFAULT NULL,
  `max_grade` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_username` (`admin_username`),
  KEY `fk_homework_admin_class_id` (`class_id`),
  CONSTRAINT `fk_homework_admin_class_id` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `homework_admin_ibfk_1` FOREIGN KEY (`admin_username`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `homework_student` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `homework_id` int(11) NOT NULL,
  `student_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `submission_text` varchar(255) DEFAULT NULL,
  `professors_answer` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `homework_id` (`homework_id`),
  KEY `student_username` (`student_username`),
  CONSTRAINT `homework_student_ibfk_1` FOREIGN KEY (`homework_id`) REFERENCES `homework_admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `homework_student_ibfk_2` FOREIGN KEY (`student_username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `import_quiz1` (
  `idimport` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `duration` varchar(30) NOT NULL,
  `allquestion` varchar(30) NOT NULL,
  `trueresponse` varchar(30) NOT NULL,
  `falseresponse` varchar(30) NOT NULL,
  `unresponse` varchar(30) NOT NULL,
  `nomre` varchar(50) NOT NULL,
  `quizid` varchar(20) NOT NULL,
  PRIMARY KEY (`idimport`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `import_quiz1_jame` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `duration` varchar(30) NOT NULL,
  `allquestion` varchar(30) NOT NULL,
  `true_response` varchar(30) NOT NULL,
  `false_response` varchar(30) NOT NULL,
  `unresponse` varchar(30) NOT NULL,
  `nomre` varchar(50) NOT NULL,
  `quiz_id` varchar(20) NOT NULL,
  `results_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`results_json`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `import_quiz2` (
  `idimport` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `duration` varchar(30) NOT NULL,
  `allquestion` int(11) NOT NULL,
  `trueresponse` int(11) NOT NULL,
  `falseresponse` int(11) NOT NULL,
  `unresponse` int(11) NOT NULL,
  `nomre` int(11) NOT NULL,
  `taraz` int(11) DEFAULT NULL,
  `rotbe` int(11) DEFAULT NULL,
  `quizid` int(11) NOT NULL,
  PRIMARY KEY (`idimport`)
) ENGINE=InnoDB AUTO_INCREMENT=43688 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `import_quiz2_jame` (
  `id_import` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `duration` varchar(30) NOT NULL,
  `allquestion` int(11) NOT NULL,
  `true_response` int(11) NOT NULL,
  `false_response` int(11) NOT NULL,
  `unresponse` int(11) NOT NULL,
  `nomre` int(11) NOT NULL,
  `taraz` int(11) DEFAULT NULL,
  `rotbe` int(11) DEFAULT NULL,
  `quiz_id` int(11) NOT NULL,
  `results_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`results_json`)),
  PRIMARY KEY (`id_import`)
) ENGINE=InnoDB AUTO_INCREMENT=1350 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `institution_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_type` enum('school','institute') NOT NULL DEFAULT 'school',
  `kind` enum('level','group','field') NOT NULL DEFAULT 'level',
  `value` int(11) DEFAULT NULL COMMENT 'مقدار ذخیره شده در dbpersonal.paye؛ فقط برای kind=level',
  `slug` varchar(64) NOT NULL COMMENT 'مقداری که در codtbl_course.paye برچسب می خورد',
  `label` varchar(64) NOT NULL COMMENT 'متن نمایشی',
  `group_slug` varchar(64) DEFAULT NULL COMMENT 'اسلاگ دستهٔ والد؛ فقط برای kind=level',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `selectable_for_person` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'در فرم ثبت نام فراگیر نشان داده شود؟',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_kind_slug` (`institution_type`,`kind`,`slug`),
  KEY `idx_lookup` (`institution_type`,`kind`,`is_active`,`sort_order`),
  KEY `idx_value` (`institution_type`,`value`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `log_skyroom` (
  `skyroom_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL COMMENT 'نام کاربری',
  `codecourse` int(11) NOT NULL,
  `datelogin` date NOT NULL,
  `timelogin` time NOT NULL,
  `url` text NOT NULL,
  `ip` varchar(30) NOT NULL,
  `os` varchar(255) NOT NULL,
  `is_generated` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`skyroom_id`)
) ENGINE=InnoDB AUTO_INCREMENT=145160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `logviewdoc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `codecourse` int(11) NOT NULL,
  `iddoc` int(11) NOT NULL,
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `iddoc` (`iddoc`),
  KEY `codecourse` (`codecourse`),
  CONSTRAINT `logviewdoc_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewdoc_ibfk_2` FOREIGN KEY (`iddoc`) REFERENCES `dbaddon` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewdoc_ibfk_3` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63468 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `logviewdoc_demo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `codecourse` int(11) NOT NULL,
  `iddoc` int(11) NOT NULL,
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  `ip` varchar(30) NOT NULL,
  `os` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `logviewdoc_demo_ibfk_1` (`username`),
  KEY `codecourse` (`codecourse`),
  KEY `iddoc` (`iddoc`),
  CONSTRAINT `logviewdoc_demo_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewdoc_demo_ibfk_2` FOREIGN KEY (`codecourse`) REFERENCES `course_demo` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewdoc_demo_ibfk_3` FOREIGN KEY (`iddoc`) REFERENCES `dbaddon_demo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `logviewfilm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL COMMENT 'دانش آموز',
  `codecourse` int(11) NOT NULL COMMENT 'فیلم متعلق به دوره',
  `idfilm` int(11) NOT NULL COMMENT 'شناسه فیلم',
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `idfilm` (`idfilm`),
  KEY `codecourse` (`codecourse`),
  CONSTRAINT `logviewfilm_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewfilm_ibfk_2` FOREIGN KEY (`idfilm`) REFERENCES `dbmovie` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewfilm_ibfk_3` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121000 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `logviewfilm_demo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `codecourse` int(11) NOT NULL,
  `idfilm` int(11) NOT NULL,
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  `ip` varchar(30) NOT NULL,
  `os` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `logviewfilm_demo_ibfk_1` (`username`),
  KEY `codecourse` (`codecourse`),
  KEY `idfilm` (`idfilm`),
  CONSTRAINT `logviewfilm_demo_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewfilm_demo_ibfk_2` FOREIGN KEY (`codecourse`) REFERENCES `course_demo` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `logviewfilm_demo_ibfk_3` FOREIGN KEY (`idfilm`) REFERENCES `dbmovie_demo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=553 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `news` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show 0=not show',
  `creator` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_news_dbadmin_username` (`creator`),
  CONSTRAINT `fk_news_dbadmin_username` FOREIGN KEY (`creator`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `news_audience` (
  `news_id` int(11) NOT NULL,
  `audience` varchar(3) NOT NULL COMMENT '''0'' => ''مدیریت'', ''1'' => ''پشتیبان'', ''2'' => ''مدیرمیانی'', ''3'' => ''استاد'', ''6'' => ''کارشناس فروش'', ''51'' => ''معاونت اجرایی'', ''52'' => ''معاونت آموزشی'', ''53'' => ''معاونت فروش'', ''54'' => ''معاونت فنی'', ''00'' => ''کاربران'' ''000'' => ''همه کارمندان''',
  KEY `news_id` (`news_id`),
  CONSTRAINT `news_audience_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `no_phones` (
  `codemeli` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notif` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `creator` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL COMMENT 'File path to uploaded picture (admin upload)',
  `expiration_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notif_dbadmin_username` (`creator`),
  CONSTRAINT `fk_notif_dbadmin_username` FOREIGN KEY (`creator`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notif_audience` (
  `notif_id` int(11) NOT NULL,
  `audience` varchar(3) NOT NULL COMMENT '''0''  => ''مدیریت'',\r\n    ''1''  => ''پشتیبان'',\r\n    ''2''  => ''مدیرمیانی'',\r\n    ''3''  => ''استاد'',\r\n    ''6''  => ''کارشناس فروش'',\r\n    ''51'' => ''معاونت اجرایی'',\r\n    ''52'' => ''معاونت آموزشی'',\r\n    ''53'' => ''معاونت فروش'',\r\n    ''54'' => ''معاونت فنی'',\r\n    ''00'' => ''کاربران''\r\n    ''000'' => ''همه کارمندان''',
  KEY `notif_id` (`notif_id`),
  CONSTRAINT `notif_audience_ibfk_1` FOREIGN KEY (`notif_id`) REFERENCES `notif` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notification_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `period_days` int(11) NOT NULL,
  `min_calls` int(11) DEFAULT NULL,
  `min_talk_time` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `payment_installments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `operator_username` varchar(12) NOT NULL,
  `amount_due` decimal(12,0) NOT NULL,
  `due_date` date NOT NULL,
  `paid_date` date DEFAULT NULL,
  `status` enum('pending','paid','overdue','canceled','refund') DEFAULT 'pending',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_installment_operator` (`operator_username`),
  KEY `fk_installment_payment` (`payment_id`),
  CONSTRAINT `fk_installment_operator` FOREIGN KEY (`operator_username`) REFERENCES `dbadmin` (`username`),
  CONSTRAINT `fk_installment_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=254 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codemeli` varchar(12) NOT NULL,
  `operator_username` varchar(12) NOT NULL,
  `idfactor` int(11) DEFAULT NULL,
  `total_amount` decimal(12,0) NOT NULL,
  `paid_amount` decimal(12,0) DEFAULT 0,
  `status` enum('active','paid','overdue','canceled','refund') DEFAULT 'active',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_payment_operator` (`operator_username`),
  KEY `fk_payment_student` (`codemeli`),
  KEY `idfactor` (`idfactor`),
  CONSTRAINT `fk_payment_operator` FOREIGN KEY (`operator_username`) REFERENCES `dbadmin` (`username`) ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_student` FOREIGN KEY (`codemeli`) REFERENCES `dbpersonal` (`codemeli`) ON UPDATE CASCADE,
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`idfactor`) REFERENCES `dbfactor` (`idfactor`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `people_staging` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `grade` tinyint(3) unsigned DEFAULT NULL,
  `phone` varchar(15) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `major` varchar(100) DEFAULT NULL,
  `codemeli` varchar(10) DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL COMMENT 'null = 7.8 mil original data. \r\n= 0 komite emdad\r\n',
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_state_city` (`state`,`city`),
  KEY `idx_major` (`major`),
  KEY `idx_grade` (`grade`),
  KEY `idx_city` (`city`)
) ENGINE=InnoDB AUTO_INCREMENT=524281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `private_homework_students` (
  `homework_id` int(11) NOT NULL,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'student codemeli',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  KEY `fk_private_homework_students__homework_admin__id` (`homework_id`),
  KEY `fk_private_homework_students__dbpersonal__codemeli` (`username`),
  CONSTRAINT `fk_private_homework_students__dbpersonal__codemeli` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_private_homework_students__homework_admin__id` FOREIGN KEY (`homework_id`) REFERENCES `homework_admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `register_class_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `register_class_events_id` int(11) NOT NULL,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `status` enum('0','1') DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `register_class_events_id` (`register_class_events_id`,`username`,`created_by`),
  KEY `username` (`username`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `register_class_attendance_ibfk_1` FOREIGN KEY (`register_class_events_id`) REFERENCES `register_class_events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_attendance_ibfk_2` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_attendance_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `register_class_discipline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `reason` varchar(255) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `username` (`username`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `register_class_discipline_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_discipline_ibfk_2` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_discipline_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `register_class_evaluation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `register_class_events_id` int(11) NOT NULL,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `score` decimal(8,2) DEFAULT NULL COMMENT '┘å┘àÏ▒┘ç ┌®┘äÏºÏ│█î (█░ Ï¬Ïº █▓█░) █îÏº Ï¬Ï▒ÏºÏ▓ ÏóÏ▓┘à┘ê┘å Ïó┘å┘äÏº█î┘å',
  `note` varchar(500) DEFAULT NULL COMMENT '┘àÏ¬┘å Ï│Ï¬┘ê┘å Ï¬┘êÏÂ█îÏ¡ÏºÏ¬',
  `quality` tinyint(3) unsigned DEFAULT NULL COMMENT '1=Ï«█î┘ä█î Ï«┘êÏ¿Ïî 2=Ï«┘êÏ¿Ïî 3=┘éÏºÏ¿┘ä ┘éÏ¿┘ê┘äÏî 4=┘å█îÏºÏ▓ Ï¿┘ç Ï¬┘äÏºÏ┤ Ï¿█îÏ┤Ï¬Ï▒',
  `visibility_target` varchar(20) NOT NULL DEFAULT 'both' COMMENT 'student، family یا both',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `register_class_events_id` (`register_class_events_id`,`username`,`created_by`),
  KEY `created_by` (`created_by`),
  KEY `username` (`username`),
  CONSTRAINT `register_class_evaluation_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_evaluation_ibfk_3` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `register_class_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('0','1','2','3','4','5') NOT NULL COMMENT '0=حضور دستی، 1=نمره کلاسی، 2=حضور آنلاین، 3=توضیحات، 4=تراز آزمون آنلاین، 5=نمره تکلیف',
  `max_score` decimal(8,2) NOT NULL DEFAULT 20.00 COMMENT 'سقف نمره ستون نمره کلاسی',
  `homework_notification_target` varchar(20) NOT NULL DEFAULT 'family' COMMENT 'family یا family_student برای نمره تکلیف',
  `event_date` date DEFAULT NULL COMMENT 'Ï¬ÏºÏ▒█îÏ« Ï│Ï¬┘ê┘åÏø ÏºÏ«Ï¬█îÏºÏ▒█î ÏºÏ│Ï¬ ┘ê Ï»Ï▒ ÏÁ┘êÏ▒Ï¬ Ï«Ïº┘ä█î Ï¿┘êÏ»┘å ┘å┘àÏº█îÏ┤ Ï»ÏºÏ»┘ç ┘å┘à█î Ï┤┘êÏ»',
  `group_uid` char(32) DEFAULT NULL COMMENT 'Ï┤┘åÏºÏ│┘ç Ï»Ï│Ï¬┘ç Ï│Ï¬┘ê┘å ┘çÏº█î Ïº█îÏ¼ÏºÏ» Ï┤Ï»┘ç Ï»Ï▒ █î┌® ┘å┘êÏ¿Ï¬',
  `description` varchar(255) DEFAULT NULL,
  `status` enum('0','1','2') NOT NULL COMMENT '0 = pending 1 = accepted \r\n2 = changes needed\r\n',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewer` int(11) DEFAULT NULL,
  `reviewer_note` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `created_by` (`created_by`),
  KEY `reviewer` (`reviewer`),
  KEY `teacher_id` (`teacher_id`),
  KEY `idx_rce_group_uid` (`group_uid`),
  CONSTRAINT `register_class_events_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_events_ibfk_3` FOREIGN KEY (`reviewer`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `register_class_events_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `register_class_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`body`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `report_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `thread_id` bigint(20) NOT NULL,
  `sender_username` varchar(12) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `seen_by_operator` tinyint(1) NOT NULL DEFAULT 0,
  `seen_by_manager` tinyint(1) NOT NULL DEFAULT 0,
  `seen_at_operator` datetime DEFAULT NULL,
  `seen_at_manager` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_thread_id` (`thread_id`),
  KEY `idx_sender_username` (`sender_username`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_thread_created` (`thread_id`,`created_at`),
  CONSTRAINT `fk_report_messages_thread` FOREIGN KEY (`thread_id`) REFERENCES `report_threads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `report_threads` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `codemeli` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `operator_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `responder_username` varchar(12) DEFAULT NULL,
  `title` varchar(250) DEFAULT NULL,
  `status` enum('open','chatting','resolved','rejected') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_codemeli` (`codemeli`),
  KEY `idx_operator_username` (`operator_username`),
  KEY `idx_responder_username` (`responder_username`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_updated_at` (`updated_at`),
  KEY `idx_operator_codemeli` (`operator_username`,`codemeli`),
  KEY `idx_responder_status` (`responder_username`,`status`),
  KEY `idx_status_updated` (`status`,`updated_at`),
  KEY `idx_operator_updated` (`operator_username`,`updated_at`),
  CONSTRAINT `report_threads_ibfk_1` FOREIGN KEY (`operator_username`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=617 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `reportcollague` (
  `codemeli` varchar(12) NOT NULL,
  `fname` varchar(255) NOT NULL,
  `lname` varchar(255) NOT NULL,
  `fathername` varchar(255) NOT NULL,
  `tel1` varchar(12) NOT NULL,
  `tel2` varchar(12) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `workname` varchar(255) DEFAULT NULL,
  `semat` varchar(100) DEFAULT NULL,
  `maghta` varchar(100) DEFAULT NULL,
  `reshtehamkar` varchar(200) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `datecreated` date DEFAULT NULL,
  `presented` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `result_quiz` (
  `idresult` bigint(20) NOT NULL AUTO_INCREMENT,
  `quizid` int(11) NOT NULL COMMENT 'شناسه آزمون',
  `username` varchar(12) NOT NULL COMMENT 'نام کاربری',
  `allquestion` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد سوالات آزمون',
  `trueresponse` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد صحیح',
  `falseresponse` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد غلط',
  `unresponse` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد بی جواب',
  `duration` varchar(30) DEFAULT NULL COMMENT 'طول آزمون',
  `nomre` int(11) DEFAULT NULL COMMENT 'نمره',
  `taraz` int(11) DEFAULT NULL COMMENT 'تراز',
  `rotbe` int(11) DEFAULT NULL,
  PRIMARY KEY (`idresult`),
  KEY `quizid` (`quizid`),
  KEY `result_quiz_ibfk_2` (`username`),
  CONSTRAINT `result_quiz_ibfk_1` FOREIGN KEY (`quizid`) REFERENCES `dbquiz` (`idquiz`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `result_quiz_ibfk_2` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40093 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `result_quizzes_jame` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `allquestion` int(11) NOT NULL DEFAULT 0,
  `true_response` int(11) NOT NULL DEFAULT 0,
  `false_response` int(11) NOT NULL DEFAULT 0,
  `unresponse` int(11) NOT NULL DEFAULT 0,
  `duration` varchar(30) DEFAULT NULL,
  `nomre` int(11) DEFAULT NULL,
  `taraz` int(11) DEFAULT NULL,
  `rotbe` int(11) DEFAULT NULL,
  `results_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`results_json`)),
  PRIMARY KEY (`id`),
  KEY `result_quizzes_jame_quiz_id_index` (`quiz_id`),
  KEY `result_quizzes_jame_username_index` (`username`),
  CONSTRAINT `result_quizzes_jame_quiz_id_foreign` FOREIGN KEY (`quiz_id`) REFERENCES `dbquiz` (`idquiz`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `result_quizzes_jame_username_foreign` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1357 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sales_followup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'نام کاربری دانش آموز',
  `sales_admin` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'نام کاربری کارشناس فروش',
  `total_previous_payment` bigint(20) DEFAULT 0 COMMENT 'مبلغ پرداختی قبلی',
  `assign_date` datetime NOT NULL COMMENT 'تاریخ تخصیص به کارشناس',
  `status` varchar(50) DEFAULT 'در انتظار تماس' COMMENT 'وضعیت پیگیری',
  `followup_note` text DEFAULT NULL COMMENT 'توضیحات و نتیجه تماس کارشناس',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'تاریخ آخرین تغییرات',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `sales_admin` (`sales_admin`),
  CONSTRAINT `sales_followup_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `sales_followup_ibfk_2` FOREIGN KEY (`sales_admin`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=199 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `school_admin_roles` (
  `id` tinyint(3) unsigned NOT NULL,
  `role_key` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_admin_roles_key` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `school_assigned_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` varchar(50) DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','done','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_school_tasks_assignee` (`assigned_to`,`status`),
  KEY `idx_school_tasks_due` (`due_date`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `school_notification_reads` (
  `notification_id` bigint(20) unsigned NOT NULL,
  `admin_username` varchar(12) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`,`admin_username`),
  KEY `idx_school_notification_reads_admin` (`admin_username`,`read_at`),
  KEY `idx_school_notification_reads_user_notification` (`admin_username`,`notification_id`),
  CONSTRAINT `fk_school_notification_reads_notification` FOREIGN KEY (`notification_id`) REFERENCES `school_notifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `school_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` text DEFAULT NULL,
  `actor_admin_id` int(11) DEFAULT NULL,
  `actor_username` varchar(12) DEFAULT NULL,
  `codecourse` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `student_username` varchar(15) DEFAULT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `source_type` varchar(60) DEFAULT NULL,
  `source_id` varchar(100) DEFAULT NULL,
  `dedupe_key` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_notifications_dedupe` (`dedupe_key`),
  KEY `idx_school_notifications_type_created` (`event_type`,`created_at`),
  KEY `idx_school_notifications_course_created` (`codecourse`,`created_at`),
  KEY `idx_school_notifications_actor` (`actor_username`,`actor_admin_id`),
  KEY `fk_school_notifications_class` (`class_id`),
  CONSTRAINT `fk_school_notifications_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_school_notifications_course` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `school_staff_courses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_username` varchar(12) NOT NULL,
  `codecourse` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_staff_course` (`admin_username`,`codecourse`),
  KEY `idx_school_staff_courses_course` (`codecourse`),
  CONSTRAINT `fk_school_staff_courses_admin` FOREIGN KEY (`admin_username`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_school_staff_courses_course` FOREIGN KEY (`codecourse`) REFERENCES `codtbl_course` (`codecourse`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `school_staff_insurance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_username` varchar(50) NOT NULL,
  `provider` varchar(150) DEFAULT NULL,
  `policy_number` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_insurance_user` (`admin_username`),
  KEY `idx_staff_insurance_status` (`status`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `school_staff_monthly_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_username` varchar(50) NOT NULL,
  `payment_month` char(7) NOT NULL COMMENT 'Jalali YYYY/MM',
  `amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `paid_at` date DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_monthly_payment` (`admin_username`,`payment_month`),
  KEY `idx_staff_monthly_payment_month` (`payment_month`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `schools` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `city_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_school_city` (`school_name`,`city_id`),
  KEY `city_id` (`city_id`),
  CONSTRAINT `schools_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `city` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=141621 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `setting_public` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_type` enum('school','institute') NOT NULL DEFAULT 'school',
  `act_register_student` tinyint(1) DEFAULT NULL COMMENT 'فعالسازی ثبت نام دانش آموز',
  `act_register_collague` tinyint(1) DEFAULT NULL COMMENT 'فعالسازی ثبت نام همکار',
  `act_login` tinyint(1) DEFAULT NULL COMMENT 'فعالسازی فرم ورود',
  `actsms_success_pay` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'فعالسازی ارسال پیامک موفق درگاه',
  `actsms_success_card` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'فعالسازی پیامک تایید کارت به کارت',
  `act_admin` tinyint(4) NOT NULL DEFAULT 1,
  `wage` float DEFAULT 0 COMMENT 'کارمزد همکار(درصد)',
  `act_register_student_two` tinyint(4) NOT NULL DEFAULT 0,
  `act_register_collague_two` tinyint(4) NOT NULL DEFAULT 0,
  `act_login_two` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `simotel_calls_data` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `codemeli` varchar(12) NOT NULL,
  `operator_username` varchar(12) NOT NULL,
  `snapshot_day` date NOT NULL,
  `total_calls` int(10) unsigned DEFAULT 0,
  `total_talk_time` int(10) unsigned DEFAULT 0,
  `unanswered_calls` int(10) unsigned DEFAULT 0,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daily_snapshot` (`codemeli`,`operator_username`,`snapshot_day`),
  KEY `idx_student` (`codemeli`),
  KEY `idx_operator` (`operator_username`),
  KEY `idx_day` (`snapshot_day`)
) ENGINE=InnoDB AUTO_INCREMENT=4852487 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sms_bulk_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bulk_id` int(11) NOT NULL,
  `sender` varchar(20) NOT NULL,
  `receivers_count` int(11) NOT NULL,
  `message` text NOT NULL,
  `send_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bulk_id` (`bulk_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sms_inbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_number` varchar(20) NOT NULL,
  `to_number` varchar(20) NOT NULL,
  `body` text NOT NULL,
  `received_at` datetime NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `response` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `from_number` (`from_number`),
  KEY `to_number` (`to_number`),
  KEY `received_at` (`received_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) NOT NULL,
  `message_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `sender_admin` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `send_date` date NOT NULL,
  `send_time` time NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_send_date` (`send_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=798 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `stdhozor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(13) DEFAULT NULL,
  `hozor` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `student_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(12) NOT NULL,
  `student_comment` text DEFAULT NULL,
  `supporter_comment` text DEFAULT NULL,
  `avg_first_semester` varchar(50) DEFAULT NULL,
  `avg_second_semester` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_student_status` (`student_id`),
  CONSTRAINT `fk_student_status` FOREIGN KEY (`student_id`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3181 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `student_supporter_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codemeli` varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `comment` text NOT NULL,
  `created_by` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `codemeli` (`codemeli`),
  CONSTRAINT `student_supporter_comments_ibfk_1` FOREIGN KEY (`codemeli`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4098 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `student_suspension_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `requested_by` varchar(100) NOT NULL COMMENT 'username اپراتور/همکار ارسال‌کننده',
  `requested_mode` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '1=suspend, 0=unsuspend',
  `requested_reason` varchar(511) DEFAULT NULL COMMENT 'علت درخواست تعلیق',
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0=pending,1=approved,2=rejected',
  `reviewed_by` varchar(100) DEFAULT NULL COMMENT 'username ادمین بررسی‌کننده',
  `reject_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'دلیل رد کردن',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'idadmin',
  `reviewed_at` datetime DEFAULT NULL COMMENT 'زمان بررسی درخواست',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `seen_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_status` (`student_username`,`status`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_requested_by` (`requested_by`),
  KEY `fk_student_suspension_requests_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_student_suspension_requests_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `dbadmin` (`idadmin`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_student_suspension_requests_student_username` FOREIGN KEY (`student_username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=262 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `subchapter_addon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chapter_id` (`chapter_id`),
  KEY `addon_id` (`addon_id`),
  CONSTRAINT `subchapter_addon_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `subchapters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `subchapter_addon_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `dbaddon` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `subchapter_movie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chapter_id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chapter_id` (`chapter_id`),
  KEY `movie_id` (`movie_id`),
  CONSTRAINT `subchapter_movie_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `subchapters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `subchapter_movie_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `dbmovie` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=238 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `subchapters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `subchapters_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `dblesson` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `suport_message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_type` enum('student','admin','','') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `sender_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `sender_ip` varchar(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `receiver_type` enum('student','admin','','') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `receiver_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `message_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `status` enum('sent','seen','','') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'sent',
  `message_date` date DEFAULT NULL,
  `message_time` time DEFAULT NULL,
  `edit_message_date` date DEFAULT NULL,
  `edit_message_time` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33363 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `suport_message_constant` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COLLATE=utf8_persian_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `suport_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `type` varchar(100) NOT NULL,
  `status` varchar(100) NOT NULL,
  `datesupport` date NOT NULL,
  `detail` text NOT NULL,
  `poshtiban` varchar(100) NOT NULL,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0=normal, 1=important, 2=really important',
  `ip` varchar(30) NOT NULL,
  `os` varchar(255) NOT NULL,
  `datenow` date NOT NULL,
  `timenow` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  CONSTRAINT `suport_report_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38658 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `suport_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `typesupport` int(11) NOT NULL DEFAULT 0 COMMENT 'نوع پشتیبانی',
  `lastdate` date NOT NULL COMMENT 'آخرین پشتیبانی',
  `afterdate` date NOT NULL COMMENT 'پشتیبانی بعدی',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  CONSTRAINT `suport_user_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2948 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tbl_attendanced` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classname` varchar(255) DEFAULT NULL,
  `codecourse` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `datestart` date NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `tbl_attendanced_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `handhug_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=856 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `teacher_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tgm` (`group_id`,`admin_id`),
  KEY `idx_tgm_admin` (`admin_id`),
  CONSTRAINT `fk_tgm_group` FOREIGN KEY (`group_id`) REFERENCES `teacher_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `teacher_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_groups_title` (`title`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `teacher_lesson_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_username` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `class_id` int(11) NOT NULL,
  `dars_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `plan_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_lesson_plan_unique` (`teacher_username`,`class_id`,`dars_id`,`lesson_id`,`plan_date`),
  KEY `idx_teacher_lesson_plans_date` (`plan_date`),
  KEY `idx_teacher_lesson_plans_class` (`class_id`),
  KEY `idx_teacher_lesson_plans_dars` (`dars_id`),
  KEY `idx_teacher_lesson_plans_lesson` (`lesson_id`),
  CONSTRAINT `tlp_class_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tlp_dars_fk` FOREIGN KEY (`dars_id`) REFERENCES `dbdars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tlp_lesson_fk` FOREIGN KEY (`lesson_id`) REFERENCES `dblesson` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tlp_teacher_fk` FOREIGN KEY (`teacher_username`) REFERENCES `dbadmin` (`username`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `teacher_ticket` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `codemeli` varchar(12) DEFAULT NULL,
  `teacher_username` varchar(12) NOT NULL,
  `target_group` enum('admins','management','operators') NOT NULL,
  `responder_username` varchar(12) DEFAULT NULL,
  `title` varchar(250) DEFAULT NULL,
  `status` enum('open','chatting','resolved') NOT NULL DEFAULT 'open',
  `class_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher_ticket_codemeli` (`codemeli`),
  KEY `idx_teacher_ticket_teacher_username` (`teacher_username`),
  KEY `fk_teacher_ticket_class_id` (`class_id`),
  CONSTRAINT `fk_teacher_ticket_class_id` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `teacher_ticket_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `teacher_ticket_id` bigint(20) NOT NULL,
  `sender_username` varchar(12) NOT NULL,
  `body` text NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `seen_by_teacher` tinyint(1) NOT NULL DEFAULT 0,
  `seen_by_respounder` tinyint(1) NOT NULL DEFAULT 0,
  `seen_at_teacher` datetime DEFAULT NULL,
  `seen_at_respounder` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_ticket_messages_ticket_id` (`teacher_ticket_id`),
  KEY `idx_teacher_ticket_messages_sender_username` (`sender_username`),
  CONSTRAINT `fk_teacher_ticket_messages_ticket` FOREIGN KEY (`teacher_ticket_id`) REFERENCES `teacher_ticket` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tel_db` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group` int(11) NOT NULL,
  `fname` varchar(100) NOT NULL,
  `lname` varchar(150) NOT NULL,
  `guidance` varchar(255) NOT NULL COMMENT 'نام راهنما',
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(150) DEFAULT NULL,
  `mob1` varchar(11) NOT NULL COMMENT 'موبایل1',
  `mob2` varchar(11) DEFAULT NULL COMMENT 'موبایل2',
  `mob3` varchar(11) DEFAULT NULL COMMENT 'موبایل3',
  `tel1` varchar(11) DEFAULT NULL COMMENT 'تلفن ثابت1',
  `tel2` varchar(11) DEFAULT NULL COMMENT 'تلفن ثابت2',
  `tel3` varchar(11) DEFAULT NULL COMMENT 'تلفن ثابت3',
  `metod` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'متد',
  `act` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'اقدام',
  `dakheli` varchar(6) NOT NULL COMMENT 'داخلی',
  `updator` varchar(12) DEFAULT NULL COMMENT 'بروزرسانی کننده',
  `dateupdate` date DEFAULT NULL,
  `timeupdate` time DEFAULT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  `nextcall` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group` (`group`),
  KEY `dakheli` (`dakheli`),
  CONSTRAINT `tel_db_ibfk_1` FOREIGN KEY (`group`) REFERENCES `tel_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tel_db_ibfk_2` FOREIGN KEY (`dakheli`) REFERENCES `dbadmin` (`dakheli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=106142 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tel_detail` (
  `iddetail` int(11) NOT NULL AUTO_INCREMENT,
  `dbtel` int(11) NOT NULL,
  `txt` text NOT NULL,
  `txt2` text DEFAULT NULL,
  `txt3` text DEFAULT NULL,
  `box` varchar(50) DEFAULT NULL COMMENT 'تماس با باکس',
  `useradder` varchar(12) DEFAULT NULL,
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  PRIMARY KEY (`iddetail`),
  KEY `dbtel` (`dbtel`),
  CONSTRAINT `tel_detail_ibfk_1` FOREIGN KEY (`dbtel`) REFERENCES `tel_db` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=75848 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tel_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `namegroup` varchar(255) NOT NULL COMMENT 'نام گروه تلفن',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tel_sms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dbtel` int(11) NOT NULL,
  `useradder` varchar(12) NOT NULL,
  `box` varchar(100) DEFAULT NULL,
  `tel` varchar(12) NOT NULL,
  `olgo` int(11) DEFAULT NULL,
  `txt` text NOT NULL,
  `response` varchar(255) NOT NULL,
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dbtel` (`dbtel`),
  CONSTRAINT `tel_sms_ibfk_1` FOREIGN KEY (`dbtel`) REFERENCES `tel_db` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=213 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `temporary_personal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `grade` tinyint(3) unsigned DEFAULT NULL,
  `phone` varchar(15) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `major` varchar(100) DEFAULT NULL,
  `codemeli` varchar(10) DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL COMMENT 'null = 7.8 mil   original data. \r\n0 = emdad.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codemeli` (`codemeli`),
  KEY `idx_phone` (`phone`),
  KEY `idx_state_city` (`state`,`city`),
  KEY `idx_major` (`major`),
  KEY `idx_grade` (`grade`),
  KEY `idx_city` (`city`),
  KEY `idx_temp_codemeli` (`codemeli`),
  KEY `idx_phone_name` (`phone`,`last_name`,`first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ticket_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_num` int(11) NOT NULL,
  `detail` text NOT NULL,
  `useradder` varchar(12) NOT NULL,
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_num` (`ticket_num`),
  CONSTRAINT `ticket_meta_ibfk_1` FOREIGN KEY (`ticket_num`) REFERENCES `ticket_topic` (`ticket_num`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1852 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ticket_referrals` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) NOT NULL,
  `ticket_type` varchar(100) NOT NULL,
  `referred_by_id` int(11) DEFAULT NULL,
  `referred_to_id` int(11) DEFAULT NULL,
  `referrer_comment` text DEFAULT NULL,
  `referee_comment` text DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL COMMENT 'NULL=Pending, 1=Solved, 0=Not Solved',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ticket_referrals_referred_by` (`referred_by_id`),
  KEY `fk_ticket_referrals_referred_to` (`referred_to_id`),
  CONSTRAINT `fk_ticket_referrals_referred_by` FOREIGN KEY (`referred_by_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ticket_referrals_referred_to` FOREIGN KEY (`referred_to_id`) REFERENCES `dbadmin` (`idadmin`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ticket_topic` (
  `ticket_num` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL COMMENT 'نام کاربری ',
  `ticket_section` varchar(100) NOT NULL COMMENT 'ارتباط با ',
  `metod` tinyint(1) NOT NULL DEFAULT 0,
  `responser` varchar(12) DEFAULT NULL COMMENT 'پاسخ دهنده',
  `ticket_pririoty` varchar(30) NOT NULL COMMENT 'درجه اهمیت',
  `ticket_title` varchar(255) DEFAULT NULL,
  `ticket_status` varchar(30) NOT NULL DEFAULT 'منتظر پاسخ کارشناس',
  `op_status` enum('pending','accepted','rejected') DEFAULT NULL,
  `datecreated` date NOT NULL,
  `timecreated` time NOT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `os` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ticket_num`),
  KEY `username` (`username`),
  KEY `responser` (`responser`),
  CONSTRAINT `ticket_topic_ibfk_1` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ticket_topic_ibfk_2` FOREIGN KEY (`responser`) REFERENCES `dbadmin` (`username`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=926 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `tmp_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classname` varchar(255) DEFAULT NULL,
  `codecourse` int(11) NOT NULL,
  `datestart` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=319 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `type_vip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `user_account_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(12) NOT NULL,
  `status` tinyint(4) NOT NULL COMMENT '0=active 1=deactivated',
  `reason` varchar(255) DEFAULT NULL,
  `created_by` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_uas_user` (`username`),
  CONSTRAINT `fk_uas_user` FOREIGN KEY (`username`) REFERENCES `dbpersonal` (`codemeli`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=563 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `state` WRITE;
/*!40000 ALTER TABLE `state` DISABLE KEYS */;
INSERT  IGNORE INTO `state` VALUES (1,'آذربایجان شرقی'),(2,'آذربایجان غربی'),(3,'اردبیل'),(4,'اصفهان'),(5,'البرز'),(6,'ایلام'),(7,'بوشهر'),(8,'تهران'),(9,'چهارمحال وبختیاری'),(10,'خراسان جنوبی'),(11,'خراسان رضوی'),(12,'خراسان شمالی'),(13,'خوزستان'),(14,'زنجان'),(15,'سمنان'),(16,'سیستان و بلوچستان'),(17,'فارس'),(18,'قزوین'),(19,'قم'),(20,'کردستان'),(21,'کرمان'),(22,'کرمانشاه'),(23,'کهگیلویه و بویراحمد'),(24,'گلستان'),(25,'گیلان'),(26,'لرستان'),(27,'مازندران'),(28,'مرکزی'),(29,'هرمزگان'),(30,'همدان'),(31,'یزد'),(32,'تهران (شهرستان‌ها)');
/*!40000 ALTER TABLE `state` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `city` WRITE;
/*!40000 ALTER TABLE `city` DISABLE KEYS */;
INSERT  IGNORE INTO `city` VALUES (1,'آذرشهر',1),(2,'اسکو',1),(3,'اهر',1),(4,'بستان آباد',1),(5,'بناب',1),(6,'تبریز',1),(7,'جلفا',1),(9,'سراب',1),(10,'شبستر',1),(12,'کلیبر',1),(13,'مراغه',1),(14,'مرند',1),(15,'ملکان',1),(16,'میانه',1),(17,'ورزقان',1),(18,'هریس',1),(19,'هشترود',1),(20,'ارومیه',2),(21,'اشنویه',2),(22,'بوکان',2),(24,'تکاب',2),(25,'چالدران',2),(26,'خوی',2),(27,'سردشت',2),(28,'سلماس',2),(29,'شاهین دژ',2),(30,'ماکو',2),(31,'مهاباد',2),(32,'میاندوآب',2),(33,'نقده',2),(34,'اردبیل',3),(35,'بیله سوار',3),(36,'پارس آباد',3),(37,'خلخال',3),(38,'کوثر',3),(41,'نمین',3),(42,'نیر',3),(43,'آران و بیدگل',4),(44,'اردستان',4),(45,'اصفهان',4),(48,'چادگان',4),(49,'خمینی شهر',4),(50,'خوانسار',4),(51,'سمیرم',4),(52,'شاهین شهر و میمه',4),(53,'شهر رضا',4),(54,'سمیرم سفلی',4),(55,'فریدن',4),(56,'فریدون شهر',4),(57,'فلاورجان',4),(58,'کاشان',4),(59,'گلپایگان',4),(61,'مبارکه',4),(63,'نجف آباد',4),(64,'نطنز',4),(65,'ساوجبلاغ',5),(66,'کرج',5),(67,'نظرآباد',5),(68,'طالقان',5),(69,'آبدانان',6),(70,'ایلام',6),(71,'ایوان',6),(72,'دره شهر',6),(73,'دهلران',6),(75,'مهران',6),(76,'بوشهر',7),(81,'دیر',7),(82,'دیلم',7),(83,'کنگان',7),(84,'گناوه',7),(87,'تهران',8),(95,'اردل',9),(96,'بروجن',9),(97,'شهرکرد',9),(98,'فارسان',9),(99,'کوهرنگ',9),(100,'لردگان',9),(101,'بیرجند',10),(102,'درمیان',10),(103,'سرایان',10),(104,'سر بیشه',10),(105,'فردوس',10),(107,'نهبندان',10),(109,'بجستان',11),(110,'تایباد',11),(111,'تحت جلگه',11),(112,'تربت جام',11),(113,'تربت حیدریه',11),(114,'چناران',11),(115,'جغتای',11),(116,'جوین',11),(117,'خلیل آباد',11),(118,'خواف',11),(119,'درگز',11),(120,'رشتخوار',11),(121,'زاوه',11),(122,'سبزوار',11),(123,'سرخس',11),(124,'فریمان',11),(125,'قوچان',11),(127,'کاشمر',11),(128,'کلات',11),(129,'گناباد',11),(130,'مشهد',11),(131,'مه ولات',11),(132,'نیشابور',11),(133,'اسفراین',12),(134,'بجنورد',12),(135,'جاجرم',12),(136,'شیروان',12),(137,'فاروج',12),(139,'آبادان',13),(140,'امیدیه',13),(141,'اندیمشک',13),(142,'اهواز',13),(143,'ایذه',13),(146,'بهبهان',13),(147,'خرمشهر',13),(148,'دزفول',13),(149,'دشت آزادگان',13),(150,'رامشیر',13),(151,'رامهرمز',13),(152,'شادگان',13),(153,'شوش',13),(154,'شوشتر',13),(155,'گتوند',13),(156,'لالی',13),(158,'هندیجان',13),(159,'ابهر',14),(160,'ایجرود',14),(161,'خدابنده',14),(162,'خرمدره',14),(163,'زنجان',14),(164,'طارم',14),(165,'ماه نشان',14),(166,'دامغان',15),(167,'سمنان',15),(168,'شاهرود',15),(169,'گرمسار',15),(170,'مهدی شهر',15),(171,'ایرانشهر',16),(173,'خاش',16),(174,'دلگان',16),(175,'زابل',16),(176,'زاهدان',16),(177,'زهک',16),(178,'سراوان',16),(179,'سرباز',16),(180,'کنارک',16),(182,'آباده',17),(183,'ارسنجان',17),(184,'استهبان',17),(185,'اقلید',17),(186,'بوانات',17),(187,'پاسارگاد',17),(188,'جهرم',17),(189,'خرم بید',17),(190,'خنج',17),(191,'داراب',17),(192,'زرین دشت',17),(193,'سپیدان',17),(194,'شیراز',17),(195,'فراشبند',17),(196,'فسا',17),(197,'فیروزآباد',17),(199,'کازرون',17),(200,'لارستان',17),(201,'لامرد',17),(202,'مرودشت',17),(203,'ممسنی',17),(204,'مهر',17),(205,'نی ریز',17),(206,'آبیک',18),(207,'البرز',18),(208,'بوئین زهرا',18),(209,'تاکستان',18),(210,'قزوین',18),(211,'قم',19),(212,'بانه',20),(213,'بیجار',20),(214,'دیواندره',20),(215,'سروآباد',20),(216,'سقز',20),(217,'سنندج',20),(218,'قروه',20),(219,'کامیاران',20),(220,'مریوان',20),(221,'بافت',21),(222,'بردسیر',21),(223,'بم',21),(224,'جیرفت',21),(225,'راور',21),(226,'رفسنجان',21),(228,'زرند',21),(229,'سیرجان',21),(231,'عنبرآباد',21),(232,'قلعه گنج',21),(233,'کرمان',21),(234,'کوهبنان',21),(235,'کهنوج',21),(236,'منوجان',21),(238,'پاوه',22),(239,'ثلاث باباجانی',22),(240,'جوانرود',22),(242,'روانسر',22),(245,'صحنه',22),(247,'کرمانشاه',22),(248,'کنگاور',22),(250,'هرسین',22),(252,'بهمئی',23),(253,'دنا',23),(255,'گچساران',23),(256,'آزادشهر',24),(257,'آق قلا',24),(260,'رامیان',24),(261,'علی آباد',24),(263,'کلاله',24),(264,'گرگان',24),(267,'آستارا',25),(268,'آستانه اشرفیه',25),(269,'املش',25),(271,'رشت',25),(272,'رضوانشهر',25),(273,'رودبار',25),(274,'رودسر',25),(275,'سیاهکل',25),(276,'شفت',25),(277,'صومعه سرا',25),(278,'تالش',25),(279,'فومن',25),(280,'لاهیجان',25),(281,'لنگرود',25),(283,'ازنا',26),(284,'الیگودرز',26),(285,'بروجرد',26),(287,'خرم آباد',26),(288,'دورود',26),(299,'کهک',19),(360,'خمام',25),(377,'دلفان',26),(379,'کوهدشت',26),(380,'الشتر',26),(382,'آمل',27),(383,'بابل',27),(384,'بابلسر',27),(385,'بهشهر',27),(386,'تنکابن',27),(387,'جویبار',27),(388,'چالوس',27),(389,'رامسر',27),(390,'ساری',27),(391,'سوادکوه',27),(392,'قائم شهر',27),(393,'گلوگاه',27),(394,'محمود آباد',27),(396,'نور',27),(397,'نوشهر',27),(398,'فریدونکنار',27),(399,'آشتیان',28),(400,'اراک',28),(401,'تفرش',28),(402,'خمین',28),(403,'دلیجان',28),(404,'زرندیه',28),(405,'ساوه',28),(407,'کمیجان',28),(408,'محلات',28),(409,'بندرعباس',29),(410,'میناب',29),(413,'جاسک',29),(414,'قشم',29),(415,'حاجی آباد',29),(416,'ابوموسی',29),(417,'بستک',29),(419,'خمیر',29),(420,'اسدآباد',30),(421,'بهار',30),(422,'تویسرکان',30),(423,'رزن',30),(425,'ملایر',30),(426,'نهاوند',30),(427,'همدان',30),(428,'ابرکوه',31),(429,'اردکان',31),(430,'بافق',31),(431,'تفت',31),(432,'خاتم',31),(433,'صدوق',31),(435,'مهریز',31),(436,'میبد',31),(437,'یزد',31),(443,'فامنین',30),(447,'انار',21),(449,'بدره',6),(450,'ملکشاهی',6),(451,'هلیلان',6),(452,'عسلویه',7),(454,'سرعین',3),(455,'انگوت',3),(456,'خداآفرین',1),(457,'هوراند',1),(459,'نظرکهریز',1),(461,'مهربان',1),(463,'گوگان',1),(464,'کندوان',1),(466,'کاغذکنان',1),(467,'عشایری',1),(468,'عجب شیر',1),(469,'صوفیان',1),(470,'خواجه',1),(471,'خسروشهر',1),(472,'خاروانا',1),(473,'چاراویماق',1),(474,'تیکمه داش',1),(475,'تسوج',1),(476,'ترکمانچای',1),(477,'تبریز - ناحیه 5',1),(478,'تبریز - ناحیه 4',1),(479,'تبریز - ناحیه 3',1),(480,'تبریز - ناحیه 2',1),(481,'تبریز - ناحیه 1',1),(482,'ایلخچی',1),(484,'نازلو',2),(486,'مرحمت آباد',2),(488,'کشاورز',2),(489,'صومای برادوست',2),(490,'شوط',2),(492,'سیلوانا',2),(494,'چایپاره',2),(496,'پیران شهر',2),(497,'پلدشت',2),(499,'انزل',2),(501,'ارومیه - ناحیه 2',2),(502,'ارومیه - ناحیه 1',2),(503,'هیر',3),(506,'مغان (گرمی)',3),(507,'مشکین شهر',3),(508,'مشکین شرقی (لاهرود)',3),(510,'قشلاق دشت',3),(511,'شاهرود',3),(512,'خورش رستم',3),(514,'اصلان دوز',3),(515,'ارشق',3),(516,'اردبیل - ناحیه 2',3),(517,'اردبیل - ناحیه 1',3),(518,'ورزنه',4),(519,'هرند',4),(520,'نایین',4),(521,'ناحیه 6(جی)',4),(522,'میمه',4),(523,'مهردشت',4),(526,'کوهپایه',4),(528,'فولاد شهر',4),(531,'شهرضا',4),(532,'شاهین شهر',4),(534,'زواره',4),(535,'زرین شهر',4),(536,'دهاقان',4),(537,'خوروبیابانک',4),(539,'جرقویه علیا',4),(540,'جرقویه',4),(541,'تیران وکرون',4),(542,'پیربکران',4),(543,'بوئین میاندشت',4),(544,'برخوار',4),(545,'باغبهادران',4),(546,'امامزاده',4),(547,'اصفهان - ناحیه 4',4),(548,'اصفهان - ناحیه 3',4),(549,'اصفهان - ناحیه 2',4),(550,'اصفهان - ناحیه 1',4),(551,'اداره ناحیه 5',4),(553,'کرج - ناحیه 4',5),(554,'کرج - ناحیه 3',5),(555,'کرج - ناحیه 2',5),(556,'کرج - ناحیه 1',5),(557,'اشتهارد',5),(558,'موسیان',6),(559,'شیروان',6),(560,'زرین آباد',6),(561,'چوار',6),(562,'چرداول',6),(566,'کاکی',7),(567,'شبانکاره',7),(568,'سعدآباد',7),(569,'ساحلی (دلوار)',7),(570,'ریگ',7),(573,'دشتی (خورموج)',7),(574,'دشتستان (برازجان)',7),(575,'خارک',7),(576,'جم وریز',7),(577,'تنگستان (اهرم)',7),(578,'بردخون',7),(579,'آب بخش',7),(580,'تهران - منطقه 19',8),(581,'تهران - منطقه 18',8),(582,'تهران - منطقه 17',8),(583,'تهران - منطقه 16',8),(584,'تهران - منطقه 15',8),(585,'تهران - منطقه 14',8),(586,'تهران - منطقه 13',8),(587,'تهران - منطقه 12',8),(588,'تهران - منطقه 11',8),(589,'تهران - منطقه 10',8),(590,'تهران - منطقه 09',8),(591,'تهران - منطقه 08',8),(592,'تهران - منطقه 07',8),(593,'تهران - منطقه 06',8),(594,'تهران - منطقه 05',8),(595,'تهران - منطقه 04',8),(596,'تهران - منطقه 03',8),(597,'تهران - منطقه 02',8),(598,'تهران - منطقه 01',8),(599,'قائنات',10),(600,'طبس',10),(601,'سربیشه',10),(603,'زیرکوه حاجی آباد',10),(604,'دستگردان',10),(606,'خوسف',10),(608,'بشرویه',10),(610,'نوخندان',11),(611,'میان جلگه',11),(612,'مشهد - ناحیه 7',11),(613,'مشهد - ناحیه 6',11),(614,'مشهد - ناحیه 5',11),(615,'مشهد - ناحیه 4',11),(616,'مشهد - ناحیه 3',11),(617,'مشهد - ناحیه 2',11),(618,'مشهد - ناحیه 1',11),(619,'گل بهار',11),(620,'کوهسرخ',11),(622,'کدکن',11),(625,'طرقبه',11),(626,'صالح آباد',11),(627,'ششتمد',11),(628,'سرولایت',11),(629,'زبرخان',11),(630,'رضویه',11),(631,'داورزن',11),(632,'خوشاب',11),(635,'جلگه رخ',11),(638,'تبادکان',11),(640,'بردسکن',11),(641,'باخرز',11),(642,'احمدآباد',11),(643,'مانه وسملقان',12),(644,'گرمه',12),(646,'رازوجرگلان',12),(647,'بام وصفی آباد',12),(649,'هویزه',13),(651,'هفتگل',13),(652,'مسجدسلیمان',13),(653,'ماهشهر',13),(655,'کارون',13),(656,'عشایری',13),(657,'صیدون',13),(658,'شاوور',13),(659,'سردشت',13),(660,'زیدون',13),(662,'دهدز',13),(663,'حمیدیه',13),(664,'بندر امام خمینی',13),(665,'بستان',13),(666,'باوی',13),(667,'باغملک جانکی',13),(669,'اهواز - ناحیه 4',13),(670,'اهواز - ناحیه 3',13),(671,'اهواز - ناحیه 2',13),(672,'اهواز - ناحیه 1',13),(674,'اندیکا',13),(676,'الوارگرم سیری',13),(677,'اروندکنار',13),(678,'آغاجاری',13),(679,'سلطانیه',14),(680,'سجاسرود',14),(681,'زنجانرود',14),(682,'زنجان - ناحیه 2',14),(683,'زنجان - ناحیه 1',14),(684,'بزینه رود',14),(686,'انگوران',14),(687,'افشار',14),(688,'میامی',15),(690,'سرخه',15),(691,'بیارجمند',15),(692,'بسطام',15),(693,'امیرآباد',15),(694,'آرادان',15),(695,'هیرمند',16),(696,'نیک شهر',16),(697,'نوک آباد',16),(698,'نصرت آباد',16),(699,'میرجاوه',16),(700,'لاشار',16),(702,'قصرقند',16),(703,'فنوج',16),(704,'عشایری سیستان بلوچستان',16),(705,'شیب آب',16),(706,'سیب وسوران',16),(708,'زرآباد',16),(709,'زاهدان - ناحیه 2',16),(710,'زاهدان - ناحیه 1',16),(711,'زابلی',16),(712,'راسک',16),(713,'دشتیاری',16),(714,'چا بهار',16),(715,'جالق',16),(716,'پشت آب',16),(717,'بنجار',16),(718,'بنت',16),(719,'بمپور',16),(720,'بم پشت',16),(721,'بزمان',16),(723,'اداره آموزش و پرورش پلان',16),(725,'میمند',17),(727,'مشهدمرغاب (قادرآباد)',17),(728,'ماهورمیلاتی',17),(729,'گله دار',17),(730,'گراش',17),(731,'کوار',17),(732,'کهمره',17),(733,'کامفیروز',17),(735,'قیروکارزین',17),(736,'قایمیه',17),(738,'فرگ',17),(739,'علامرودشت',17),(740,'عشایری فارس',17),(741,'شیراز - ناحیه 4',17),(742,'شیراز - ناحیه 3',17),(743,'شیراز - ناحیه 2',17),(744,'شیراز - ناحیه 1',17),(745,'شیب کوه',17),(746,'ششده وقره بلاغ',17),(747,'سروستان',17),(748,'سرچهان',17),(749,'سده',17),(752,'زرقان',17),(753,'رستم',17),(754,'دشمن زیاری',17),(755,'درودزن',17),(756,'خفر',17),(757,'خشت وکمارج',17),(759,'خرامه',17),(760,'جویم',17),(761,'بیضا',17),(762,'بیرم',17),(763,'بنارویه',17),(764,'بالاده',17),(765,'اوز',17),(767,'اشکنان',17),(768,'ارژن (کوهمره سرخی)',17),(769,'آباده طشک',17),(770,'قزوین - ناحیه 2',18),(771,'قزوین - ناحیه 1',18),(772,'طارم سفلی',18),(773,'ضیاء آباد',18),(774,'شال',18),(775,'رودبارشهرستان',18),(776,'رودبارالموت',18),(777,'خرمدشت',18),(780,'اسفرورین',18),(781,'آوج',18),(783,'قم - ناحیه 4',19),(784,'قم - ناحیه 3',19),(785,'قم - ناحیه 2',19),(786,'قم - ناحیه 1',19),(787,'خلجستان',19),(788,'جعفرآباد',19),(789,'نوق',21),(790,'نرماشیر',21),(791,'ماهان',21),(792,'گلباف',21),(795,'کشکوئیه',21),(796,'کرمان - ناحیه 2',21),(797,'کرمان - ناحیه 1',21),(798,'فهرج',21),(799,'فاریاب',21),(800,'عشایری',21),(801,'شهربابک',21),(802,'شهداد',21),(804,'ریگان',21),(805,'رودبار',21),(806,'راین',21),(807,'رابر',21),(808,'چترود',21),(810,'جازموریان',21),(812,'ارزوئیه',21),(814,'نوسود',22),(815,'ماهیدشت',22),(816,'گیلانغرب',22),(817,'گواور',22),(818,'گهواره',22),(820,'کرندغرب',22),(821,'کرمانشاه - ناحیه 3',22),(822,'کرمانشاه - ناحیه 2',22),(823,'کرمانشاه - ناحیه 1',22),(824,'قصرشیرین',22),(825,'سنقرکلیایی',22),(826,'سرپل ذهاب',22),(827,'دینور',22),(828,'حمیل',22),(830,'بیستون',22),(831,'باینگان',22),(832,'اسلام آباد',22),(833,'مینودشت',24),(834,'مراوه تپه',24),(835,'گنبدکاووس',24),(836,'گمیشان',24),(837,'گالیکش',24),(839,'کردکوی',24),(842,'بندرگز',24),(843,'بندرترکمن',24),(844,'ماسال وشاندرمن',25),(845,'لشت نشاء',25),(847,'کومله',25),(848,'کوچصفهان',25),(849,'کلاچای',25),(850,'عمارلو',25),(852,'سنگر',25),(853,'رودبنه',25),(854,'رشت - ناحیه 2',25),(855,'رشت - ناحیه 1',25),(856,'رحیم آباد',25),(857,'رحمت آبادوبلوکات',25),(858,'دیلمان',25),(859,'خشکبیجار',25),(860,'حویق',25),(861,'چابکسر',25),(862,'تولمات',25),(863,'بندرکیاشهر',25),(864,'بندرانزلی',25),(865,'اطاقور',25),(867,'ویسیان',26),(868,'معمولان',26),(869,'کوهنانی',26),(871,'کاکاوند',26),(872,'عشایری لرستان',26),(873,'طرحان',26),(874,'سیلاخور',26),(875,'ززوماهرو',26),(876,'زاغه',26),(877,'رومشگان',26),(878,'خرم آباد - ناحیه 2',26),(879,'خرم آباد - ناحیه 1',26),(880,'چگنی',26),(881,'چغلوندی',26),(882,'پل دختر',26),(883,'پاپی (راه آهن)',26),(885,'اشترینان',26),(886,'نکاء',27),(887,'میاندرود',27),(888,'مرزن آباد',27),(889,'لاریجان',27),(890,'کیاکلا',27),(891,'کلاردشت',27),(892,'کجور',27),(893,'فریدون کنار',27),(894,'عباس آباد',27),(895,'شیرگاه',27),(897,'ساری - ناحیه 2',27),(898,'ساری - ناحیه 1',27),(899,'دودانگه',27),(900,'چهاردانگه',27),(901,'چمستان',27),(904,'بهنمیر',27),(905,'بندپی غربی',27),(906,'بندپی شرقی',27),(907,'بلده',27),(908,'نوبران',28),(910,'فراهان',28),(911,'شازند (سربند)',28),(912,'سربند',28),(915,'خنداب (شرا)',28),(917,'اراک - ناحیه 2',28),(918,'اراک - ناحیه 1',28),(920,'هرمز',29),(922,'لیردف',29),(923,'کیش',29),(924,'فین وسیاهو',29),(925,'شیب کوه',29),(926,'شهاب',29),(927,'سندرک',29),(928,'رودخانه',29),(929,'رودان',29),(932,'جناح',29),(934,'پارسیان',29),(935,'بیابان سیریک',29),(936,'بندرلنگه',29),(937,'بندرعباس - ناحیه 2',29),(938,'بندرعباس - ناحیه 1',29),(939,'بشاگرد',29),(942,'همدان - ناحیه 2',30),(943,'همدان - ناحیه 1',30),(945,'لالجین',30),(946,'گل تپه',30),(947,'کبودرآهنگ',30),(948,'قهاوند شرا',30),(949,'قلقل رود (فرسفج)',30),(950,'سردرود',30),(951,'سامن',30),(952,'درگزین',30),(953,'خزل',30),(954,'جوکار',30),(956,'یزد - ناحیه 2',31),(957,'یزد - ناحیه 1',31),(958,'نیر',31),(961,'مروست',31),(962,'زارچ',31),(963,'بهاباد',31),(968,'بلداجی',9),(969,'بن',9),(970,'خانمیرزا',9),(971,'سامان',9),(972,'شهر کرد - ناحیه 1',9),(973,'شهر کرد - ناحیه 2',9),(974,'عشایری چهارمحال و بختیاری',9),(975,'فلارد',9),(976,'کیار',9),(977,'گندمان',9),(978,'لاران',9),(979,'میانکوه',9),(980,'دهگلان',20),(981,'زیویه',20),(982,'سریش آباد',20),(983,'سنندج - ناحیه 1',20),(984,'سنندج - ناحیه 2',20),(985,'کرانی',20),(986,'کلاترزان',20),(987,'موچش',20),(995,'باشت',23),(996,'چاروسا',23),(997,'چرام',23),(998,'دهدشت (کهکیلویه)',23),(999,'دیشموک',23),(1000,'عشایری',23),(1001,'لنده',23),(1002,'لوداب',23),(1003,'مارگون',23),(1004,'یاسوج',23),(1010,'اسلام شهر',32),(1011,'بهارستان - ناحیه 1',32),(1012,'بهارستان - ناحیه 2',32),(1013,'پاکدشت',32),(1014,'پردیس',32),(1015,'پیشوا',32),(1016,'جوادآباد',32),(1017,'چهاردانگه',32),(1018,'دماوند',32),(1019,'رباطکریم',32),(1020,'رودهن',32),(1021,'شهرری - ناحیه 1',32),(1022,'شهرری - ناحیه 2',32),(1023,'شهرقدس',32),(1024,'شهریار',32),(1025,'فشافویه',32),(1026,'فیروزکوه',32),(1027,'قرچک',32),(1028,'کهریزک',32),(1029,'ملارد',32),(1030,'ورامین',32);
/*!40000 ALTER TABLE `city` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `setting_public` WRITE;
/*!40000 ALTER TABLE `setting_public` DISABLE KEYS */;
INSERT  IGNORE INTO `setting_public` VALUES (1,'school',1,1,1,1,1,1,0,1,1,1);
/*!40000 ALTER TABLE `setting_public` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `institution_levels` WRITE;
/*!40000 ALTER TABLE `institution_levels` DISABLE KEYS */;
INSERT  IGNORE INTO `institution_levels` VALUES (1,'school','group',NULL,'ابتدایی','ابتدایی',NULL,10,1,1),(2,'school','group',NULL,'متوسطه_اول','متوسطه اول',NULL,20,1,1),(3,'school','group',NULL,'متوسطه_دوم','متوسطه دوم',NULL,30,1,1),(4,'school','level',1,'اول','اول','ابتدایی',10,1,0),(5,'school','level',2,'دوم','دوم','ابتدایی',20,1,0),(6,'school','level',3,'سوم','سوم','ابتدایی',30,1,0),(7,'school','level',4,'چهارم','چهارم','ابتدایی',40,1,0),(8,'school','level',5,'پنجم','پنجم','ابتدایی',50,1,1),(9,'school','level',6,'ششم','ششم','ابتدایی',60,1,1),(10,'school','level',7,'هفتم','هفتم','متوسطه_اول',70,1,1),(11,'school','level',8,'هشتم','هشتم','متوسطه_اول',80,1,1),(12,'school','level',9,'نهم','نهم','متوسطه_اول',90,1,1),(13,'school','level',10,'دهم','دهم','متوسطه_دوم',100,1,1),(14,'school','level',11,'یازدهم','یازدهم','متوسطه_دوم',110,1,1),(15,'school','level',12,'دوازدهم','دوازدهم','متوسطه_دوم',120,1,1),(16,'school','field',NULL,'المپیاد','المپیاد','متوسطه_دوم',10,1,0),(17,'school','field',NULL,'ریاضی','ریاضی',NULL,20,1,0),(18,'school','field',NULL,'تجربی','تجربی',NULL,30,1,0),(19,'school','field',NULL,'انسانی','انسانی',NULL,40,1,0),(20,'school','field',NULL,'فنی','فنی',NULL,50,1,0),(21,'school','field',NULL,'کارودانش','کارودانش',NULL,60,1,0),(22,'institute','level',1,'مقدماتی','مقدماتی',NULL,10,1,1),(23,'institute','level',2,'متوسط','متوسط',NULL,20,1,1),(24,'institute','level',3,'پیشرفته','پیشرفته',NULL,30,1,1);
/*!40000 ALTER TABLE `institution_levels` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `type_vip` WRITE;
/*!40000 ALTER TABLE `type_vip` DISABLE KEYS */;
INSERT  IGNORE INTO `type_vip` VALUES (1,'کمیته'),(2,'عادی'),(3,'نیرو های مسلح'),(4,'عادی رایگان'),(5,'بهزیستی'),(6,'بسیج'),(7,'دهک اول'),(8,'دهک دوم '),(9,'دهک سوم'),(10,'دهک چهارم'),(11,'تیزهوشان'),(12,'نمونه دولتی'),(13,'سایر');
/*!40000 ALTER TABLE `type_vip` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `school_admin_roles` WRITE;
/*!40000 ALTER TABLE `school_admin_roles` DISABLE KEYS */;
INSERT  IGNORE INTO `school_admin_roles` VALUES (1,'manager','مدیر',1,1),(2,'grade_deputy','معاون پایه',3,1),(3,'disciplinary_deputy','معاون انضباطی',3,0),(4,'cultural_deputy','معاون فرهنگی/پرورشی',4,0),(5,'technical_deputy','معاون فنی',7,1),(6,'financial_deputy','معاون مالی',8,1),(7,'counselor','مشاور',6,1),(8,'teacher','معلم',9,1),(9,'general_deputy','معاون کل',2,1),(10,'executive_deputy','معاون اجرایی',4,1),(11,'cultural_disciplinary_deputy','معاون فرهنگی انضباطی',5,1);
/*!40000 ALTER TABLE `school_admin_roles` ENABLE KEYS */;
UNLOCK TABLES;

INSERT IGNORE INTO `hf_control_state` (`id`) VALUES (1);
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

