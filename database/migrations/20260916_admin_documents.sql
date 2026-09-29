ALTER TABLE `dbadmin`
    ADD COLUMN IF NOT EXISTS `document_file` VARCHAR(255) NULL AFTER `adminpic`,
    ADD COLUMN IF NOT EXISTS `resume_file` VARCHAR(255) NULL AFTER `document_file`,
    ADD COLUMN IF NOT EXISTS `identity_file` VARCHAR(255) NULL AFTER `resume_file`;
