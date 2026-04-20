-- ============================================
-- DATABASE: Toko JK Pasar Jati — POS & Dashboard
-- ============================================
CREATE DATABASE IF NOT EXISTS toko_jk_pasar_jati CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE toko_jk_pasar_jati;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Products (parent) — includes kode_barang for unique product code
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_barang VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    sell_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Product Variants (SKU per size+color combo)
CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    size VARCHAR(10) NOT NULL,
    color VARCHAR(50) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 5,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Stock Movements
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id INT NOT NULL,
    type ENUM('in','out') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    supplier_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Transactions
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_type ENUM('percent','nominal') DEFAULT NULL,
    discount_value DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','qris','transfer') NOT NULL DEFAULT 'cash',
    payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    change_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Transaction Items
CREATE TABLE IF NOT EXISTS transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    variant_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    variant_info VARCHAR(100) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
) ENGINE=InnoDB;

-- Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL
) ENGINE=InnoDB;

-- ============================================
-- SEED DATA
-- ============================================

-- Default users (password: admin123 / kasir123)
INSERT INTO users (name, username, password, role, status) VALUES
('Dwiky Darmawan', 'admin', '$2y$10$tovtBeE.UekN1cbQGrm/YezKnFy6cz1F4M4dOWD48HxQbvP6NKiIS', 'admin', 'active'),
('Derpi', 'kasir', '$2y$10$yxDcTueW6wby3MtAfPgUZONuIEuzrm249UXWKsMD9VddTUg/uaopC', 'kasir', 'active');

-- Categories
INSERT INTO categories (name) VALUES
('Baju'), ('Celana'), ('Dress'), ('Jaket'), ('Aksesoris'), ('Tas');

-- Suppliers
INSERT INTO suppliers (name, phone, address) VALUES
('PT Garmen Indonesia', '081234567890', 'Jl. Industri No. 10, Bandung'),
('CV Textile Jaya', '082345678901', 'Jl. Tekstil No. 5, Solo'),
('UD Fashion Import', '083456789012', 'Jl. Mangga Dua No. 88, Jakarta');

-- Products (with kode_barang)
INSERT INTO products (kode_barang, name, category_id, brand, description, cost_price, sell_price) VALUES
('BRG-0001', 'Kemeja Flanel', 1, 'Urban Style', 'Kemeja flanel katun premium', 120000, 250000),
('BRG-0002', 'Kaos Polos', 1, 'BasicWear', 'Kaos cotton combed 30s', 45000, 120000),
('BRG-0003', 'Celana Chino', 2, 'ChiStyle', 'Celana chino slim fit premium', 150000, 350000),
('BRG-0004', 'Celana Jeans', 2, 'DenimCo', 'Celana jeans stretch', 180000, 380000),
('BRG-0005', 'Dress Midi', 3, 'Elegan', 'Dress midi floral', 130000, 280000),
('BRG-0006', 'Jaket Denim', 4, 'DenimCo', 'Jaket denim klasik', 200000, 450000),
('BRG-0007', 'Hoodie Polos', 4, 'BasicWear', 'Hoodie fleece premium', 100000, 280000),
('BRG-0008', 'Topi Baseball', 5, 'CapHead', 'Topi baseball bordir', 35000, 89000),
('BRG-0009', 'Tas Selempang', 6, 'BagPack', 'Tas selempang kanvas', 75000, 180000),
('BRG-0010', 'Rok Plisket', 3, 'Elegan', 'Rok plisket premium', 90000, 200000);

-- Product variants
INSERT INTO product_variants (product_id, sku, size, color, stock, min_stock) VALUES
-- Kemeja Flanel
(1, 'KMJ-FL-RED-S', 'S', 'Red', 15, 5),
(1, 'KMJ-FL-RED-M', 'M', 'Red', 20, 5),
(1, 'KMJ-FL-RED-L', 'L', 'Red', 12, 5),
(1, 'KMJ-FL-RED-XL', 'XL', 'Red', 8, 5),
(1, 'KMJ-FL-BLU-M', 'M', 'Blue', 18, 5),
(1, 'KMJ-FL-BLU-L', 'L', 'Blue', 10, 5),
-- Kaos Polos
(2, 'KAO-PL-BLK-S', 'S', 'Black', 30, 10),
(2, 'KAO-PL-BLK-M', 'M', 'Black', 25, 10),
(2, 'KAO-PL-BLK-L', 'L', 'Black', 20, 10),
(2, 'KAO-PL-WHT-M', 'M', 'White', 22, 10),
(2, 'KAO-PL-WHT-L', 'L', 'White', 3, 10),
-- Celana Chino
(3, 'CLN-CH-BLK-30', '30', 'Black', 10, 5),
(3, 'CLN-CH-BLK-32', '32', 'Black', 14, 5),
(3, 'CLN-CH-CRM-32', '32', 'Cream', 8, 5),
(3, 'CLN-CH-CRM-34', '34', 'Cream', 2, 5),
-- Celana Jeans
(4, 'CLN-JN-BLU-30', '30', 'Blue', 12, 5),
(4, 'CLN-JN-BLU-32', '32', 'Blue', 15, 5),
(4, 'CLN-JN-BLK-32', '32', 'Black', 9, 5),
-- Dress Midi
(5, 'DRS-MD-PNK-S', 'S', 'Pink', 7, 3),
(5, 'DRS-MD-PNK-M', 'M', 'Pink', 10, 3),
(5, 'DRS-MD-WHT-M', 'M', 'White', 5, 3),
-- Jaket Denim
(6, 'JKT-DN-BLU-M', 'M', 'Blue', 6, 3),
(6, 'JKT-DN-BLU-L', 'L', 'Blue', 8, 3),
(6, 'JKT-DN-BLU-XL', 'XL', 'Blue', 4, 3),
-- Hoodie
(7, 'HDI-PL-BLK-M', 'M', 'Black', 12, 5),
(7, 'HDI-PL-BLK-L', 'L', 'Black', 10, 5),
(7, 'HDI-PL-GRY-L', 'L', 'Grey', 1, 5),
-- Topi
(8, 'TOP-BB-BLK-ALL', 'All Size', 'Black', 20, 5),
(8, 'TOP-BB-WHT-ALL', 'All Size', 'White', 15, 5),
-- Tas
(9, 'TAS-SL-BLK-ALL', 'All Size', 'Black', 8, 3),
(9, 'TAS-SL-BRN-ALL', 'All Size', 'Brown', 6, 3),
-- Rok
(10, 'ROK-PL-BLK-S', 'S', 'Black', 9, 3),
(10, 'ROK-PL-BLK-M', 'M', 'Black', 11, 3),
(10, 'ROK-PL-CRM-M', 'M', 'Cream', 4, 3);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('store_name', 'Toko JK Pasar Jati'),
('store_address', 'Pasar Jati, Bandung'),
('store_phone', '021-12345678'),
('receipt_footer', 'Terima kasih telah berbelanja!'),
('default_min_stock', '5');

-- Sample transactions
INSERT INTO transactions (invoice_number, user_id, subtotal, discount_type, discount_value, discount_amount, total, payment_method, payment_amount, change_amount, created_at) VALUES
('TRX-20260419-001', 2, 500000, 'percent', 10, 50000, 450000, 'cash', 500000, 50000, '2026-04-19 10:30:00'),
('TRX-20260419-002', 2, 350000, NULL, 0, 0, 350000, 'qris', 350000, 0, '2026-04-19 11:15:00'),
('TRX-20260419-003', 2, 730000, 'nominal', 30000, 30000, 700000, 'transfer', 700000, 0, '2026-04-19 13:20:00'),
('TRX-20260418-001', 2, 250000, NULL, 0, 0, 250000, 'cash', 300000, 50000, '2026-04-18 09:45:00'),
('TRX-20260418-002', 2, 480000, 'percent', 5, 24000, 456000, 'cash', 500000, 44000, '2026-04-18 14:20:00'),
('TRX-20260417-001', 2, 620000, NULL, 0, 0, 620000, 'qris', 620000, 0, '2026-04-17 10:00:00');

INSERT INTO transaction_items (transaction_id, variant_id, product_name, variant_info, price, cost_price, quantity, subtotal) VALUES
(1, 2, 'Kemeja Flanel', 'Red - M', 250000, 120000, 2, 500000),
(2, 12, 'Celana Chino', 'Black - 30', 350000, 150000, 1, 350000),
(3, 8, 'Kaos Polos', 'Black - M', 120000, 45000, 1, 120000),
(3, 22, 'Jaket Denim', 'Blue - M', 450000, 200000, 1, 450000),
(3, 20, 'Dress Midi', 'Pink - M', 280000, 130000, 1, 280000),
(4, 10, 'Kaos Polos', 'White - M', 120000, 45000, 1, 120000),
(4, 28, 'Topi Baseball', 'Black - All Size', 89000, 35000, 1, 89000),
(5, 3, 'Kemeja Flanel', 'Red - L', 250000, 120000, 1, 250000),
(5, 17, 'Celana Jeans', 'Blue - 32', 380000, 180000, 1, 380000),
(6, 25, 'Hoodie Polos', 'Black - M', 280000, 100000, 1, 280000),
(6, 13, 'Celana Chino', 'Black - 32', 350000, 150000, 1, 350000);
