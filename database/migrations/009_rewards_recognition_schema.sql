USE cyberkavach;

-- 1. Create member_points table
CREATE TABLE IF NOT EXISTS member_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL, -- 'attendance', 'manual_award', 'manual_deduction', 'redemption'
    awarded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_points_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_member_points_awarded_by
        FOREIGN KEY (awarded_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_member_points_user (user_id)
) ENGINE=InnoDB;

-- 2. Create badges table
CREATE TABLE IF NOT EXISTS badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    threshold_points INT NOT NULL,
    icon VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- 3. Create user_badges table
CREATE TABLE IF NOT EXISTS user_badges (
    user_id BIGINT UNSIGNED NOT NULL,
    badge_id INT UNSIGNED NOT NULL,
    unlocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_id),
    CONSTRAINT fk_user_badges_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_badges_badge
        FOREIGN KEY (badge_id) REFERENCES badges(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Create reward_items table
CREATE TABLE IF NOT EXISTS reward_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    points_cost INT NOT NULL,
    stock INT NOT NULL DEFAULT 999,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Seed default badges
INSERT IGNORE INTO badges (name, description, threshold_points, icon) VALUES
('Novice Participant', 'Awarded upon reaching 10 reward points for participating in club events.', 10, 'shield-halved'),
('Dedicated Challenger', 'Awarded upon reaching 50 reward points for continuous active participation.', 50, 'award'),
('Cyber Sentinel', 'Awarded upon reaching 100 reward points. A recognized guardian of club activities.', 100, 'shield-virus'),
('Elite CyberKavach', 'Awarded upon reaching 200 reward points. The highest echelon of club recognition.', 200, 'crown');

-- 6. Seed default reward items
INSERT IGNORE INTO reward_items (name, description, points_cost, stock) VALUES
('CyberKavach Sticker Pack', 'A pack of premium developer stickers to style your gear.', 30, 100),
('VIP Event Registration Voucher', 'Guarantees registration for any high-demand workshop or hackathon.', 50, 50),
('Custom Premium Certificate Theme', 'Unlock a special custom template style for your next certificate.', 60, 999),
('CyberKavach Exclusive Hoodie', 'Ultra-premium cotton hoodie with custom branding (limited stock).', 200, 10);

-- 7. Register permission for managing rewards
INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('rewards.manage', 'rewards', 'Manually award or deduct reward points for club members');

-- 8. Assign permission to coordinator roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'rewards.manage'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');
