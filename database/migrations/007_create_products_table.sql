CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(100) NULL,
  `barcode` VARCHAR(100) NULL,
  `unit` ENUM('шт','кг','м','кор') NOT NULL DEFAULT 'шт',
  `purchase_price` DECIMAL(15,2) NULL,
  `sale_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_products_company_name`    (`company_id`, `name`),
  KEY `idx_products_company_barcode` (`company_id`, `barcode`),
  CONSTRAINT `fk_products_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
