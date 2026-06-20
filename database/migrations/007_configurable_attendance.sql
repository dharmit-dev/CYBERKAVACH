USE cyberkavach;

-- 1. Alter events table to support configurable late arrival and early exit thresholds
ALTER TABLE events 
    ADD COLUMN late_arrival_threshold_minutes INT UNSIGNED DEFAULT 15 AFTER capacity,
    ADD COLUMN early_exit_threshold_minutes INT UNSIGNED DEFAULT 15 AFTER late_arrival_threshold_minutes;

-- 2. Alter event_attendance table to store audit flags
ALTER TABLE event_attendance
    ADD COLUMN is_late TINYINT(1) UNSIGNED DEFAULT 0 AFTER attendance_type,
    ADD COLUMN is_early_exit TINYINT(1) UNSIGNED DEFAULT 0 AFTER is_late;
