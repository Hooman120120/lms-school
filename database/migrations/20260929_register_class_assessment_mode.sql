-- هر ستون نمره کلاسی فقط عددی یا فقط کیفی است.
ALTER TABLE register_class_events
    ADD COLUMN IF NOT EXISTS assessment_mode VARCHAR(20) NOT NULL DEFAULT 'score' AFTER max_score;

UPDATE register_class_events
SET assessment_mode = 'score'
WHERE assessment_mode IS NULL OR assessment_mode NOT IN ('score', 'quality');
