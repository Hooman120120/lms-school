-- تاریخ ستون دیگر الزامی نیست؛ ستون بدون تاریخ هم می توان ساخت
ALTER TABLE register_class_events
    MODIFY COLUMN `event_date` DATE NULL
    COMMENT 'تاریخ ستون؛ اختیاری است و در صورت خالی بودن نمایش داده نمی شود';
