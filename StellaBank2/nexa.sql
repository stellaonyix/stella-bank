-- ============================================================
--  Stella Bank — Full Schema
--  Run this ONCE in phpMyAdmin (drop old tables if needed)
-- ============================================================

CREATE DATABASE IF NOT EXISTS nexabank;
USE nexabank;

-- ── Users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100)   NOT NULL,
    email          VARCHAR(255)   NOT NULL UNIQUE,
    password       VARCHAR(255)   NOT NULL,
    account_number CHAR(10)       NOT NULL UNIQUE,
    balance        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    created_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

-- ── Transactions ───────────────────────────────────────────────
--   from_account / to_account are filled for transfers;
--   NULL for bill payments and funding.
CREATE TABLE IF NOT EXISTS transactions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT             NOT NULL,
    type            ENUM('credit','debit') NOT NULL,
    amount          DECIMAL(12,2)   NOT NULL,
    description     VARCHAR(255)    DEFAULT '',
    reference       VARCHAR(100)    DEFAULT '',
    from_account    CHAR(10)        DEFAULT NULL,
    to_account      CHAR(10)        DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Password resets ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Admins ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(255)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ── Default super-admin account ────────────────────────────────
-- Email:    admin@stellabank.com
-- Password: Admin@1234
INSERT IGNORE INTO admins (name, email, password, role) VALUES (
    'Super Admin',
    'admin@stellabank.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'superadmin'
);
-- IMPORTANT: The hash above is a placeholder. After importing, run this PHP once:
--   echo password_hash('Admin@1234', PASSWORD_DEFAULT);
-- Then update the row:
--   UPDATE admins SET password='<new_hash>' WHERE email='admin@stellabank.com';
