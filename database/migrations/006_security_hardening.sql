USE cyberkavach;

-- 1. Alter otp_codes table to support failed attempts tracking and client network context
ALTER TABLE otp_codes 
    ADD COLUMN failed_attempts INT UNSIGNED DEFAULT 0 AFTER is_used,
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER failed_attempts,
    ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address;

-- 2. Alter password_resets table to support client network context
ALTER TABLE password_resets 
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER is_used,
    ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address;
