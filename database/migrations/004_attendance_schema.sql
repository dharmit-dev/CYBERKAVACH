USE cyberkavach;

CREATE TABLE IF NOT EXISTS event_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    registration_id BIGINT UNSIGNED NULL,
    team_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    attendance_type ENUM('check_in', 'check_out') NOT NULL,
    scanned_by_user_id BIGINT UNSIGNED NULL,
    scanned_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_attendance_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_attendance_registration
        FOREIGN KEY (registration_id) REFERENCES event_registrations(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_event_attendance_team
        FOREIGN KEY (team_id) REFERENCES event_teams(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_event_attendance_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_attendance_scanner
        FOREIGN KEY (scanned_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_event_attendance_event_user (event_id, user_id),
    INDEX idx_event_attendance_event_team (event_id, team_id),
    INDEX idx_event_attendance_type (attendance_type),
    INDEX idx_event_attendance_scanned_at (scanned_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('events.attendance', 'events', 'Scan and manage event attendance');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'events.attendance'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');
