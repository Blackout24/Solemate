-- ═══════════════════════════════════════════════════════
--  SoleMate Database Backup
--  Generated : 2026-06-26 10:51:06
--  Tables    : services, orders, reviews, admin_users
--
--  Restore: run this file in SSMS against shoe_repair_db
--           after the schema (SQLQuery1.sql) is in place.
-- ═══════════════════════════════════════════════════════

USE shoe_repair_db;
SET NOCOUNT ON;

-- ── services ──────────────────────────────────────────
SET IDENTITY_INSERT dbo.[services] ON;
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('1', 'deep-clean', 'Deep Clean', 'Full interior and exterior clean, deodorise, and condition. Restores freshness to any shoe.', '350.00', '🧽', '0', '1');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('2', 'sole-repair', 'Sole Repair', 'Resole, re-glue, or replace worn soles. Extends shoe life by years.', '450.00', '👟', '1', '2');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('3', 'leather-restoration', 'Leather Restoration', 'Repair scuffs, restore colour, deep condition. Premium leather treatment.', '600.00', '✨', '1', '3');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('4', 'sneaker-whitening', 'Sneaker Whitening', 'Restore white midsoles and uppers to factory-fresh brightness.', '280.00', '⚪', '0', '4');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('5', 'heel-replacement', 'Heel Replacement', 'Replace worn-down heels on pumps, boots, and dress shoes.', '380.00', '👠', '0', '5');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('6', 'full-restoration', 'Full Restoration', 'Complete overhaul: clean, repair, restore colour, replace sole. Like new.', '950.00', '🔧', '1', '6');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('7', 're-glue', 'Re-Glue', 'Sole separating from the upper? We re-bond and seal the adhesive for a clean, lasting fix.', '150.00', '🔧', '0', '7');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('8', 'stitch-work', 'Stitch Work', 'Loose threads or torn seams repaired with precision stitching to restore structural integrity.', '180.00', '🪡', '0', '8');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('9', 'colour-restoration', 'Colour Restoration', 'Faded or scuffed colour on suede, leather, or canvas restored to its original vibrancy.', '250.00', '🎨', '0', '9');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('10', 'rubber-sole-deyellowing', 'Rubber Sole De-yellowing', 'Remove yellowing from rubber midsoles and outsoles for a clean, factory-fresh look.', '180.00', '⚪', '0', '10');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('11', 'under-sole-replacement', 'Under-sole Replacement', 'Complete replacement of worn-out undersoles. Restores grip, cushioning, and extends shoe life.', '650.00', '👟', '0', '11');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('12', 'gum-sole-deyellowing', 'Gum Sole De-Yellowing', 'Restore translucent gum soles to their original amber-clear colour with our specialist treatment.', '280.00', '🟡', '0', '12');
INSERT INTO dbo.[services] ([id], [service_key], [service_name], [description], [price], [icon], [price_from], [sort_order]) VALUES ('13', 'custom-shoes', 'Custom Shoes', 'Design your own shoe in our 3D Customizer — choose colours for every part. Handcrafted to your exact specification.', '1200.00', '🎨', '0', '13');
SET IDENTITY_INSERT dbo.[services] OFF;

-- ── orders ──────────────────────────────────────────

-- ── reviews ──────────────────────────────────────────
SET IDENTITY_INSERT dbo.[reviews] ON;
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('1', 'Siya Khoza', 'SK', 'Berea, Durban', 'My Air Force 1s look better than the day I bought them. Genuinely blown away by the results.', '5', '2026-04-28 14:26:11.750');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('2', 'Nomsa Mthembu', 'NM', 'Umhlanga, Durban', 'Italian leather oxfords with deep scuffs — the restoration was absolutely remarkable.', '5', '2026-04-28 14:26:11.750');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('3', 'Aryan Patel', 'AP', 'Westville, Durban', 'Three pairs via courier. Smooth tracking, SMS updates, incredible results. Will use again.', '5', '2026-04-28 14:26:11.750');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('4', 'Marco Govender', 'MG', 'Chatsworth, Durban', 'Timberlands fully restored — new soles, cleaned, waterproofed. Looked brand new!', '5', '2026-04-28 14:26:11.750');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('5', 'Thandeka Nxumalo', 'TN', 'Pinetown, Durban', 'Brought in a pair of heels I thought were ruined. They came back perfect. Incredible craftsmanship.', '5', '2026-04-28 14:26:11.750');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('6', 'Rajan Sharma', 'RS', 'Phoenix, Durban', 'Quick turnaround, kept me updated via SMS. My suede loafers look immaculate. Highly recommend.', '5', '2026-04-28 14:26:11.750');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('7', 'Siya Khoza', 'SK', 'Berea, Durban', 'My Air Force 1s look better than the day I bought them. Genuinely blown away by the results.', '5', '2026-04-29 13:36:38.280');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('8', 'Nomsa Mthembu', 'NM', 'Umhlanga, Durban', 'Italian leather oxfords with deep scuffs — the restoration was absolutely remarkable.', '5', '2026-04-29 13:36:38.280');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('9', 'Aryan Patel', 'AP', 'Westville, Durban', 'Three pairs via courier. Smooth tracking, SMS updates, incredible results. Will use again.', '5', '2026-04-29 13:36:38.280');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('10', 'Marco Govender', 'MG', 'Chatsworth, Durban', 'Timberlands fully restored — new soles, cleaned, waterproofed. Looked brand new!', '5', '2026-04-29 13:36:38.280');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('11', 'Thandeka Nxumalo', 'TN', 'Pinetown, Durban', 'Brought in a pair of heels I thought were ruined. They came back perfect. Incredible craftsmanship.', '5', '2026-04-29 13:36:38.280');
INSERT INTO dbo.[reviews] ([id], [customer], [initials], [location], [review_text], [stars], [created_at]) VALUES ('12', 'Rajan Sharma', 'RS', 'Phoenix, Durban', 'Quick turnaround, kept me updated via SMS. My suede loafers look immaculate. Highly recommend.', '5', '2026-04-29 13:36:38.280');
SET IDENTITY_INSERT dbo.[reviews] OFF;

-- ── admin_users ──────────────────────────────────────────
SET IDENTITY_INSERT dbo.[admin_users] ON;
INSERT INTO dbo.[admin_users] ([id], [username], [password_hash], [display_name], [created_at], [last_login]) VALUES ('1', 'admin', '$2y$10$XOBEW2jPn1O9bIDl8O5Q..frHZaGoHVZvqN2ZJeF40ou6lVW2pIp2', 'Admin', '2026-04-28 14:26:15.680', '2026-06-26 10:50:36.243');
SET IDENTITY_INSERT dbo.[admin_users] OFF;

-- ── End of backup ────────────────────────────────────
