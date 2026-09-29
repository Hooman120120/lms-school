-- افزودن شناسه اتاق چهاردیواری (اسکای‌روم) به هر درس
ALTER TABLE dbdars
    ADD COLUMN IF NOT EXISTS link_class VARCHAR(255) NULL
    COMMENT 'شناسه اتاق چهاردیواری (اسکای‌روم) اختصاصی درس'
    AFTER codecourse;
