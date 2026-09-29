-- Bind each online attendance column to one actual Chahard session.
ALTER TABLE register_class_events
    ADD COLUMN IF NOT EXISTS room_session_id CHAR(24) NULL AFTER event_date;

CREATE UNIQUE INDEX IF NOT EXISTS uq_register_class_room_session
    ON register_class_events (room_session_id);
