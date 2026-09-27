-- گسترش انواع ستون های دفتر کلاسی
-- 0: حضور و غیاب دستی | 1: نمره کلاسی | 2: حضور و غیاب آنلاین | 3: توضیحات | 4: تراز آزمون آنلاین
ALTER TABLE register_class_events
    MODIFY COLUMN `type` ENUM('0','1','2','3','4') NOT NULL
    COMMENT '0=حضور دستی، 1=نمره کلاسی، 2=حضور آنلاین، 3=توضیحات، 4=تراز آزمون آنلاین';

-- شناسه گروه؛ ۵ ستونی که با یک بار کلیک روی + ساخته می شوند یک مقدار مشترک می گیرند
ALTER TABLE register_class_events
    ADD COLUMN IF NOT EXISTS `group_uid` CHAR(32) NULL
    COMMENT 'شناسه دسته ستون های ایجاد شده در یک نوبت'
    AFTER `event_date`;

CREATE INDEX IF NOT EXISTS idx_rce_group_uid ON register_class_events (group_uid);
