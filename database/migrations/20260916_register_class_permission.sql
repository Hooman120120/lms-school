ALTER TABLE `dbadmin`
    ADD COLUMN IF NOT EXISTS `register_class_permission` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `permission_news_notif`;
