-- ============================================
-- Ward Stock - Medical Inventory Database
-- Import this file in phpMyAdmin (or run via
-- the MySQL/MariaDB command line) to set up
-- everything you need.
-- ============================================

-- [SECTION: CREATE DATABASE]
CREATE DATABASE IF NOT EXISTS ward_stock;
USE ward_stock;

-- [SECTION: INVENTORY TABLE - holds all stock items]
CREATE TABLE IF NOT EXISTS inventory (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    item_name     VARCHAR(100) NOT NULL,
    sku           VARCHAR(50)  DEFAULT NULL,
    category      VARCHAR(50)  NOT NULL,
    quantity      INT          NOT NULL DEFAULT 0,
    unit          VARCHAR(20)  NOT NULL DEFAULT 'pcs',
    brand_name    VARCHAR(100) DEFAULT NULL,
    expiry_date   DATE         DEFAULT NULL,
    location      VARCHAR(50)  DEFAULT NULL,
    last_updated  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- [SECTION: ADMINS TABLE - holds admin login accounts only]
CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- [SECTION: STAFF TABLE - holds staff login accounts only]
-- Kept as a completely separate table from admins (not just a role
-- column) so admin and staff accounts can never mix up with each other.
CREATE TABLE IF NOT EXISTS staff (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- [SECTION: SAMPLE ACCOUNTS - so you can log in right away]
-- Passwords are stored as plain text (not hashed) for local testing.
-- Change these, or create your own via Create Account, before real use.
INSERT INTO admins (username, password_hash, is_active) VALUES
('admin', 'admin123', 1);

INSERT INTO staff (username, password_hash, is_active) VALUES
('staff1', 'staff123', 1);

-- [SECTION: STOCK ADJUSTMENTS TABLE - audit trail of every +/- action]
-- item_name/category/username are stored directly (not just IDs) so the
-- history stays readable even if the item or account is later deleted.
-- adjusted_by_role records WHICH table adjusted_by_id points into,
-- since an admin and a staff account can now share the same id number.
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    item_id               INT DEFAULT NULL,
    item_name             VARCHAR(100) NOT NULL,
    category              VARCHAR(50) NOT NULL,
    action                VARCHAR(10) NOT NULL,
    quantity_before       INT NOT NULL,
    quantity_after        INT NOT NULL,
    adjusted_by_id        INT DEFAULT NULL,
    adjusted_by_role      VARCHAR(10) NOT NULL DEFAULT 'staff',
    adjusted_by_username  VARCHAR(50) NOT NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- [SECTION: ITEM ACTIONS LOG TABLE - audit trail of admin create/edit/delete]
CREATE TABLE IF NOT EXISTS item_actions_log (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    item_id               INT DEFAULT NULL,
    item_name             VARCHAR(100) NOT NULL,
    category              VARCHAR(50) NOT NULL,
    action                VARCHAR(10) NOT NULL,
    detail                TEXT         DEFAULT NULL,
    performed_by_id       INT DEFAULT NULL,
    performed_by_role     VARCHAR(10) NOT NULL DEFAULT 'staff',
    performed_by_username VARCHAR(50) NOT NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- [SECTION: ACCOUNT ACTIVITY LOG TABLE - who changed which account, and how]
-- Covers account create / password reset / deactivate / reactivate / delete.
CREATE TABLE IF NOT EXISTS account_activity_log (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    target_username       VARCHAR(50) NOT NULL,
    target_role           VARCHAR(10) NOT NULL,
    action                VARCHAR(20) NOT NULL,
    performed_by_id       INT DEFAULT NULL,
    performed_by_role     VARCHAR(10) NOT NULL DEFAULT 'admin',
    performed_by_username VARCHAR(50) NOT NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- [SECTION: LOGIN LOG TABLE - every login attempt, successful or not]
CREATE TABLE IF NOT EXISTS login_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL,
    role          VARCHAR(10) DEFAULT NULL,
    success       TINYINT(1) NOT NULL,
    attempted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- [SECTION: SAMPLE DATA - so you have something to see right away]
INSERT INTO inventory (item_name, sku, category, quantity, unit, brand_name, expiry_date, location) VALUES
('Paracetamol 500mg', NULL, 'Medicine', 250, 'tablets', 'MedSupply Co.', '2027-03-15', 'Shelf A1'),
('Surgical Gloves (M)', NULL, 'Consumable', 500, 'pairs', 'SafeHands Inc.', '2028-01-01', 'Shelf B2'),
('Amoxicillin 250mg', NULL, 'Medicine', 40, 'capsules', 'MedSupply Co.', '2026-11-20', 'Shelf A2'),
('Digital Thermometer', 'EQP-1001', 'Equipment', 12, 'units', 'HealthTech', NULL, 'Cabinet C1'),
('IV Cannula 18G', NULL, 'Consumable', 80, 'pcs', 'SafeHands Inc.', '2027-08-10', 'Shelf B1'),
('Face Mask (Surgical)', NULL, 'Consumable', 15, 'boxes', 'SafeHands Inc.', '2027-05-01', 'Shelf B3'),
('Insulin Syringe', NULL, 'Consumable', 200, 'pcs', 'MedSupply Co.', '2027-02-14', 'Shelf B4'),
('Blood Pressure Monitor', 'EQP-1002', 'Equipment', 6, 'units', 'HealthTech', NULL, 'Cabinet C2'),
('Hospital Bedsheet', NULL, 'Linen', 60, 'pcs', 'ComfortWeave', NULL, 'Shelf D1'),
('Patient Gown', NULL, 'Linen', 45, 'pcs', 'ComfortWeave', NULL, 'Shelf D2'),
('Pillow Case', NULL, 'Linen', 70, 'pcs', 'ComfortWeave', NULL, 'Shelf D3'),
('CPR Training Manikin', 'TRN-2001', 'Training Model', 4, 'units', 'MedEd Supplies', NULL, 'Training Room 1');
