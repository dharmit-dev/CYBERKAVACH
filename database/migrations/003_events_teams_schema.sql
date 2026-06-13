USE cyberkavach;

CREATE TABLE IF NOT EXISTS event_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(190) NOT NULL,
    registration_deadline DATETIME NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    poster_path VARCHAR(255) NULL,
    team_allowed TINYINT(1) NOT NULL DEFAULT 0,
    min_team_size INT UNSIGNED NULL,
    max_team_size INT UNSIGNED NULL,
    event_rules TEXT NULL,
    status ENUM('draft', 'pending_approval', 'under_review', 'approved', 'published', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_category
        FOREIGN KEY (category_id) REFERENCES event_categories(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_events_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_events_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_events_status_date (status, event_date),
    INDEX idx_events_category (category_id),
    INDEX idx_events_created_by (created_by)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_tag_mapping (
    event_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, tag_id),
    CONSTRAINT fk_event_tag_mapping_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_tag_mapping_tag
        FOREIGN KEY (tag_id) REFERENCES event_tags(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    team_name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_teams_owner
        FOREIGN KEY (owner_user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_saved_teams_owner (owner_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_team_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    saved_team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_team_members_team
        FOREIGN KEY (saved_team_id) REFERENCES saved_teams(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_saved_team_members_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_saved_team_member (saved_team_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    saved_team_id BIGINT UNSIGNED NULL,
    team_identifier VARCHAR(40) NOT NULL UNIQUE,
    team_name VARCHAR(150) NOT NULL,
    leader_user_id BIGINT UNSIGNED NOT NULL,
    qr_payload VARCHAR(255) NOT NULL UNIQUE,
    qr_path VARCHAR(255) NULL,
    status ENUM('registered', 'cancelled') NOT NULL DEFAULT 'registered',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_teams_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_teams_saved_team
        FOREIGN KEY (saved_team_id) REFERENCES saved_teams(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_event_teams_leader
        FOREIGN KEY (leader_user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    INDEX idx_event_teams_event (event_id),
    INDEX idx_event_teams_leader (leader_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    team_id BIGINT UNSIGNED NULL,
    registration_type ENUM('individual', 'team') NOT NULL,
    status ENUM('registered', 'cancelled') NOT NULL DEFAULT 'registered',
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_registrations_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_registrations_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_event_registrations_team
        FOREIGN KEY (team_id) REFERENCES event_teams(id)
        ON DELETE SET NULL,
    UNIQUE KEY uq_event_registration_user (event_id, user_id),
    INDEX idx_event_registrations_event_status (event_id, status),
    INDEX idx_event_registrations_team (team_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_team_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    is_leader TINYINT(1) NOT NULL DEFAULT 0,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_team_members_team
        FOREIGN KEY (team_id) REFERENCES event_teams(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_event_team_members_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    UNIQUE KEY uq_event_team_member (team_id, user_id),
    INDEX idx_event_team_members_user (user_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO event_categories (name, description) VALUES
('Workshop', 'Hands-on technical or awareness workshop'),
('Competition', 'Contest or challenge event'),
('Seminar', 'Talk, guest lecture, or seminar'),
('Webinar', 'Online learning session'),
('Club Activity', 'Internal club activity');

INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('events.manage', 'events', 'Create and manage events'),
('events.approve', 'events', 'Submit and review event approvals'),
('events.register', 'events', 'Register for published events'),
('events.export', 'events', 'Export event registrations');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('events.manage', 'events.approve', 'events.export')
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'events.register'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator', 'content_coordinator', 'social_media_coordinator', 'club_member', 'guest_participant');
