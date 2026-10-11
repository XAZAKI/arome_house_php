CREATE DATABASE IF NOT EXISTS `arome_house`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `arome_house`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`name`, `email`, `phone`, `password`, `role`)
VALUES (
  'Administrator',
  'admin@aromehouse.test',
  NULL,
  '$2y$12$OldoyajUqjtGezNlwF4uF.mdeJN8pVyQ0LzeOZLXWEnSdPRAA0GTW',
  'admin'
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = 'admin';

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `gender` ENUM('Wanita','Pria','Unisex') NOT NULL DEFAULT 'Unisex',
  `category` VARCHAR(80) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `old_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `rating` DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  `reviews` INT UNSIGNED NOT NULL DEFAULT 0,
  `volume` VARCHAR(40) DEFAULT '50 ml',
  `notes` VARCHAR(255) DEFAULT NULL,
  `description` TEXT,
  `stock` INT NOT NULL DEFAULT 0,
  `status` ENUM('available','limited','unavailable') NOT NULL DEFAULT 'available',
  `image` VARCHAR(255) DEFAULT NULL,
  `authenticity` VARCHAR(255) DEFAULT '100% Original',
  `shipping_info` VARCHAR(255) DEFAULT 'Pengiriman Cepat',
  `packaging` VARCHAR(255) DEFAULT 'Kemasan Aman',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `order_code` VARCHAR(40) NOT NULL, `user_id` INT UNSIGNED NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0, `shipping_cost` DECIMAL(12,2) NOT NULL DEFAULT 0, `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(80) NOT NULL, `shipping_method` VARCHAR(100) NOT NULL, `address` TEXT NOT NULL, `status` VARCHAR(40) NOT NULL DEFAULT 'Diproses',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `uq_order_code` (`order_code`), KEY `idx_orders_user` (`user_id`), KEY `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `order_id` INT UNSIGNED NOT NULL, `product_id` INT UNSIGNED NOT NULL, `product_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0, `quantity` INT UNSIGNED NOT NULL DEFAULT 1, `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), KEY `idx_order_items_order` (`order_id`), KEY `idx_order_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ERP MODULES
CREATE TABLE IF NOT EXISTS suppliers (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,contact_person VARCHAR(120),phone VARCHAR(30),email VARCHAR(150),address TEXT,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS purchase_orders (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,po_code VARCHAR(40) NOT NULL UNIQUE,supplier_id INT UNSIGNED,total DECIMAL(12,2) NOT NULL DEFAULT 0,status ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',notes TEXT,order_date DATE NOT NULL,expected_date DATE,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_po_supplier(supplier_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS purchase_order_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,purchase_order_id INT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL DEFAULT 1,cost DECIMAL(12,2) NOT NULL DEFAULT 0,subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,KEY idx_poi_po(purchase_order_id),KEY idx_poi_product(product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS stock_movements (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,product_id INT UNSIGNED NOT NULL,type ENUM('in','out','adjustment') NOT NULL,quantity INT NOT NULL,reference_type VARCHAR(30),reference_id INT UNSIGNED,notes VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_stock_product(product_id),KEY idx_stock_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS expenses (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,expense_date DATE NOT NULL,category VARCHAR(80) NOT NULL,description VARCHAR(255) NOT NULL,amount DECIMAL(12,2) NOT NULL DEFAULT 0,payment_method VARCHAR(80),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_expense_date(expense_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
