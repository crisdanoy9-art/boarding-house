-- Create the schema if it doesn't exist
CREATE SCHEMA IF NOT EXISTS bh;

-- Set search path so you don't always have to type 'bh.' 
-- (Optional, but recommended for the current session)
SET search_path TO bh, public;

-- Drop tables within the schema
DROP TABLE IF EXISTS bh.payments CASCADE;
DROP TABLE IF EXISTS bh.tenants CASCADE;
DROP TABLE IF EXISTS bh.reservations CASCADE;
DROP TABLE IF EXISTS bh.beds CASCADE;
DROP TABLE IF EXISTS bh.rooms CASCADE;
DROP TABLE IF EXISTS bh.floors CASCADE;
DROP TABLE IF EXISTS bh.users CASCADE;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE bh.users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role VARCHAR(10) DEFAULT 'tenant' CHECK (role IN ('admin', 'tenant')),
    profile_image VARCHAR(255) DEFAULT 'default.png',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- FLOORS TABLE
-- ============================================================
CREATE TABLE bh.floors (
    id SERIAL PRIMARY KEY,
    floor_number INTEGER UNIQUE NOT NULL,
    floor_name VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- ROOMS TABLE
-- ============================================================
CREATE TABLE bh.rooms (
    id SERIAL PRIMARY KEY,
    floor_id INTEGER NOT NULL REFERENCES bh.floors(id) ON DELETE CASCADE,
    room_number VARCHAR(10) NOT NULL,
    capacity INTEGER DEFAULT 4,
    price DECIMAL(10, 2) NOT NULL,
    status VARCHAR(15) DEFAULT 'available' CHECK (status IN ('available', 'full', 'maintenance')),
    description TEXT,
    amenities TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(floor_id, room_number)
);

-- ============================================================
-- BEDS TABLE
-- ============================================================
CREATE TABLE bh.beds (
    id SERIAL PRIMARY KEY,
    room_id INTEGER NOT NULL REFERENCES bh.rooms(id) ON DELETE CASCADE,
    bed_number INTEGER NOT NULL,
    status VARCHAR(10) DEFAULT 'available' CHECK (status IN ('available', 'occupied', 'reserved')),
    tenant_id INTEGER REFERENCES bh.users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(room_id, bed_number)
);

-- ============================================================
-- RESERVATIONS TABLE
-- ============================================================
CREATE TABLE bh.reservations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES bh.users(id) ON DELETE CASCADE,
    room_id INTEGER NOT NULL REFERENCES bh.rooms(id) ON DELETE CASCADE,
    bed_id INTEGER NOT NULL REFERENCES bh.beds(id) ON DELETE CASCADE,
    move_in_date DATE NOT NULL,
    move_out_date DATE,
    notes TEXT,
    status VARCHAR(10) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled')),
    admin_notes TEXT,
    approved_by INTEGER REFERENCES bh.users(id),
    approved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TENANTS TABLE
-- ============================================================
CREATE TABLE bh.tenants (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES bh.users(id) ON DELETE CASCADE,
    room_id INTEGER NOT NULL REFERENCES bh.rooms(id) ON DELETE CASCADE,
    bed_id INTEGER NOT NULL REFERENCES bh.beds(id) ON DELETE CASCADE,
    reservation_id INTEGER REFERENCES bh.reservations(id),
    move_in_date DATE NOT NULL,
    move_out_date DATE,
    status VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- PAYMENTS TABLE
-- ============================================================
CREATE TABLE bh.payments (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES bh.tenants(id) ON DELETE CASCADE,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    due_date DATE,
    payment_month VARCHAR(7),
    payment_method VARCHAR(20) DEFAULT 'cash' CHECK (payment_method IN ('cash', 'gcash', 'bank_transfer', 'check')),
    reference_number VARCHAR(100),
    status VARCHAR(10) DEFAULT 'paid' CHECK (status IN ('paid', 'pending', 'overdue')),
    notes TEXT,
    recorded_by INTEGER REFERENCES bh.users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO bh.floors (floor_number, floor_name, description) VALUES
(1, 'Ground Floor', 'Ground level with easy access'),
(2, 'Second Floor', 'Second level with balcony view'),
(3, 'Third Floor', 'Top floor with panoramic view');

-- floor
INSERT INTO bh.rooms (floor_id, room_number, capacity, price, status, amenities) VALUES
(1, '101', 4, 1300.00, 'available', 'Shared Bathroom'),
(1, '102', 4, 1300.00, 'available', 'Shared Bathroom'),
(1, '103', 4, 1300.00, 'available', 'Private Bathroom'),
(1, '104', 4, 1300.00, 'available', 'Private Bathroom'),
(1, '105', 4, 1300.00, 'available', 'Fan, Shared Bathroom'),
(1, '106', 4, 1300.00, 'available', 'Fan, Shared Bathroom');

INSERT INTO bh.rooms (floor_id, room_number, capacity, price, status, amenities) VALUES
(2, '201', 4, 1300.00, 'available', 'Shared Bathroom, Balcony'),
(2, '202', 4, 1300.00, 'available', 'Shared Bathroom, Balcony'),
(2, '203', 4, 1300.00, 'available', 'Private Bathroom'),
(2, '204', 4, 1300.00, 'available', 'Private Bathroom'),
(2, '205', 4, 1300.00, 'available', 'Shared Bathroom'),
(2, '206', 4, 1300.00, 'available', 'Shared Bathroom');

INSERT INTO bh.rooms (floor_id, room_number, capacity, price, status, amenities) VALUES
(3, '301', 4, 1300.00, 'available', 'Shared Bathroom, City View'),
(3, '302', 4, 1300.00, 'available', 'Shared Bathroom, City View'),
(3, '303', 4, 1300.00, 'available', 'Private Bathroom, Panoramic View'),
(3, '304', 4, 1300.00, 'available', 'Private Bathroom, Panoramic View'),
(3, '305', 4, 1300.00, 'available', 'Shared Bathroom'),
(3, '306', 4, 1300.00, 'available', 'Shared Bathroom');

 -- pass admin CrisAdmin@2026
INSERT INTO bh.users (name, email, password, phone, role) 
VALUES (
    'Cris Danoy', 
    'crisdanoy9@gmail.com', 
    '$2y$10$P/0P7Qv8mZ5kR.GjW9XoOe6bT7zY/X7mB.C8G0L1hR5N9V3J2K4W', 
    '09633951825', 
    'admin'
);


-- Replace 'your_db_user' with the actual username your app uses to connect
-- (Usually 'postgres' or a specific name you created)
ALTER ROLE postgres SET search_path TO bh, public;

-- Beds (4 per room, 72 total)
DO $$
DECLARE
    r RECORD;
    b INTEGER;
BEGIN
    FOR r IN SELECT id FROM rooms LOOP
        FOR b IN 1..4 LOOP
            INSERT INTO beds (room_id, bed_number, status) VALUES (r.id, b, 'available');
        END LOOP;
    END LOOP;
END $$;

-- Update some beds as occupied for sample data
UPDATE beds SET status = 'occupied', tenant_id = 2 WHERE room_id = 1 AND bed_number = 1;
UPDATE beds SET status = 'occupied', tenant_id = 3 WHERE room_id = 1 AND bed_number = 2;
UPDATE beds SET status = 'occupied', tenant_id = 4 WHERE room_id = 7 AND bed_number = 1;
UPDATE beds SET status = 'reserved' WHERE room_id = 3 AND bed_number = 1;

-- Update room statuses
UPDATE rooms SET status = 'available' WHERE id NOT IN (
    SELECT DISTINCT room_id FROM beds WHERE status IN ('occupied', 'reserved')
);

-- Sample reservations
INSERT INTO reservations (user_id, room_id, bed_id, move_in_date, status) VALUES
(2, 1, 1, '2025-01-01', 'approved'),
(3, 1, 2, '2025-01-15', 'approved'),
(4, 7, 1, '2025-02-01', 'approved'),
(5, 3, 9, '2025-03-01', 'pending');

-- Sample tenants
INSERT INTO tenants (user_id, room_id, bed_id, reservation_id, move_in_date, status) VALUES
(2, 1, 1, 1, '2025-01-01', 'active'),
(3, 1, 2, 2, '2025-01-15', 'active'),
(4, 7, (SELECT id FROM beds WHERE room_id = 7 AND bed_number = 1), 3, '2025-02-01', 'active');

-- Sample payments
INSERT INTO payments (tenant_id, amount, payment_date, due_date, payment_month, payment_method, status, recorded_by) VALUES
(1, 2500.00, '2025-01-01', '2025-01-05', '2025-01', 'cash', 'paid', 1),
(1, 2500.00, '2025-02-02', '2025-02-05', '2025-02', 'gcash', 'paid', 1),
(1, 2500.00, '2025-03-03', '2025-03-05', '2025-03', 'cash', 'paid', 1),
(2, 2500.00, '2025-01-15', '2025-01-20', '2025-01', 'cash', 'paid', 1),
(2, 2500.00, '2025-02-14', '2025-02-20', '2025-02', 'bank_transfer', 'paid', 1),
(3, 2800.00, '2025-02-01', '2025-02-05', '2025-02', 'gcash', 'paid', 1),
(3, 2800.00, '2025-03-01', '2025-03-05', '2025-03', 'cash', 'pending', 1);

-- ============================================================
-- NADELAS BOARDING HOUSE — DATABASE ADDITIONS v3.0
-- Run these on top of your existing database.sql
-- ============================================================

SET search_path TO bh, public;

-- ── Announcements table ──
CREATE TABLE IF NOT EXISTS bh.announcements (
    id         SERIAL PRIMARY KEY,
    title      VARCHAR(200) NOT NULL,
    body       TEXT NOT NULL,
    type       VARCHAR(20) DEFAULT 'info'
                CHECK (type IN ('info','warning','danger','success','gold')),
    icon       VARCHAR(50) DEFAULT 'bullhorn',
    audience   VARCHAR(10) DEFAULT 'all'
                CHECK (audience IN ('all','tenants','admin')),
    is_active  BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP,
    created_by INTEGER REFERENCES bh.users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── System settings (key-value store) ──
CREATE TABLE IF NOT EXISTS bh.system_settings (
    key        VARCHAR(100) PRIMARY KEY,
    value      TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default settings
INSERT INTO bh.system_settings (key, value) VALUES
('maintenance_mode',    '0'),
('maintenance_message', 'System is under maintenance. Please check back later.'),
('maintenance_eta',     ''),
('site_name',           'Nadelas Boarding House'),
('admin_phone',         '09633951825'),
('admin_facebook',      'https://www.facebook.com/cris.danoy.7/'),
('admin_email',         'crisdanoy9@gmail.com'),
('advance_deposit',     '1300'),
('monthly_rate',        '1300')
ON CONFLICT (key) DO NOTHING;

-- ── Add advance_deposit_paid column to tenants ──
ALTER TABLE bh.tenants
  ADD COLUMN IF NOT EXISTS advance_deposit_paid BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS move_out_date DATE;

-- ── Add recorded_by to payments if missing ──
ALTER TABLE bh.payments
  ADD COLUMN IF NOT EXISTS recorded_by INTEGER REFERENCES bh.users(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ── Add updated_at to reservations if missing ──
ALTER TABLE bh.reservations
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ── Add address to users if missing ──
ALTER TABLE bh.users
  ADD COLUMN IF NOT EXISTS address TEXT,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ── Sample announcements ──
INSERT INTO bh.announcements (title, body, type, icon, audience, created_by) VALUES
('Monthly Payment Reminder',
 'This is a reminder that monthly rent of ₱1,300 is due on the 1st of every month. Please settle your balance on time to avoid penalties.',
 'warning', 'calendar-day', 'tenants',
 (SELECT id FROM bh.users WHERE role='admin' LIMIT 1)),

('Advance Deposit Policy',
 'All new tenants are required to pay a ₱1,300 advance deposit upon room approval. Rooms will only be confirmed once the advance deposit is received.',
 'gold', 'peso-sign', 'tenants',
 (SELECT id FROM bh.users WHERE role='admin' LIMIT 1)),

('3-Month Non-Payment Policy',
 'Please be advised: tenants with 3 or more consecutive months of unpaid rent will have their room assignment automatically cancelled per house rules.',
 'danger', 'exclamation-triangle', 'all',
 (SELECT id FROM bh.users WHERE role='admin' LIMIT 1))
ON CONFLICT DO NOTHING;

-- ── Function: auto-vacate tenants with 3+ months overdue ──
-- Run this periodically (or call from PHP) to enforce the 3-month rule
CREATE OR REPLACE FUNCTION bh.auto_vacate_overdue_tenants()
RETURNS INTEGER AS $$
DECLARE
  vacated_count INTEGER := 0;
  t RECORD;
BEGIN
  FOR t IN
    SELECT ten.id, ten.bed_id, ten.room_id, ten.user_id
    FROM bh.tenants ten
    WHERE ten.status = 'active'
      AND (
        SELECT COUNT(*) FROM bh.payments p
        WHERE p.tenant_id = ten.id
          AND p.status = 'overdue'
          AND p.due_date <= CURRENT_DATE - INTERVAL '90 days'
      ) >= 3
  LOOP
    -- Deactivate tenant
    UPDATE bh.tenants
    SET status = 'inactive',
        move_out_date = CURRENT_DATE,
        updated_at = NOW()
    WHERE id = t.id;

    -- Free the bed
    UPDATE bh.beds
    SET status = 'available', tenant_id = NULL
    WHERE id = t.bed_id;

    -- Update room status
    UPDATE bh.rooms
    SET status = CASE
        WHEN (SELECT COUNT(*) FROM bh.beds WHERE room_id = t.room_id AND status = 'occupied') >= 4
        THEN 'full' ELSE 'available'
    END
    WHERE id = t.room_id;

    vacated_count := vacated_count + 1;
  END LOOP;

  RETURN vacated_count;
END;
$$ LANGUAGE plpgsql;

-- ── View: overdue tenants ──
CREATE OR REPLACE VIEW bh.v_overdue_tenants AS
SELECT
    u.name,
    u.email,
    u.phone,
    r.room_number,
    f.floor_number,
    b.bed_number,
    t.id AS tenant_id,
    (SELECT COUNT(*) FROM bh.payments p
     WHERE p.tenant_id = t.id AND p.status = 'overdue') AS overdue_months,
    (SELECT SUM(p.amount) FROM bh.payments p
     WHERE p.tenant_id = t.id AND p.status = 'overdue') AS total_overdue
FROM bh.tenants t
JOIN bh.users u  ON u.id  = t.user_id
JOIN bh.rooms r  ON r.id  = t.room_id
JOIN bh.floors f ON f.id  = r.floor_id
JOIN bh.beds b   ON b.id  = t.bed_id
WHERE t.status = 'active'
ORDER BY overdue_months DESC;

-- ── Indexes for performance ──
CREATE INDEX IF NOT EXISTS idx_payments_tenant_status   ON bh.payments(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_payments_payment_date    ON bh.payments(payment_date DESC);
CREATE INDEX IF NOT EXISTS idx_reservations_user_status ON bh.reservations(user_id, status);
CREATE INDEX IF NOT EXISTS idx_tenants_user_status      ON bh.tenants(user_id, status);
CREATE INDEX IF NOT EXISTS idx_announcements_active     ON bh.announcements(is_active, audience);