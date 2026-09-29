-- سقف قابل تنظیم برای ستون‌های نمره کلاسی.
ALTER TABLE register_class_events
    ADD COLUMN IF NOT EXISTS max_score DECIMAL(8,2) NOT NULL DEFAULT 20.00 AFTER type;

UPDATE register_class_events
SET max_score = 20.00
WHERE max_score IS NULL OR max_score <= 0;
