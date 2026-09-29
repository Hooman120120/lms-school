-- مقصد نمایش هر نمرهٔ ثبت‌شده در دفتر کلاسی.
-- رکوردهای قبلی برای جلوگیری از مخفی‌شدن ناگهانی، برای هر دو مخاطب قابل نمایش هستند.
ALTER TABLE register_class_evaluation
    ADD COLUMN IF NOT EXISTS visibility_target VARCHAR(20) NOT NULL DEFAULT 'both' AFTER quality;

UPDATE register_class_evaluation
SET visibility_target = 'both'
WHERE visibility_target IS NULL
   OR visibility_target NOT IN ('student', 'family', 'both');

