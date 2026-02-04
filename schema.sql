CREATE DATABASE IF NOT EXISTS abhash_bids CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE abhash_bids;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS admin_logs;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS bids;
DROP TABLE IF EXISTS auctions;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS app_settings;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(10) NULL UNIQUE,
    email VARCHAR(150) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    session_token VARCHAR(128) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE app_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE auctions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    starting_price DECIMAL(12,2) NOT NULL,
    bid_increment DECIMAL(12,2) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('Scheduled','Live','Ended','Cancelled') NOT NULL DEFAULT 'Scheduled',
    current_price DECIMAL(12,2) NOT NULL,
    highest_bidder_id INT UNSIGNED NULL,
    bids_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auctions_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE,
    CONSTRAINT fk_auctions_highest_bidder FOREIGN KEY (highest_bidder_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_auctions_status_time (status, start_time, end_time),
    INDEX idx_auctions_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE bids (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id INT UNSIGNED NOT NULL,
    bidder_id INT UNSIGNED NOT NULL,
    bid_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bids_auction FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bids_bidder FOREIGN KEY (bidder_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_bids_auction (auction_id, id),
    INDEX idx_bids_bidder (bidder_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    winning_amount DECIMAL(12,2) NOT NULL,
    status ENUM('Confirmed') NOT NULL DEFAULT 'Confirmed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_auction FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_orders_auction_user (auction_id, user_id),
    INDEX idx_orders_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE admin_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_admin_logs_admin (admin_id, created_at)
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
('Electronics'),
('Vehicles'),
('Furniture'),
('Collectibles'),
('Fashion');

INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$qsgr4mk6khpvg7LK/4C.N.e8GEs.JLVP.NqU3msr75DYQ3VDlxjSq');

INSERT INTO app_settings (setting_key, setting_value) VALUES
('login_form_alignment', 'center');
