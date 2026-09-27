-- متن ستون توضیحات دفتر کلاسی (به ازای هر دانش آموز در هر ستون)
ALTER TABLE register_class_evaluation
    ADD COLUMN IF NOT EXISTS `note` VARCHAR(500) NULL
    COMMENT 'متن ستون توضیحات'
    AFTER `score`;
