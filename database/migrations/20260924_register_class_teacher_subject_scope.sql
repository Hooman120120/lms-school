-- تفکیک دفتر کلاسی بر اساس معلم و درس
-- داده های قدیمی عمداً NULL می مانند تا بدون حدس زدن درس، قابل بازبینی باشند.
ALTER TABLE register_class_events
    ADD COLUMN IF NOT EXISTS dars_id INT(11) NULL AFTER teacher_id;

CREATE INDEX IF NOT EXISTS idx_rce_class_teacher_dars
    ON register_class_events (class_id, teacher_id, dars_id);

ALTER TABLE register_class_discipline
    ADD COLUMN IF NOT EXISTS dars_id INT(11) NULL AFTER class_id;

CREATE INDEX IF NOT EXISTS idx_rcd_class_creator_dars
    ON register_class_discipline (class_id, created_by, dars_id);
