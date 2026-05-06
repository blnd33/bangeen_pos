-- ============================================================
-- Bangeen Crystal POS — Full Database Setup
-- Run this entire file in one go
-- ============================================================

CREATE DATABASE IF NOT EXISTS bangeen_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bangeen_pos;

-- Drop tables if exist to start fresh
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS stock_log;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS counters;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS debts;
SET FOREIGN_KEY_CHECKS=1;

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name_ar VARCHAR(100),
    full_name_en VARCHAR(100),
    role ENUM('admin','manager','cashier') DEFAULT 'cashier',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100),
    contact VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Counters
CREATE TABLE counters (
    name VARCHAR(50) PRIMARY KEY,
    value INT DEFAULT 0
);

-- Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(80) NOT NULL,
    name_en VARCHAR(80),
    color VARCHAR(10) DEFAULT '#E03A1E'
);

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150),
    category_id INT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost DECIMAL(10,2) DEFAULT 0.00,
    stock_qty INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    supplier_id INT,
    barcode_text VARCHAR(100),
    barcode_image VARCHAR(255),
    unit_ar VARCHAR(30) DEFAULT 'قطعة',
    unit_en VARCHAR(30) DEFAULT 'pcs',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Sales
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE NOT NULL,
    user_id INT,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_total DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','card','split') DEFAULT 'cash',
    cash_tendered DECIMAL(10,2) DEFAULT 0,
    change_given DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    status ENUM('completed','refunded','void') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Sale Items
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT,
    product_name_ar VARCHAR(150) NOT NULL,
    product_name_en VARCHAR(150),
    barcode_text VARCHAR(100),
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    discount_pct DECIMAL(5,2) DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Stock Log
CREATE TABLE stock_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    type ENUM('in','out','adjustment','sale','return') DEFAULT 'in',
    quantity INT NOT NULL,
    note_ar TEXT,
    note_en TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Settings
CREATE TABLE settings (
    key_name VARCHAR(80) PRIMARY KEY,
    value TEXT
);

-- Permissions
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('manager','cashier') NOT NULL,
    module VARCHAR(50) NOT NULL,
    can_view TINYINT(1) DEFAULT 1,
    can_add TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    UNIQUE KEY role_module (role, module)
);

-- ── Finance Module ─────────────────────────────────────────

-- Expenses
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    description_ar TEXT,
    description_en TEXT,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expense_date DATE NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Debts
CREATE TABLE debts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('we_owe','owed_to_us') NOT NULL DEFAULT 'we_owe',
    party_name VARCHAR(150) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) DEFAULT 0.00,
    due_date DATE,
    status ENUM('pending','partial','paid','overdue') DEFAULT 'pending',
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Default Data ──────────────────────────────────────────

INSERT INTO counters (name, value) VALUES ('barcode_seq', 0);

-- Default password = "password" for all users
INSERT INTO users (username, password_hash, full_name_ar, full_name_en, role) VALUES
('admin',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام',  'System Admin',  'admin'),
('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير المتجر',  'Store Manager', 'manager'),
('cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'أمين الصندوق', 'Cashier',       'cashier');

INSERT INTO categories (name_ar, name_en, color) VALUES
('هدايا منزلية',  'Home Gifts',      '#E03A1E'),
('ديكور',         'Decor',           '#c0392b'),
('إكسسوارات',    'Accessories',      '#e67e22'),
('عطور',          'Fragrances',      '#8e44ad'),
('مستلزمات طعام','Kitchen & Dining', '#27ae60');

INSERT INTO suppliers (name_ar, name_en, phone) VALUES
('المورد العام',  'General Supplier', '+964-750-000-0001'),
('مستوردات هدايا','Gift Imports',     '+964-750-000-0002');

INSERT INTO settings (key_name, value) VALUES
('store_name_ar',    'بهنگین کریستال'),
('store_name_en',    'Bangeen Crystal'),
('store_address_ar', 'العراق، دهوك'),
('store_address_en', 'Duhok, Iraq'),
('store_phone',      '+964-750-000-0000'),
('currency',         'IQD'),
('currency_symbol',  'IQD'),
('tax_rate',         '0'),
('receipt_footer_ar','شكراً لزيارتكم — بهنگین کریستال'),
('receipt_footer_en','Thank you — Bangeen Crystal'),
('low_stock_threshold','10'),
('default_lang',     'ar');

INSERT INTO permissions (role, module, can_view, can_add, can_edit, can_delete) VALUES
('manager','dashboard', 1,1,1,1),
('manager','pos',       1,1,1,1),
('manager','products',  1,1,1,1),
('manager','categories',1,1,1,1),
('manager','suppliers', 1,1,1,1),
('manager','stock',     1,1,1,1),
('manager','sales',     1,1,1,0),
('manager','reports',   1,0,0,0),
('manager','finance',   1,1,1,1),
('manager','users',     0,0,0,0),
('manager','settings',  0,0,0,0),
('manager','backup',    0,0,0,0),
('cashier','dashboard', 1,0,0,0),
('cashier','pos',       1,1,0,0),
('cashier','products',  1,0,0,0),
('cashier','categories',0,0,0,0),
('cashier','suppliers', 0,0,0,0),
('cashier','stock',     1,0,0,0),
('cashier','sales',     1,0,0,0),
('cashier','reports',   0,0,0,0),
('cashier','finance',   0,0,0,0),
('cashier','users',     0,0,0,0),
('cashier','settings',  0,0,0,0),
('cashier','backup',    0,0,0,0);

SELECT 'Database setup complete!' AS Status;