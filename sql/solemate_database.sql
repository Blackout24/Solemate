-- ═══════════════════════════════════════════════════════
--  SoleMate Database Schema
--  Run this in phpMyAdmin or MySQL CLI before launching
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS shoe_repair_db;
USE shoe_repair_db;

-- ── Services table ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS services (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    service_key  VARCHAR(50)     NOT NULL UNIQUE,
    service_name VARCHAR(100)    NOT NULL,
    description  TEXT,
    price        DECIMAL(10, 2)  NOT NULL,
    icon         VARCHAR(10)     DEFAULT '🔧',
    price_from   TINYINT(1)      DEFAULT 0,
    sort_order   INT             DEFAULT 0
);

-- ── Orders table ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    order_ref     VARCHAR(30)     NOT NULL UNIQUE,
    customer_name VARCHAR(100)    NOT NULL,
    email         VARCHAR(100)    NOT NULL,
    phone         VARCHAR(30)     DEFAULT '',
    service_id    INT,
    shoe_type     VARCHAR(150)    DEFAULT '',
    drop_date     DATE,
    collection    ENUM('drop-off','courier','home') DEFAULT 'drop-off',
    notes         TEXT,
    status        ENUM('Pending','Received','Assessment','In service','Quality check','Ready') DEFAULT 'Received',
    order_date    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- ── Reviews table ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    customer     VARCHAR(100)  NOT NULL,
    initials     VARCHAR(4)    NOT NULL,
    location     VARCHAR(100)  DEFAULT '',
    review_text  TEXT          NOT NULL,
    stars        TINYINT       DEFAULT 5,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ── Admin users table ───────────────────────────────────
-- Passwords stored as bcrypt hashes.
-- Default credentials: admin / solemate2024
-- Change immediately in production using: password_hash('yourpassword', PASSWORD_BCRYPT)
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    display_name  VARCHAR(100)  DEFAULT '',
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    last_login    TIMESTAMP     NULL
);

-- ════════════════════════════════════════════════════════
--  Seed Data
-- ════════════════════════════════════════════════════════

INSERT INTO services (service_key, service_name, description, price, icon, price_from, sort_order) VALUES
('deep-clean',          'Deep Clean',          'Full interior and exterior clean, deodorise, and condition. Restores freshness to any shoe.',          350.00, '🧽', 0, 1),
('sole-repair',         'Sole Repair',         'Resole, re-glue, or replace worn soles. Extends shoe life by years.',                                  450.00, '👟', 1, 2),
('leather-restoration', 'Leather Restoration', 'Repair scuffs, restore colour, deep condition. Premium leather treatment.',                             600.00, '✨', 1, 3),
('sneaker-whitening',   'Sneaker Whitening',   'Restore white midsoles and uppers to factory-fresh brightness.',                                        280.00, '⚪', 0, 4),
('heel-replacement',    'Heel Replacement',    'Replace worn-down heels on pumps, boots, and dress shoes.',                                             380.00, '👠', 0, 5),
('full-restoration',    'Full Restoration',    'Complete overhaul: clean, repair, restore colour, replace sole. Like new.',                             950.00, '🔧', 1, 6);

INSERT INTO orders (order_ref, customer_name, email, phone, service_id, shoe_type, drop_date, collection, status, order_date) VALUES
('SM-20240421', 'Siya K.',   'siya@example.com',  '0821234567', 1, 'Nike Air Max 90',   '2026-04-18', 'drop-off', 'In service',    '2026-04-18 09:00:00'),
('SM-20240388', 'Marco G.',  'marco@example.com', '0839876543', 6, 'Timberland Boots',  '2026-04-12', 'courier',  'Ready',         '2026-04-12 10:30:00'),
('SM-20240372', 'Nomsa M.',  'nomsa@example.com', '0761112233', 3, 'Oxford Brogues',    '2026-04-10', 'drop-off', 'Assessment',    '2026-04-10 08:15:00'),
('SM-20240301', 'Aryan P.',  'aryan@example.com', '0844455667', 4, 'Adidas Stan Smith', '2026-04-05', 'home',     'Quality check', '2026-04-05 14:00:00'),
('SM-20240255', 'Thabo N.',  'thabo@example.com', '0712223344', 5, 'Black Pumps',       '2026-04-20', 'drop-off', 'Received',      '2026-04-20 11:00:00');

INSERT INTO reviews (customer, initials, location, review_text, stars) VALUES
('Siya Khoza',       'SK', 'Berea, Durban',      'My Air Force 1s look better than the day I bought them. Genuinely blown away by the results.',                5),
('Nomsa Mthembu',    'NM', 'Umhlanga, Durban',   'Italian leather oxfords with deep scuffs — the restoration was absolutely remarkable.',                       5),
('Aryan Patel',      'AP', 'Westville, Durban',  'Three pairs via courier. Smooth tracking, SMS updates, incredible results. Will use again.',                  5),
('Marco Govender',   'MG', 'Chatsworth, Durban', 'Timberlands fully restored — new soles, cleaned, waterproofed. Looked brand new!',                           5),
('Thandeka Nxumalo', 'TN', 'Pinetown, Durban',   'Brought in a pair of heels I thought were ruined. They came back perfect. Incredible craftsmanship.',         5),
('Rajan Sharma',     'RS', 'Phoenix, Durban',    'Quick turnaround, kept me updated via SMS. My suede loafers look immaculate. Highly recommend.',              5);

-- Default admin: username=admin, password=solemate2024
-- Generated with: password_hash('solemate2024', PASSWORD_BCRYPT)
INSERT INTO admin_users (username, password_hash, display_name) VALUES
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');
-- NOTE: The hash above is a placeholder. Run this in PHP to get a real hash:
-- echo password_hash('solemate2024', PASSWORD_BCRYPT);
-- Then replace the hash above with the output.

-- ════════════════════════════════════════════════════════
--  Migration: Add status column if it doesn't exist
--  Run this if your orders table is missing the status column
-- ════════════════════════════════════════════════════════