-- InfinityFree ready schema + sample data
-- Note: On InfinityFree, select your database in phpMyAdmin, then import this file directly.
-- (The CREATE DATABASE and USE statements have been removed to prevent permission errors on shared hosting)

DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS contact_submissions;
DROP TABLE IF EXISTS user_profiles;
DROP TABLE IF EXISTS users;

-- 1. User accounts
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(30)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_created_at (created_at)
) ENGINE=InnoDB;

-- 2. Profile details (one-to-one with users)
CREATE TABLE user_profiles (
    user_id    INT UNSIGNED NOT NULL,
    full_name  VARCHAR(80)  NULL,
    phone      VARCHAR(20)  NULL,
    bio        TEXT         NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Contact Us submissions (linked to users via FK)
CREATE TABLE contact_submissions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    name       VARCHAR(80)  NOT NULL,
    email      VARCHAR(120) NOT NULL,
    subject    VARCHAR(80)  NOT NULL,
    message    TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sub_user (user_id),
    KEY idx_sub_email (email),
    KEY idx_sub_subject (subject),
    KEY idx_sub_created_at (created_at),
    CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Activity log (login/logout/CRUD events)
CREATE TABLE activity_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(120) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL DEFAULT 'unknown',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_user (user_id),
    KEY idx_log_created_at (created_at),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sample data. All sample accounts use the password:  Password123!
INSERT INTO users (username, email, password_hash, created_at) VALUES
('alice',  'alice@example.com',  '$2y$10$fTvWimEMH170Nnd8yidWbeHnxln.yZs/OC6X88w4yuKDe26LP3.Ne', NOW() - INTERVAL 10 DAY),
('brian',  'brian@example.com',  '$2y$10$fTvWimEMH170Nnd8yidWbeHnxln.yZs/OC6X88w4yuKDe26LP3.Ne', NOW() - INTERVAL 7 DAY),
('cynthia','cynthia@example.com','$2y$10$fTvWimEMH170Nnd8yidWbeHnxln.yZs/OC6X88w4yuKDe26LP3.Ne', NOW() - INTERVAL 3 DAY);

INSERT INTO user_profiles (user_id, full_name, phone, bio) VALUES
(1, 'Alice Wanjiru', '0700000001', 'Group leader and backend developer.'),
(2, 'Brian Otieno',  '0700000002', 'Database designer.'),
(3, 'Cynthia Achieng', NULL, 'Frontend and SEO.');

INSERT INTO contact_submissions (user_id, name, email, subject, message, created_at) VALUES
(1, 'Alice Wanjiru', 'alice@example.com', 'General Enquiry',   'Hello, how do I reset my profile details?', NOW() - INTERVAL 9 DAY),
(1, 'Alice Wanjiru', 'alice@example.com', 'Technical Support', 'The export button needs a quick check.',     NOW() - INTERVAL 8 DAY),
(1, 'Alice Wanjiru', 'alice@example.com', 'Feedback',          'Great layout, very clean navigation.',       NOW() - INTERVAL 6 DAY),
(2, 'Brian Otieno',  'brian@example.com', 'Bug Report',        'Pagination skips a page on the last row.',   NOW() - INTERVAL 5 DAY),
(2, 'Brian Otieno',  'brian@example.com', 'Feedback',          'Dark theme looks good.',                     NOW() - INTERVAL 4 DAY),
(2, 'Brian Otieno',  'brian@example.com', 'Partnership',       'We would like to collaborate on a project.', NOW() - INTERVAL 3 DAY),
(3, 'Cynthia Achieng','cynthia@example.com','General Enquiry', 'Where can I find the installation guide?',   NOW() - INTERVAL 2 DAY),
(3, 'Cynthia Achieng','cynthia@example.com','Technical Support','Cannot upload my CSV file.',               NOW() - INTERVAL 1 DAY);

INSERT INTO activity_log (user_id, action, ip_address) VALUES
(1, 'login', '127.0.0.1'),
(2, 'login', '127.0.0.1'),
(3, 'login', '127.0.0.1');
