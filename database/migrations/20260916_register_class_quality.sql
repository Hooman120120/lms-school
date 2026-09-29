-- ارزیابی کیفی ستون نمره کلاسی
-- 1=خیلی خوب، 2=خوب، 3=قابل قبول، 4=نیاز به تلاش بیشتر
ALTER TABLE register_class_evaluation
    ADD COLUMN IF NOT EXISTS `quality` TINYINT UNSIGNED NULL
    COMMENT '1=خیلی خوب، 2=خوب، 3=قابل قبول، 4=نیاز به تلاش بیشتر'
    AFTER `note`;
