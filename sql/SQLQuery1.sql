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
IF OBJECT_ID(N'dbo.booking', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.booking (
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
                              N'In service', N'Quality check', N'Ready',
                              N'Design confirmed', N'Pattern cut', N'Stitching',
                              N'Sole bonding', N'Finishing', N'Dispatched'
                          )),
        order_date    DATETIME        DEFAULT GETDATE(),
        CONSTRAINT FK_orders_services
            FOREIGN KEY (service_id) REFERENCES dbo.services (id)
    );
END;
GO

 --USE shoe_repair_db;
 --GO

 --DROP TABLE dbo.booking;
 --GO

 --USE shoe_repair_db;
 --GO

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

-- ═══════════════════════════════════════════════════════
--  SoleMate — Customer Accounts Migration  ·  T-SQL
--  Run AFTER SQLQuery1.sql in SSMS.
--  Safe to re-run (all blocks are guarded with IF NOT EXISTS).
-- ═══════════════════════════════════════════════════════

USE shoe_repair_db;
GO

-- ── customers ────────────────────────────────────────────
IF OBJECT_ID(N'dbo.customers', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.customers (
        id            INT             IDENTITY(1,1) PRIMARY KEY,
        first_name    NVARCHAR(60)    NOT NULL,
        last_name     NVARCHAR(60)    NOT NULL,
        email         NVARCHAR(100)   NOT NULL,
        phone         NVARCHAR(30)    DEFAULT N'',
        password_hash NVARCHAR(255)   NOT NULL,
        is_verified   BIT             DEFAULT 1,      -- set to 0 if you add email verification
        verify_token  NVARCHAR(64)    NULL,
        created_at    DATETIME        DEFAULT GETDATE(),
        last_login    DATETIME        NULL,
        CONSTRAINT UQ_customers_email UNIQUE (email)
    );
END;
GO

-- ── customer_addresses ───────────────────────────────────
IF OBJECT_ID(N'dbo.customer_addresses', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.customer_addresses (
        id            INT             IDENTITY(1,1) PRIMARY KEY,
        customer_id   INT             NOT NULL,
        label         NVARCHAR(50)    DEFAULT N'Home',
        street        NVARCHAR(150)   NOT NULL,
        suburb        NVARCHAR(100)   DEFAULT N'',
        city          NVARCHAR(100)   DEFAULT N'Durban',
        province      NVARCHAR(60)    DEFAULT N'KwaZulu-Natal',
        postal_code   NVARCHAR(10)    DEFAULT N'',
        is_default    BIT             DEFAULT 0,
        created_at    DATETIME        DEFAULT GETDATE(),
        CONSTRAINT FK_addresses_customer
            FOREIGN KEY (customer_id) REFERENCES dbo.customers (id) ON DELETE CASCADE
    );
END;
GO

-- ── password_resets ──────────────────────────────────────
IF OBJECT_ID(N'dbo.password_resets', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.password_resets (
        id          INT             IDENTITY(1,1) PRIMARY KEY,
        email       NVARCHAR(100)   NOT NULL,
        token       NVARCHAR(64)    NOT NULL,
        expires_at  DATETIME        NOT NULL,
        used        BIT             DEFAULT 0,
        created_at  DATETIME        DEFAULT GETDATE(),
        CONSTRAINT UQ_password_resets_token UNIQUE (token)
    );
END;
GO

-- ── Add customer_id FK to booking (links guest orders to accounts) ──
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.booking') AND name = N'customer_id'
)
BEGIN
    ALTER TABLE dbo.booking
        ADD customer_id INT NULL
            CONSTRAINT FK_booking_customer
            FOREIGN KEY REFERENCES dbo.customers (id) ON DELETE SET NULL;
END;
GO

-- ════════════════════════════════════════════════════════
--  Useful maintenance query: expire old reset tokens
--  Schedule this daily or run manually.
-- ════════════════════════════════════════════════════════
-- DELETE FROM dbo.password_resets WHERE expires_at < GETDATE();
-- GO


-- ═══════════════════════════════════════════════════════
--  SoleMate — Inventory Migration  ·  T-SQL
--  Run AFTER SQLQuery1.sql in SSMS.
--  Safe to re-run — guarded with IF NOT EXISTS.
-- ═══════════════════════════════════════════════════════

USE shoe_repair_db;
GO

-- ── inventory ────────────────────────────────────────────
IF OBJECT_ID(N'dbo.inventory', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.inventory (
        id             INT              IDENTITY(1,1) PRIMARY KEY,
        item_name      NVARCHAR(100)    NOT NULL,
        category       NVARCHAR(60)     DEFAULT N'General',
        quantity       DECIMAL(10,2)    DEFAULT 0,
        unit           NVARCHAR(20)     DEFAULT N'unit',
        reorder_level  DECIMAL(10,2)    DEFAULT 5,
        cost_per_unit  DECIMAL(10,2)    DEFAULT 0,
        supplier       NVARCHAR(100)    DEFAULT N'',
        notes          NVARCHAR(MAX)    NULL,
        updated_at     DATETIME         DEFAULT GETDATE()
    );
END;
GO

-- ═══════════════════════════════════════════════════════
--  SoleMate — Customer Features Migration  ·  T-SQL
--  Run AFTER solemate_customers_migration.sql in SSMS.
-- ═══════════════════════════════════════════════════════

USE shoe_repair_db;
GO

-- ── customer_saved_designs ───────────────────────────────
-- Stores named custom shoe designs (colours, size, etc.)
IF OBJECT_ID(N'dbo.customer_saved_designs', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.customer_saved_designs (
        id             INT              IDENTITY(1,1) PRIMARY KEY,
        customer_id    INT              NOT NULL,
        design_name    NVARCHAR(100)    DEFAULT N'My Design',
        customization  NVARCHAR(MAX)    NOT NULL,   -- JSON: { colors:{}, size:'', environment:'' }
        notes          NVARCHAR(MAX)    NULL,
        created_at     DATETIME         DEFAULT GETDATE(),
        updated_at     DATETIME         DEFAULT GETDATE(),
        CONSTRAINT FK_designs_customer
            FOREIGN KEY (customer_id) REFERENCES dbo.customers (id) ON DELETE CASCADE
    );
END;
GO

-- ── customer_wishlist ────────────────────────────────────
-- One row per (customer, service) pair — soft favourites.
IF OBJECT_ID(N'dbo.customer_wishlist', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.customer_wishlist (
        id          INT      IDENTITY(1,1) PRIMARY KEY,
        customer_id INT      NOT NULL,
        service_id  INT      NOT NULL,
        added_at    DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_wishlist_customer
            FOREIGN KEY (customer_id) REFERENCES dbo.customers (id) ON DELETE CASCADE,
        CONSTRAINT UQ_wishlist_customer_service
            UNIQUE (customer_id, service_id)
    );
END;
GO

-- ═══════════════════════════════════════════════════════
--  SoleMate — Auto-Inventory Migration  ·  T-SQL
--  Run AFTER solemate_inventory_migration.sql in SSMS.
--
--  Adds:
--    dbo.service_inventory_usage  — what each service consumes
--    dbo.booking.inventory_deducted — prevents double-deduction
-- ═══════════════════════════════════════════════════════

USE shoe_repair_db;
GO

-- ── service_inventory_usage ──────────────────────────────
IF OBJECT_ID(N'dbo.service_inventory_usage', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.service_inventory_usage (
        id            INT             IDENTITY(1,1) PRIMARY KEY,
        service_id    INT             NOT NULL,
        inventory_id  INT             NOT NULL,
        qty_per_job   DECIMAL(10,3)   NOT NULL DEFAULT 1,
        CONSTRAINT FK_siu_service
            FOREIGN KEY (service_id)   REFERENCES dbo.services   (id) ON DELETE CASCADE,
        CONSTRAINT FK_siu_inventory
            FOREIGN KEY (inventory_id) REFERENCES dbo.inventory   (id) ON DELETE CASCADE,
        CONSTRAINT UQ_siu_service_item
            UNIQUE (service_id, inventory_id)
    );
END;
GO

-- ── booking.inventory_deducted ───────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.booking') AND name = N'inventory_deducted'
)
BEGIN
    ALTER TABLE dbo.booking
        ADD inventory_deducted BIT NOT NULL DEFAULT 0;
END;
GO

-- ═══════════════════════════════════════════════════════
--  SoleMate — E-Commerce Migration  ·  T-SQL
--  Run in SSMS after all previous migrations.
-- ═══════════════════════════════════════════════════════
USE shoe_repair_db;
GO
 
-- ── 1. booking — add payment + discount columns ──────────
IF NOT EXISTS(SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name=N'cart_ref')
    ALTER TABLE dbo.booking ADD cart_ref NVARCHAR(40) NULL;
GO
IF NOT EXISTS(SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name=N'payment_status')
    ALTER TABLE dbo.booking ADD payment_status NVARCHAR(20) NOT NULL DEFAULT N'unpaid';
GO
IF NOT EXISTS(SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name=N'payment_ref')
    ALTER TABLE dbo.booking ADD payment_ref NVARCHAR(60) NULL;
GO
IF NOT EXISTS(SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name=N'amount_paid')
    ALTER TABLE dbo.booking ADD amount_paid DECIMAL(10,2) NULL;
GO
 
-- ── 2. customers — loyalty balance ───────────────────────
IF NOT EXISTS(SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.customers') AND name=N'loyalty_points')
    ALTER TABLE dbo.customers ADD loyalty_points INT NOT NULL DEFAULT 0;
GO
 
-- ── 3. payments ──────────────────────────────────────────
IF OBJECT_ID(N'dbo.payments', N'U') IS NULL
CREATE TABLE dbo.payments (
    id                  INT             IDENTITY(1,1) PRIMARY KEY,
    cart_ref            NVARCHAR(40)    NOT NULL UNIQUE,
    pf_payment_id       NVARCHAR(40)    NULL,
    amount_gross        DECIMAL(10,2)   NOT NULL,
    amount_fee          DECIMAL(10,2)   DEFAULT 0,
    amount_net          DECIMAL(10,2)   DEFAULT 0,
    status              NVARCHAR(20)    DEFAULT N'pending',
    payment_method      NVARCHAR(50)    NULL,
    customer_email      NVARCHAR(100)   NOT NULL,
    coupon_code         NVARCHAR(50)    NULL,
    coupon_discount     DECIMAL(10,2)   DEFAULT 0,
    gift_card_code      NVARCHAR(30)    NULL,
    gift_card_discount  DECIMAL(10,2)   DEFAULT 0,
    loyalty_redeemed    INT             DEFAULT 0,
    loyalty_discount    DECIMAL(10,2)   DEFAULT 0,
    created_at          DATETIME        DEFAULT GETDATE(),
    paid_at             DATETIME        NULL
);
GO
 
-- ── 4. coupons ───────────────────────────────────────────
IF OBJECT_ID(N'dbo.coupons', N'U') IS NULL
CREATE TABLE dbo.coupons (
    id              INT             IDENTITY(1,1) PRIMARY KEY,
    code            NVARCHAR(50)    NOT NULL,
    type            NVARCHAR(10)    NOT NULL,      -- 'percent' | 'fixed'
    value           DECIMAL(10,2)   NOT NULL,      -- 10.00 = 10% or R10
    min_order       DECIMAL(10,2)   DEFAULT 0,
    max_uses        INT             DEFAULT 0,     -- 0 = unlimited
    uses_count      INT             DEFAULT 0,
    valid_from      DATE            NULL,
    valid_until     DATE            NULL,
    is_active       BIT             DEFAULT 1,
    created_at      DATETIME        DEFAULT GETDATE(),
    CONSTRAINT UQ_coupon_code UNIQUE (code)
);
GO
 
-- ── 5. gift_cards ────────────────────────────────────────
IF OBJECT_ID(N'dbo.gift_cards', N'U') IS NULL
CREATE TABLE dbo.gift_cards (
    id              INT             IDENTITY(1,1) PRIMARY KEY,
    code            NVARCHAR(20)    NOT NULL,
    initial_balance DECIMAL(10,2)   NOT NULL,
    balance         DECIMAL(10,2)   NOT NULL,
    purchased_by    NVARCHAR(100)   NULL,
    recipient_email NVARCHAR(100)   NULL,
    recipient_name  NVARCHAR(100)   NULL,
    message         NVARCHAR(MAX)   NULL,
    cart_ref        NVARCHAR(40)    NULL,          -- purchase cart_ref
    is_active       BIT             DEFAULT 1,
    expires_at      DATE            NULL,
    created_at      DATETIME        DEFAULT GETDATE(),
    CONSTRAINT UQ_gift_card_code UNIQUE (code)
);
GO
 
-- ── 6. gift_card_transactions ────────────────────────────
IF OBJECT_ID(N'dbo.gift_card_transactions', N'U') IS NULL
CREATE TABLE dbo.gift_card_transactions (
    id              INT             IDENTITY(1,1) PRIMARY KEY,
    gift_card_id    INT             NOT NULL,
    cart_ref        NVARCHAR(40)    NULL,
    amount          DECIMAL(10,2)   NOT NULL,
    balance_after   DECIMAL(10,2)   NOT NULL,
    used_at         DATETIME        DEFAULT GETDATE(),
    CONSTRAINT FK_gct_card FOREIGN KEY (gift_card_id) REFERENCES dbo.gift_cards (id)
);
GO
 
-- ── 7. loyalty_transactions ──────────────────────────────
IF OBJECT_ID(N'dbo.loyalty_transactions', N'U') IS NULL
CREATE TABLE dbo.loyalty_transactions (
    id              INT             IDENTITY(1,1) PRIMARY KEY,
    customer_id     INT             NOT NULL,
    cart_ref        NVARCHAR(40)    NULL,
    points          INT             NOT NULL,      -- positive=earned, negative=redeemed
    type            NVARCHAR(20)    NOT NULL,      -- 'earned'|'redeemed'|'adjusted'
    description     NVARCHAR(200)   NULL,
    created_at      DATETIME        DEFAULT GETDATE(),
    CONSTRAINT FK_lt_customer FOREIGN KEY (customer_id) REFERENCES dbo.customers (id) ON DELETE CASCADE
);
GO

-- ═══════════════════════════════════════════════════════════════════
--  SoleMate — Loyalty Tiers + Referral System Migration  ·  T-SQL
--  Run in SSMS against your SoleMate database.
-- ═══════════════════════════════════════════════════════════════════

USE shoe_repair_db;
GO

-- ── 1. Add referral_code to customers ───────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.customers') AND name = N'referral_code'
)
    ALTER TABLE dbo.customers ADD referral_code NVARCHAR(12) NULL;
GO

-- ── 2. Add referred_by_code to customers ────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.customers') AND name = N'referred_by_code'
)
    ALTER TABLE dbo.customers ADD referred_by_code NVARCHAR(12) NULL;
GO

-- ── 3. Add total_spent to customers (updated on each payment) ───────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.customers') AND name = N'total_spent'
)
    ALTER TABLE dbo.customers ADD total_spent DECIMAL(10,2) NOT NULL DEFAULT 0;
GO

-- ── 4. Unique index on referral_code ────────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.customers') AND name = N'UQ_customers_referral_code'
)
    ALTER TABLE dbo.customers
        ADD CONSTRAINT UQ_customers_referral_code UNIQUE (referral_code);
GO

-- ── 5. Backfill referral codes for existing customers ───────────────
-- Run once; safe to re-run (WHERE referral_code IS NULL)
DECLARE @id INT, @code NVARCHAR(12);
DECLARE cur CURSOR FOR
    SELECT id FROM dbo.customers WHERE referral_code IS NULL;
OPEN cur;
FETCH NEXT FROM cur INTO @id;
WHILE @@FETCH_STATUS = 0
BEGIN
    SET @code = N'REF-' + UPPER(SUBSTRING(REPLACE(NEWID(),N'-',N''),1,6));
    -- Ensure uniqueness
    WHILE EXISTS (SELECT 1 FROM dbo.customers WHERE referral_code = @code)
        SET @code = N'REF-' + UPPER(SUBSTRING(REPLACE(NEWID(),N'-',N''),1,6));
    UPDATE dbo.customers SET referral_code = @code WHERE id = @id;
    FETCH NEXT FROM cur INTO @id;
END;
CLOSE cur; DEALLOCATE cur;
GO

-- ── 6. dbo.referrals table ───────────────────────────────────────────
IF OBJECT_ID(N'dbo.referrals', N'U') IS NULL
    CREATE TABLE dbo.referrals (
        id            INT            IDENTITY(1,1) PRIMARY KEY,
        referrer_id   INT            NOT NULL,   -- customer who shared code
        referee_id    INT            NOT NULL,   -- customer who used the code
        status        NVARCHAR(20)   NOT NULL DEFAULT N'pending',
        -- 'pending'   = referee signed up but not yet paid
        -- 'completed' = referee completed first paid booking
        rewarded      BIT            NOT NULL DEFAULT 0,
        created_at    DATETIME       NOT NULL DEFAULT GETDATE(),
        completed_at  DATETIME       NULL,
        CONSTRAINT FK_referrals_referrer FOREIGN KEY (referrer_id)
            REFERENCES dbo.customers (id) ON DELETE NO ACTION,
        CONSTRAINT FK_referrals_referee FOREIGN KEY (referee_id)
            REFERENCES dbo.customers (id) ON DELETE NO ACTION,
        CONSTRAINT UQ_referrals_referee UNIQUE (referee_id)  -- one referral per person
    );
GO

-- ═══════════════════════════════════════════════════════
--  solemate_refund_migration.sql
--  Adds customer-initiated cancellation + refund requests.
--  Run once in SSMS against shoe_repair_db.
-- ═══════════════════════════════════════════════════════
USE shoe_repair_db;
GO

-- ── dbo.booking: allow 'Cancelled' in status CHECK ──────
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'CK_orders_status')
    ALTER TABLE dbo.booking DROP CONSTRAINT CK_orders_status;
GO

ALTER TABLE dbo.booking
    ADD CONSTRAINT CK_orders_status
    CHECK (status IN (
        N'Pending', N'Received', N'Assessment',
        N'In service', N'Quality check', N'Ready',
        N'Design confirmed', N'Pattern cut', N'Stitching',
        N'Sole bonding', N'Finishing', N'Dispatched', N'Cancelled'
    ));
GO

-- ── dbo.booking: cancellation tracking ──────────────────
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name='refund_status')
    ALTER TABLE dbo.booking ADD refund_status NVARCHAR(20) NULL;   -- NULL | requested | approved | denied | refunded

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name='cancelled_at')
    ALTER TABLE dbo.booking ADD cancelled_at DATETIME2 NULL;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.booking') AND name='cancel_reason')
    ALTER TABLE dbo.booking ADD cancel_reason NVARCHAR(500) NULL;
GO

-- ── dbo.refund_requests: one row per refund request ─────
IF NOT EXISTS (SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.refund_requests') AND type='U')
BEGIN
    CREATE TABLE dbo.refund_requests (
        id              INT IDENTITY(1,1) PRIMARY KEY,
        booking_id      INT NOT NULL,
        customer_id     INT NULL,
        order_ref       NVARCHAR(50) NOT NULL,
        cart_ref        NVARCHAR(50) NULL,
        customer_email  NVARCHAR(255) NOT NULL,
        amount          DECIMAL(10,2) NOT NULL DEFAULT 0,
        reason          NVARCHAR(500) NULL,
        status          NVARCHAR(20) NOT NULL DEFAULT 'pending', -- pending | approved | denied | refunded
        admin_notes     NVARCHAR(500) NULL,
        requested_at    DATETIME2 NOT NULL DEFAULT GETDATE(),
        resolved_at     DATETIME2 NULL,
        CONSTRAINT FK_refund_requests_booking FOREIGN KEY (booking_id) REFERENCES dbo.booking(id)
    );
END
GO

-- ═══════════════════════════════════════════════════════
--  solemate_settings_migration.sql
--  Adds a key/value feature-toggle table so admins can
--  disable Gift Cards / Coupons / Loyalty / Referrals.
--  Run once in SSMS against shoe_repair_db.
-- ═══════════════════════════════════════════════════════
USE shoe_repair_db;
GO

IF NOT EXISTS (SELECT 1 FROM sys.objects WHERE object_id=OBJECT_ID(N'dbo.site_settings') AND type='U')
BEGIN
    CREATE TABLE dbo.site_settings (
        setting_key   NVARCHAR(50) NOT NULL PRIMARY KEY,
        setting_value NVARCHAR(20) NOT NULL,
        updated_at    DATETIME2 NOT NULL DEFAULT GETDATE()
    );

    INSERT INTO dbo.site_settings (setting_key, setting_value) VALUES
        ('gift_cards', '1'),
        ('coupons',    '1'),
        ('loyalty',    '1'),
        ('referrals',  '1');
END
GO

-- ════════════════════════════════════════════════════════
--  Seed Data
-- ════════════════════════════════════════════════════════

-- ── Inventory ────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM dbo.inventory)
BEGIN
    INSERT INTO dbo.inventory
        (item_name, category, quantity, unit, reorder_level, cost_per_unit, supplier)
    VALUES
        -- Cleaning Supplies
        (N'Sneaker Cleaner Solution',      N'Cleaning Supplies', 24,  N'bottle',  6,  85.00,  N'CleanPro SA'),
        (N'Leather Cleaner',               N'Cleaning Supplies', 18,  N'bottle',  5,  95.00,  N'CleanPro SA'),
        (N'Suede Cleaner Spray',           N'Cleaning Supplies', 10,  N'bottle',  4,  75.00,  N'CleanPro SA'),
        (N'Microfibre Cloths',             N'Cleaning Supplies', 48,  N'unit',   10,   8.50,  N'Makro'),
        (N'Soft Bristle Brush',            N'Cleaning Supplies', 15,  N'unit',    5,  35.00,  N'Makro'),
        (N'Foam Applicator Sponges',       N'Cleaning Supplies', 60,  N'unit',   15,   4.00,  N'Makro'),
        (N'Suede Eraser',                  N'Cleaning Supplies',  8,  N'unit',    3,  22.00,  N'CleanPro SA'),

        -- Adhesives
        (N'Contact Cement (500ml)',        N'Adhesives',         12,  N'tin',     4, 110.00,  N'Builders Warehouse'),
        (N'Super Glue Gel',                N'Adhesives',         20,  N'tube',    5,  18.00,  N'Builders Warehouse'),
        (N'Shoe Repair Glue (Bostik)',     N'Adhesives',          9,  N'tube',    3,  45.00,  N'Builders Warehouse'),
        (N'Rubber Cement (250ml)',         N'Adhesives',          6,  N'tin',     3,  75.00,  N'Builders Warehouse'),

        -- Sole Materials
        (N'Rubber Sheet — Black 4mm',      N'Sole Materials',     5,  N'sheet',   2, 180.00,  N'Shoe Supplies SA'),
        (N'Rubber Sheet — White 4mm',      N'Sole Materials',     5,  N'sheet',   2, 180.00,  N'Shoe Supplies SA'),
        (N'Crepe Rubber Sheet',            N'Sole Materials',     3,  N'sheet',   2, 220.00,  N'Shoe Supplies SA'),
        (N'Gum Rubber Sheet (Translucent)',N'Sole Materials',     4,  N'sheet',   2, 250.00,  N'Shoe Supplies SA'),
        (N'Heel Caps — Black (assorted)',  N'Sole Materials',    30,  N'pair',    8,  28.00,  N'Shoe Supplies SA'),
        (N'Heel Caps — Nude (assorted)',   N'Sole Materials',    20,  N'pair',    6,  28.00,  N'Shoe Supplies SA'),

        -- Leather Care
        (N'Leather Conditioner',           N'Leather Care',      14,  N'bottle',  4,  95.00,  N'Leather World SA'),
        (N'Mink Oil',                      N'Leather Care',       8,  N'tin',     3,  65.00,  N'Leather World SA'),
        (N'Leather Dye — Black',           N'Leather Care',      10,  N'bottle',  3, 120.00,  N'Leather World SA'),
        (N'Leather Dye — Brown',           N'Leather Care',       8,  N'bottle',  3, 120.00,  N'Leather World SA'),
        (N'Leather Dye — White',           N'Leather Care',       6,  N'bottle',  2, 120.00,  N'Leather World SA'),
        (N'Leather Dye — Tan',             N'Leather Care',       4,  N'bottle',  2, 120.00,  N'Leather World SA'),
        (N'Leather Waterproof Spray',      N'Leather Care',      12,  N'can',     4,  85.00,  N'Leather World SA'),

        -- Thread & Stitching
        (N'Waxed Nylon Thread — Black',    N'Thread & Stitching', 8, N'spool',    2,  55.00,  N'Shoe Supplies SA'),
        (N'Waxed Nylon Thread — Brown',    N'Thread & Stitching', 6, N'spool',    2,  55.00,  N'Shoe Supplies SA'),
        (N'Waxed Nylon Thread — Cream',    N'Thread & Stitching', 4, N'spool',    2,  55.00,  N'Shoe Supplies SA'),
        (N'Curved Needles (pack of 10)',   N'Thread & Stitching',10, N'pack',     3,  32.00,  N'Shoe Supplies SA'),
        (N'Straight Harness Needles',      N'Thread & Stitching', 8, N'pack',     3,  28.00,  N'Shoe Supplies SA'),

        -- Packaging
        (N'Dust Bags — Small',             N'Packaging',         50,  N'unit',   15,   6.00,  N'Makro'),
        (N'Dust Bags — Large',             N'Packaging',         40,  N'unit',   10,   8.00,  N'Makro'),
        (N'Shoe Boxes — Standard',         N'Packaging',         25,  N'unit',    8,  15.00,  N'Makro'),
        (N'Tissue Paper',                  N'Packaging',        100,  N'sheet',  20,   1.50,  N'Makro'),
        (N'SoleMate Thank-You Cards',      N'Packaging',         40,  N'unit',   10,   4.00,  N'In-house');
END;
GO


-- ── Services ────────────────────────────────────────────
INSERT INTO dbo.services (service_key, service_name, description, price, icon, price_from, sort_order)
VALUES
    (N'deep-clean',          N'Deep Clean',          N'Full interior and exterior clean, deodorise, and condition. Restores freshness to any shoe.',         350.00, N'🧽', 0, 1),
    (N'sole-repair',         N'Sole Repair',         N'Resole, re-glue, or replace worn soles. Extends shoe life by years.',                                450.00, N'👟', 1, 2),
    (N'leather-restoration', N'Leather Restoration', N'Repair scuffs, restore colour, deep condition. Premium leather treatment.',                           600.00, N'✨', 1, 3),
    (N'sneaker-whitening',   N'Sneaker Whitening',   N'Restore white midsoles and uppers to factory-fresh brightness.',                                      280.00, N'⚪', 0, 4),
    (N'heel-replacement',    N'Heel Replacement',    N'Replace worn-down heels on pumps, boots, and dress shoes.',                                           380.00, N'👠', 0, 5),
    (N'full-restoration',    N'Full Restoration',    N'Complete overhaul: clean, repair, restore colour, replace sole. Like new.',                           950.00, N'🔧', 1, 6),
	(N're-glue',                 N'Re-Glue',                  N'Sole separating from the upper? We re-bond and seal the adhesive for a clean, lasting fix.',           150.00, N'🔧', 0, 7),
    (N'stitch-work',             N'Stitch Work',              N'Loose threads or torn seams repaired with precision stitching to restore structural integrity.',         180.00, N'🪡', 0, 8),
    (N'colour-restoration',      N'Colour Restoration',       N'Faded or scuffed colour on suede, leather, or canvas restored to its original vibrancy.',              250.00, N'🎨', 0, 9),
    (N'rubber-sole-deyellowing', N'Rubber Sole De-yellowing', N'Remove yellowing from rubber midsoles and outsoles for a clean, factory-fresh look.',                  180.00, N'⚪', 0, 10),
    (N'under-sole-replacement',  N'Under-sole Replacement',   N'Complete replacement of worn-out undersoles. Restores grip, cushioning, and extends shoe life.',       650.00, N'👟', 0, 11),
    (N'gum-sole-deyellowing',    N'Gum Sole De-Yellowing',    N'Restore translucent gum soles to their original amber-clear colour with our specialist treatment.',    280.00, N'🟡', 0, 12);
GO

USE shoe_repair_db;
GO

ALTER TABLE dbo.booking
DROP CONSTRAINT FK_orders_services;
GO

DROP TABLE dbo.services;
GO

SELECT * FROM sys.tables WHERE name = 'services';

-- ── Orders ──────────────────────────────────────────────
INSERT INTO dbo.booking (order_ref, customer_name, email, phone, service_id, shoe_type, drop_date, collection, status, order_date)
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
    ALTER TABLE dbo.booking DROP CONSTRAINT CK_orders_status;
END;
GO

ALTER TABLE dbo.booking
    ADD CONSTRAINT CK_orders_status
    CHECK (status IN (
        N'Pending', N'Received', N'Assessment',
        N'In service', N'Quality check', N'Ready',
        N'Design confirmed', N'Pattern cut', N'Stitching',
        N'Sole bonding', N'Finishing', N'Dispatched'
    ));
GO
-- ════════════════════════════════════════════════════════
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.booking')
      AND name = N'status'
)
BEGIN
    ALTER TABLE dbo.booking
        ADD status NVARCHAR(20) DEFAULT N'Received'
            CONSTRAINT CK_orders_status_migration
            CHECK (status IN (
                N'Received', N'Assessment',
                N'In service', N'Quality check', N'Ready'
            ));
END;
GO

-- ── Seed: service → inventory mappings ──────────────────

-- ── Deep Clean ───────────────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Sneaker Cleaner Solution',  1.0),
    (N'Microfibre Cloths',         2.0),
    (N'Foam Applicator Sponges',   1.0),
    (N'Soft Bristle Brush',        0.1)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'deep-clean'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Sole Repair ──────────────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Contact Cement (500ml)',     0.25),
    (N'Rubber Sheet — Black 4mm',   0.25),
    (N'Heel Caps — Black (assorted)', 1.0),
    (N'Microfibre Cloths',           1.0)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'sole-repair'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Leather Restoration ──────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Leather Cleaner',            1.0),
    (N'Leather Conditioner',        1.0),
    (N'Leather Dye — Black',        0.25),
    (N'Microfibre Cloths',          2.0),
    (N'Foam Applicator Sponges',    1.0)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'leather-restoration'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Sneaker Whitening ────────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Sneaker Cleaner Solution',  1.0),
    (N'Foam Applicator Sponges',   2.0),
    (N'Microfibre Cloths',         2.0),
    (N'Soft Bristle Brush',        0.1)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'sneaker-whitening'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Heel Replacement ─────────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Heel Caps — Black (assorted)', 1.0),
    (N'Contact Cement (500ml)',       0.15),
    (N'Microfibre Cloths',            1.0)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'heel-replacement'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Full Restoration ─────────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Sneaker Cleaner Solution',     1.0),
    (N'Leather Cleaner',              1.0),
    (N'Leather Conditioner',          1.0),
    (N'Contact Cement (500ml)',       0.5),
    (N'Rubber Sheet — Black 4mm',     0.5),
    (N'Heel Caps — Black (assorted)', 1.0),
    (N'Microfibre Cloths',            3.0),
    (N'Foam Applicator Sponges',      2.0)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'full-restoration'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Re-Glue ──────────────────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Rubber Cement (250ml)',   0.5),
    (N'Contact Cement (500ml)', 0.25)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N're-glue'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Stitch Work ──────────────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Waxed Nylon Thread — Black',  0.25),
    (N'Curved Needles (pack of 10)', 0.1)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'stitch-work'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Colour Restoration ───────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Leather Dye — Black',       0.25),
    (N'Leather Dye — Brown',       0.25),
    (N'Foam Applicator Sponges',   1.0),
    (N'Microfibre Cloths',         2.0),
    (N'Suede Cleaner Spray',       0.25)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'colour-restoration'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Rubber Sole De-yellowing ─────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Foam Applicator Sponges',  2.0),
    (N'Microfibre Cloths',        2.0),
    (N'Sneaker Cleaner Solution', 0.5)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'rubber-sole-deyellowing'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Under-sole Replacement ───────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Rubber Sheet — Black 4mm',  1.0),
    (N'Contact Cement (500ml)',    0.5),
    (N'Microfibre Cloths',         1.0)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'under-sole-replacement'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- ── Gum Sole De-yellowing ────────────────────────────────
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty
FROM dbo.services s
CROSS JOIN (VALUES
    (N'Foam Applicator Sponges',        2.0),
    (N'Microfibre Cloths',              2.0),
    (N'Gum Rubber Sheet (Translucent)', 0.1)
) AS v(item, qty)
JOIN dbo.inventory i ON i.item_name = v.item
WHERE s.service_key = N'gum-sole-deyellowing'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.service_inventory_usage x
      WHERE x.service_id = s.id AND x.inventory_id = i.id
  );
GO

-- Custom Shoes — the key one
INSERT INTO dbo.service_inventory_usage (service_id, inventory_id, qty_per_job)
SELECT s.id, i.id, v.qty FROM dbo.services s
CROSS JOIN (VALUES
    (N'EVA Foam Midsole Blank',     1.0),
    (N'Canvas Upper Material',      0.5),
    (N'Mesh Fabric Upper',          0.5),
    (N'Leather Upper Sheet',        0.5),
    (N'Suede Upper Material',       0.5),
    (N'Flat Shoelaces (pair)',       1.0),
    (N'Memory Foam Insole',         1.0),
    (N'Toe Box Stiffener',          1.0),
    (N'Heel Counter',               1.0),
    (N'Shoe Lining Fabric',         0.25),
    (N'Eyelets (pack of 24)',        0.5),
    (N'Aglets (pack of 20)',         0.5),
    (N'Contact Cement (500ml)',      0.5),
    (N'Waxed Nylon Thread — Black', 0.25),
    (N'Dust Bags — Large',          1.0),
    (N'SoleMate Thank-You Cards',   1.0)
) AS v(item,qty)
JOIN dbo.inventory i ON i.item_name=v.item WHERE s.service_key=N'custom-shoes'
AND NOT EXISTS(SELECT 1 FROM dbo.service_inventory_usage x WHERE x.service_id=s.id AND x.inventory_id=i.id);
GO

-- ════════════════════════════════════════════════════════
--  Verify mappings were inserted
-- ════════════════════════════════════════════════════════
 --SELECT s.service_name, i.item_name, u.qty_per_job
 --FROM dbo.service_inventory_usage u
 --JOIN dbo.services   s ON u.service_id   = s.id
 --JOIN dbo.inventory  i ON u.inventory_id = i.id
 --ORDER BY s.service_name, i.item_name;

 -- ── Seed: sample coupons ─────────────────────────────────
IF NOT EXISTS(SELECT 1 FROM dbo.coupons WHERE code=N'WELCOME10')
    INSERT INTO dbo.coupons (code,type,value,min_order,max_uses,valid_until)
    VALUES (N'WELCOME10',N'percent',10.00,0.00,0,DATEADD(YEAR,1,GETDATE()));
GO
IF NOT EXISTS(SELECT 1 FROM dbo.coupons WHERE code=N'SAVE50')
    INSERT INTO dbo.coupons (code,type,value,min_order,max_uses,valid_until)
    VALUES (N'SAVE50',N'fixed',50.00,300.00,100,DATEADD(YEAR,1,GETDATE()));
GO
IF NOT EXISTS(SELECT 1 FROM dbo.coupons WHERE code=N'STUDENT15')
    INSERT INTO dbo.coupons (code,type,value,min_order,max_uses,valid_until)
    VALUES (N'STUDENT15',N'percent',15.00,0.00,0,DATEADD(YEAR,1,GETDATE()));
GO

-- ═══════════════════════════════════════════════════════
--  SoleMate — customization_data column migration · T-SQL
--  Adds dbo.booking.customization_data (NVARCHAR(MAX) JSON)
--  Fixes: solemate_payment_init.php fatal PDOException
--  Safe to re-run.
-- ═══════════════════════════════════════════════════════
USE shoe_repair_db;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.booking') AND name = N'customization_data'
)
BEGIN
    ALTER TABLE dbo.booking
        ADD customization_data NVARCHAR(MAX) NULL;
END;
GO