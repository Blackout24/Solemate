-- ═══════════════════════════════════════════════════════
--  SoleMate Database Schema · MySQL (Railway)
-- ═══════════════════════════════════════════════════════

-- ── Services table ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS services (
    id           INT             AUTO_INCREMENT PRIMARY KEY,
    service_key  VARCHAR(50)     NOT NULL UNIQUE,
    service_name VARCHAR(100)    NOT NULL,
    description  TEXT            NULL,
    price        DECIMAL(10, 2)  NOT NULL,
    icon         VARCHAR(10)     DEFAULT '🔧',
    price_from   TINYINT(1)      DEFAULT 0,
    sort_order   INT             DEFAULT 0
);

-- ── Orders table ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id            INT             AUTO_INCREMENT PRIMARY KEY,
    order_ref     VARCHAR(30)     NOT NULL UNIQUE,
    customer_name VARCHAR(100)    NOT NULL,
    email         VARCHAR(100)    NOT NULL,
    phone         VARCHAR(30)     DEFAULT '',
    service_id    INT             NULL,
    shoe_type     VARCHAR(150)    DEFAULT '',
    drop_date     DATE            NULL,
    collection    VARCHAR(10)     DEFAULT 'drop-off',
    notes         TEXT            NULL,
    status        VARCHAR(20)     DEFAULT 'Received',
    order_date    DATETIME        DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_orders_services
        FOREIGN KEY (service_id) REFERENCES services (id),
    CONSTRAINT CK_orders_collection
        CHECK (collection IN ('drop-off', 'courier', 'home')),
    CONSTRAINT CK_orders_status
        CHECK (status IN ('Pending', 'Received', 'Assessment', 'In service', 'Quality check', 'Ready'))
);

-- ── Reviews table ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
    id           INT             AUTO_INCREMENT PRIMARY KEY,
    customer     VARCHAR(100)    NOT NULL,
    initials     VARCHAR(4)      NOT NULL,
    location     VARCHAR(100)    DEFAULT '',
    review_text  TEXT            NOT NULL,
    stars        TINYINT         DEFAULT 5,
    created_at   DATETIME        DEFAULT CURRENT_TIMESTAMP
);

-- ── Admin users table ───────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT             AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    password_hash VARCHAR(255)    NOT NULL,
    display_name  VARCHAR(100)    DEFAULT '',
    created_at    DATETIME        DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME        NULL
);

-- ════════════════════════════════════════════════════════
--  Seed Data
-- ════════════════════════════════════════════════════════

-- ── Services ────────────────────────────────────────────
INSERT INTO services (service_key, service_name, description, price, icon, price_from, sort_order) VALUES
    ('deep-clean',          'Deep Clean',          'Full interior and exterior clean, deodorise, and condition. Restores freshness to any shoe.',         350.00, '🧽', 0, 1),
    ('sole-repair',         'Sole Repair',         'Resole, re-glue, or replace worn soles. Extends shoe life by years.',                                450.00, '👟', 1, 2),
    ('leather-restoration', 'Leather Restoration', 'Repair scuffs, restore colour, deep condition. Premium leather treatment.',                           600.00, '✨', 1, 3),
    ('sneaker-whitening',   'Sneaker Whitening',   'Restore white midsoles and uppers to factory-fresh brightness.',                                      280.00, '⚪', 0, 4),
    ('heel-replacement',    'Heel Replacement',    'Replace worn-down heels on pumps, boots, and dress shoes.',                                           380.00, '👠', 0, 5),
    ('full-restoration',    'Full Restoration',    'Complete overhaul: clean, repair, restore colour, replace sole. Like new.',                           950.00, '🔧', 1, 6);

-- ── Orders ──────────────────────────────────────────────
INSERT INTO orders (order_ref, customer_name, email, phone, service_id, shoe_type, drop_date, collection, status, order_date) VALUES
    ('SM-20240421', 'Siya K.',  'siya@example.com',  '0821234567', 1, 'Nike Air Max 90',   '2026-04-18', 'drop-off', 'In service',    '2026-04-18 09:00:00'),
    ('SM-20240388', 'Marco G.', 'marco@example.com', '0839876543', 6, 'Timberland Boots',  '2026-04-12', 'courier',  'Ready',         '2026-04-12 10:30:00'),
    ('SM-20240372', 'Nomsa M.', 'nomsa@example.com', '0761112233', 3, 'Oxford Brogues',    '2026-04-10', 'drop-off', 'Assessment',    '2026-04-10 08:15:00'),
    ('SM-20240301', 'Aryan P.', 'aryan@example.com', '0844455667', 4, 'Adidas Stan Smith', '2026-04-05', 'home',     'Quality check', '2026-04-05 14:00:00'),
    ('SM-20240255', 'Thabo N.', 'thabo@example.com', '0712223344', 5, 'Black Pumps',       '2026-04-20', 'drop-off', 'Received',      '2026-04-20 11:00:00');

-- ── Reviews ─────────────────────────────────────────────
INSERT INTO reviews (customer, initials, location, review_text, stars) VALUES
    ('Siya Khoza',       'SK', 'Berea, Durban',      'My Air Force 1s look better than the day I bought them. Genuinely blown away by the results.',              5),
    ('Nomsa Mthembu',    'NM', 'Umhlanga, Durban',   'Italian leather oxfords with deep scuffs — the restoration was absolutely remarkable.',                     5),
    ('Aryan Patel',      'AP', 'Westville, Durban',  'Three pairs via courier. Smooth tracking, SMS updates, incredible results. Will use again.',                5),
    ('Marco Govender',   'MG', 'Chatsworth, Durban', 'Timberlands fully restored — new soles, cleaned, waterproofed. Looked brand new!',                         5),
    ('Thandeka Nxumalo', 'TN', 'Pinetown, Durban',   'Brought in a pair of heels I thought were ruined. They came back perfect. Incredible craftsmanship.',      5),
    ('Rajan Sharma',     'RS', 'Phoenix, Durban',    'Quick turnaround, kept me updated via SMS. My suede loafers look immaculate. Highly recommend.',           5);

-- ── Admin user ──────────────────────────────────────────
INSERT INTO admin_users (username, password_hash, display_name) VALUES
    ('admin', '$2y$10$XOBEW2jPn1O9bIDl8O5Q..frHZaGoHVZvqN2ZJeF40ou6lVW2pIp2', 'Admin');
