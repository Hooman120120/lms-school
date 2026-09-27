-- جدول سطوح/پایه ها: جایگزین فهرست های سخت کدشدهٔ «پنجم تا دوازدهم» در نُه فایل.
-- سه نوع مقدار در ستون codtbl_course.paye برچسب می خورد و همه اینجا نگه داری می شوند:
--   level  = پایه/سطح فراگیر؛ مقدار عددی آن در dbpersonal.paye ذخیره می شود
--   group  = دستهٔ بالادستی برای تب های صفحهٔ انتخاب پایه (ابتدایی، متوسطه اول، ...)
--   field  = رشته یا برچسب کاتالوگی که روی فراگیر ذخیره نمی شود (ریاضی، المپیاد، ...)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `institution_levels` (
    `id`                    int(11)                               NOT NULL AUTO_INCREMENT,
    `institution_type`      enum('school','institute')            NOT NULL DEFAULT 'school',
    `kind`                  enum('level','group','field')         NOT NULL DEFAULT 'level',
    `value`                 int(11)                                   NULL DEFAULT NULL COMMENT 'مقدار ذخیره شده در dbpersonal.paye؛ فقط برای kind=level',
    `slug`                  varchar(64)                           NOT NULL COMMENT 'مقداری که در codtbl_course.paye برچسب می خورد',
    `label`                 varchar(64)                           NOT NULL COMMENT 'متن نمایشی',
    `group_slug`            varchar(64)                               NULL DEFAULT NULL COMMENT 'اسلاگ دستهٔ والد؛ فقط برای kind=level',
    `sort_order`            smallint(6)                           NOT NULL DEFAULT 0,
    `is_active`             tinyint(1)                            NOT NULL DEFAULT 1,
    `selectable_for_person` tinyint(1)                            NOT NULL DEFAULT 1 COMMENT 'در فرم ثبت نام فراگیر نشان داده شود؟',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_type_kind_slug` (`institution_type`, `kind`, `slug`),
    KEY `idx_lookup` (`institution_type`, `kind`, `is_active`, `sort_order`),
    KEY `idx_value` (`institution_type`, `value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------- دادهٔ اولیهٔ حالت مدرسه: دقیقاً همان چیزی که تا امروز سخت کد شده بود ----------
-- دسته ها
INSERT IGNORE INTO `institution_levels`
    (`institution_type`,`kind`,`value`,`slug`,`label`,`group_slug`,`sort_order`,`selectable_for_person`) VALUES
    ('school','group',NULL,'ابتدایی',     'ابتدایی',     NULL,10,1),
    ('school','group',NULL,'متوسطه_اول', 'متوسطه اول', NULL,20,1),
    ('school','group',NULL,'متوسطه_دوم', 'متوسطه دوم', NULL,30,1);

-- پایه ها. توجه: فرم ثبت نام فراگیر تا امروز فقط پنجم تا دوازدهم را نشان می داد،
-- ولی برچسب گذاری دوره ها اول تا چهارم را هم داشت؛ همان رفتار با selectable_for_person حفظ شده.
INSERT IGNORE INTO `institution_levels`
    (`institution_type`,`kind`,`value`,`slug`,`label`,`group_slug`,`sort_order`,`selectable_for_person`) VALUES
    ('school','level', 1,'اول',     'اول',     'ابتدایی',    10,0),
    ('school','level', 2,'دوم',     'دوم',     'ابتدایی',    20,0),
    ('school','level', 3,'سوم',     'سوم',     'ابتدایی',    30,0),
    ('school','level', 4,'چهارم',   'چهارم',   'ابتدایی',    40,0),
    ('school','level', 5,'پنجم',    'پنجم',    'ابتدایی',    50,1),
    ('school','level', 6,'ششم',     'ششم',     'ابتدایی',    60,1),
    ('school','level', 7,'هفتم',    'هفتم',    'متوسطه_اول', 70,1),
    ('school','level', 8,'هشتم',    'هشتم',    'متوسطه_اول', 80,1),
    ('school','level', 9,'نهم',     'نهم',     'متوسطه_اول', 90,1),
    ('school','level',10,'دهم',     'دهم',     'متوسطه_دوم',100,1),
    ('school','level',11,'یازدهم',  'یازدهم',  'متوسطه_دوم',110,1),
    ('school','level',12,'دوازدهم', 'دوازدهم', 'متوسطه_دوم',120,1);

-- رشته ها و برچسب های کاتالوگی (روی فراگیر ذخیره نمی شوند)
INSERT IGNORE INTO `institution_levels`
    (`institution_type`,`kind`,`value`,`slug`,`label`,`group_slug`,`sort_order`,`selectable_for_person`) VALUES
    ('school','field',NULL,'المپیاد',  'المپیاد',  'متوسطه_دوم',10,0),
    ('school','field',NULL,'ریاضی',    'ریاضی',    NULL,20,0),
    ('school','field',NULL,'تجربی',    'تجربی',    NULL,30,0),
    ('school','field',NULL,'انسانی',   'انسانی',   NULL,40,0),
    ('school','field',NULL,'فنی',      'فنی',      NULL,50,0),
    ('school','field',NULL,'کارودانش', 'کارودانش', NULL,60,0);

-- ---------- دادهٔ اولیهٔ حالت آموزشگاه: فقط یک نقطهٔ شروع، از پنل قابل ویرایش است ----------
INSERT IGNORE INTO `institution_levels`
    (`institution_type`,`kind`,`value`,`slug`,`label`,`group_slug`,`sort_order`,`selectable_for_person`) VALUES
    ('institute','level',1,'مقدماتی', 'مقدماتی', NULL,10,1),
    ('institute','level',2,'متوسط',   'متوسط',   NULL,20,1),
    ('institute','level',3,'پیشرفته', 'پیشرفته', NULL,30,1);
