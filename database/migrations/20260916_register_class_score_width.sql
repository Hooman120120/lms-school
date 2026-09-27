-- ستون score فقط تا ۹۹.۹۹ جا داشت و مقدار تراز آزمون (هزارگان) بی صدا بریده می شد
ALTER TABLE register_class_evaluation
    MODIFY COLUMN `score` DECIMAL(8,2) NULL
    COMMENT 'نمره کلاسی (۰ تا ۲۰) یا تراز آزمون آنلاین';
