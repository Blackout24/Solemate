-- ═══════════════════════════════════════════════════════
--  SoleMate Database Schema  ·  SQL Server (T-SQL)
--  Converted from MySQL / phpMyAdmin syntax
--  Run in SQL Server Management Studio (SSMS)
-- ═══════════════════════════════════════════════════════

-- ── Create database ─────────────────────────────────────
IF NOT EXISTS (
    SELECT name FROM sys.databases WHERE name = N'shoe_repair_db'
)
BEGIN
    CREATE DATABASE shoe_repair_db;
END;
GO

USE shoe_repair_db;
GO

-- ── Services table ──────────────────────────────────────
IF OBJECT_ID(N'dbo.services', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.services (
        id           INT             IDENTITY(1,1) PRIMARY KEY,
        service_key  NVARCHAR(50)    NOT NULL UNIQUE,
        service_name NVARCHAR(100)   NOT NULL,
        description  NVARCHAR(MAX)   NULL,
        price        DECIMAL(10, 2)  NOT NULL,
        icon         NVARCHAR(10)    DEFAULT N'🔧',
        price_from   BIT             DEFAULT 0,
        sort_order   INT             DEFAULT 0
    );
END;
GO

-- ── Orders table ────────────────────────────────────────
IF OBJECT_ID(N'dbo.orders', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.orders (
        id            INT             IDENTITY(1,1) PRIMARY KEY,
        order_ref     NVARCHAR(30)    NOT NULL UNIQUE,
        customer_name NVARCHAR(100)   NOT NULL,
        email         NVARCHAR(100)   NOT NULL,
        phone         NVARCHAR(30)    DEFAULT N'',
        service_id    INT             NULL,
        shoe_type     NVARCHAR(150)   DEFAULT N'',
        drop_date     DATE            NULL,
        collection    NVARCHAR(10)    DEFAULT N'drop-off'
                          CONSTRAINT CK_orders_collection
                          CHECK (collection IN (N'drop-off', N'courier', N'home')),
        notes         NVARCHAR(MAX)   NULL,
        status        NVARCHAR(20)    DEFAULT N'Received'
                          CONSTRAINT CK_orders_status
                          CHECK (status IN (
                              N'Pending', N'Received', N'Assessment',
                              N'In service', N'Quality check', N'Ready'
                          )),
        order_date    DATETIME        DEFAULT GETDATE(),
        CONSTRAINT FK_orders_services
            FOREIGN KEY (service_id) REFERENCES dbo.services (id)
    );
END;
GO

USE shoe_repair_db;
GO

DROP TABLE dbo.orders;
GO

USE shoe_repair_db;
GO

-- ── Reviews table ───────────────────────────────────────
IF OBJECT_ID(N'dbo.reviews', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.reviews (
        id           INT             IDENTITY(1,1) PRIMARY KEY,
        customer     NVARCHAR(100)   NOT NULL,
        initials     NVARCHAR(4)     NOT NULL,
        location     NVARCHAR(100)   DEFAULT N'',
        review_text  NVARCHAR(MAX)   NOT NULL,
        stars        TINYINT         DEFAULT 5,
        created_at   DATETIME        DEFAULT GETDATE()
    );
END;
GO

-- ── Admin users table ───────────────────────────────────
-- Passwords stored as bcrypt hashes.
-- Default credentials: admin / solemate2024
-- Change immediately in production.
-- Note: bcrypt hashing must be handled in application code (PHP/Node/etc.)
--       SQL Server has no native bcrypt function.
IF OBJECT_ID(N'dbo.admin_users', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.admin_users (
        id            INT             IDENTITY(1,1) PRIMARY KEY,
        username      NVARCHAR(50)    NOT NULL UNIQUE,
        password_hash NVARCHAR(255)   NOT NULL,
        display_name  NVARCHAR(100)   DEFAULT N'',
        created_at    DATETIME        DEFAULT GETDATE(),
        last_login    DATETIME        NULL
    );
END;
GO

-- ════════════════════════════════════════════════════════
--  Seed Data
-- ════════════════════════════════════════════════════════

-- ── Services ────────────────────────────────────────────
INSERT INTO dbo.services (service_key, service_name, description, price, icon, price_from, sort_order)
VALUES
    (N'deep-clean',          N'Deep Clean',          N'Full interior and exterior clean, deodorise, and condition. Restores freshness to any shoe.',         350.00, N'🧽', 0, 1),
    (N'sole-repair',         N'Sole Repair',         N'Resole, re-glue, or replace worn soles. Extends shoe life by years.',                                450.00, N'👟', 1, 2),
    (N'leather-restoration', N'Leather Restoration', N'Repair scuffs, restore colour, deep condition. Premium leather treatment.',                           600.00, N'✨', 1, 3),
    (N'sneaker-whitening',   N'Sneaker Whitening',   N'Restore white midsoles and uppers to factory-fresh brightness.',                                      280.00, N'⚪', 0, 4),
    (N'heel-replacement',    N'Heel Replacement',    N'Replace worn-down heels on pumps, boots, and dress shoes.',                                           380.00, N'👠', 0, 5),
    (N'full-restoration',    N'Full Restoration',    N'Complete overhaul: clean, repair, restore colour, replace sole. Like new.',                           950.00, N'🔧', 1, 6);
GO

-- ── Orders ──────────────────────────────────────────────
INSERT INTO dbo.orders (order_ref, customer_name, email, phone, service_id, shoe_type, drop_date, collection, status, order_date)
VALUES
    (N'SM-20240421', N'Siya K.',  N'siya@example.com',  N'0821234567', 1, N'Nike Air Max 90',   '2026-04-18', N'drop-off', N'In service',    '2026-04-18T09:00:00'),
    (N'SM-20240388', N'Marco G.', N'marco@example.com', N'0839876543', 6, N'Timberland Boots',  '2026-04-12', N'courier',  N'Ready',         '2026-04-12T10:30:00'),
    (N'SM-20240372', N'Nomsa M.', N'nomsa@example.com', N'0761112233', 3, N'Oxford Brogues',    '2026-04-10', N'drop-off', N'Assessment',    '2026-04-10T08:15:00'),
    (N'SM-20240301', N'Aryan P.', N'aryan@example.com', N'0844455667', 4, N'Adidas Stan Smith', '2026-04-05', N'home',     N'Quality check', '2026-04-05T14:00:00'),
    (N'SM-20240255', N'Thabo N.', N'thabo@example.com', N'0712223344', 5, N'Black Pumps',       '2026-04-20', N'drop-off', N'Received',      '2026-04-20T11:00:00');
GO

-- ── Reviews ─────────────────────────────────────────────
INSERT INTO dbo.reviews (customer, initials, location, review_text, stars)
VALUES
    (N'Siya Khoza',       N'SK', N'Berea, Durban',      N'My Air Force 1s look better than the day I bought them. Genuinely blown away by the results.',              5),
    (N'Nomsa Mthembu',    N'NM', N'Umhlanga, Durban',   N'Italian leather oxfords with deep scuffs — the restoration was absolutely remarkable.',                     5),
    (N'Aryan Patel',      N'AP', N'Westville, Durban',  N'Three pairs via courier. Smooth tracking, SMS updates, incredible results. Will use again.',                5),
    (N'Marco Govender',   N'MG', N'Chatsworth, Durban', N'Timberlands fully restored — new soles, cleaned, waterproofed. Looked brand new!',                         5),
    (N'Thandeka Nxumalo', N'TN', N'Pinetown, Durban',   N'Brought in a pair of heels I thought were ruined. They came back perfect. Incredible craftsmanship.',      5),
    (N'Rajan Sharma',     N'RS', N'Phoenix, Durban',    N'Quick turnaround, kept me updated via SMS. My suede loafers look immaculate. Highly recommend.',           5);
GO

-- ── Admin user ──────────────────────────────────────────
-- Default: username=admin, password=solemate2024
-- IMPORTANT: The hash below is a placeholder only.
-- bcrypt hashing is not native to SQL Server.
-- Generate the real hash in your application layer, e.g.:
--   PHP:   password_hash('solemate2024', PASSWORD_BCRYPT)
--   Node:  bcrypt.hashSync('solemate2024', 12)
-- Then UPDATE this row with the real hash before going live.
INSERT INTO dbo.admin_users (username, password_hash, display_name)
VALUES (N'admin', N'$2y$10$XOBEW2jPn1O9bIDl8O5Q..frHZaGoHVZvqN2ZJeF40ou6lVW2pIp2', N'Admin');
GO

USE shoe_repair_db;
GO
UPDATE dbo.admin_users
SET password_hash = '$2y$10$XOBEW2jPn1O9bIDl8O5Q..frHZaGoHVZvqN2ZJeF40ou6lVW2pIp2'
WHERE username = 'admin';

-- ════════════════════════════════════════════════════════
--  Migration: Add Pending to status CHECK constraint
--  Run this if your orders table already exists
-- ════════════════════════════════════════════════════════
IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = N'CK_orders_status'
)
BEGIN
    ALTER TABLE dbo.orders DROP CONSTRAINT CK_orders_status;
END;
GO

ALTER TABLE dbo.orders
    ADD CONSTRAINT CK_orders_status
    CHECK (status IN (
        N'Pending', N'Received', N'Assessment',
        N'In service', N'Quality check', N'Ready'
    ));
GO
-- ════════════════════════════════════════════════════════
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.orders')
      AND name = N'status'
)
BEGIN
    ALTER TABLE dbo.orders
        ADD status NVARCHAR(20) DEFAULT N'Received'
            CONSTRAINT CK_orders_status_migration
            CHECK (status IN (
                N'Received', N'Assessment',
                N'In service', N'Quality check', N'Ready'
            ));
END;
GO