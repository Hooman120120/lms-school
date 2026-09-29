-- نوع مؤسسه: مدرسه یا آموزشگاه
-- school   = مدرسه (حالت پیش‌فرض و رفتار فعلی سامانه)
-- institute = آموزشگاه / دانشگاه
ALTER TABLE `setting_public`
    ADD COLUMN `institution_type` ENUM('school','institute') NOT NULL DEFAULT 'school'
    AFTER `id`;

-- اطمینان از اینکه نصب‌های موجود روی حالت مدرسه باقی بمانند
UPDATE `setting_public` SET `institution_type` = 'school' WHERE `institution_type` IS NULL OR `institution_type` = '';
