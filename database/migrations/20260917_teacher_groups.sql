-- گروه های آموزشی معلمان و استفاده از آن ها برای محدود کردن دید رویدادها

CREATE TABLE IF NOT EXISTS teacher_groups (
    id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_by INT(11) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_teacher_groups_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- عضویت معلم ها در گروه ها (هر معلم می تواند در چند گروه باشد)
CREATE TABLE IF NOT EXISTS teacher_group_members (
    id INT(11) NOT NULL AUTO_INCREMENT,
    group_id INT(11) NOT NULL,
    admin_id INT(11) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tgm (group_id, admin_id),
    KEY idx_tgm_admin (admin_id),
    CONSTRAINT fk_tgm_group FOREIGN KEY (group_id) REFERENCES teacher_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- گروه هایی که اجازه دیدن یک رویداد را دارند
CREATE TABLE IF NOT EXISTS calendar_event_teacher_groups (
    id INT(11) NOT NULL AUTO_INCREMENT,
    event_id INT(11) NOT NULL,
    group_id INT(11) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cetg (event_id, group_id),
    KEY idx_cetg_group (group_id),
    CONSTRAINT fk_cetg_group FOREIGN KEY (group_id) REFERENCES teacher_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
