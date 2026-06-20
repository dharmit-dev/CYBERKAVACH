USE cyberkavach;

-- 1. Create certificate_templates table
CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    text_settings TEXT NOT NULL, -- JSON coordinates: name_x, name_y, event_x, event_y, etc.
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Create certificates table
CREATE TABLE IF NOT EXISTS certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_code VARCHAR(50) NOT NULL UNIQUE,
    template_id INT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    recipient_name VARCHAR(150) NOT NULL,
    recipient_email VARCHAR(150) NOT NULL,
    cryptographic_signature VARCHAR(64) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_certificates_template
        FOREIGN KEY (template_id) REFERENCES certificate_templates(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_certificates_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_certificates_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_certificates_code (certificate_code)
) ENGINE=InnoDB;

-- 3. Register permissions for certificate management
INSERT IGNORE INTO permissions (permission_key, module, description) VALUES
('certificates.manage', 'certificates', 'Upload templates and generate bulk certificates');

-- 4. Assign permissions to coordinators
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key = 'certificates.manage'
WHERE r.role_key IN ('faculty_coordinator', 'student_coordinator', 'tech_coordinator');
