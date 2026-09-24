CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_method` ENUM('cash','card','transfer','other') NOT NULL DEFAULT 'cash',
  `note` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_payments_company_client` (`company_id`, `client_id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  CONSTRAINT `fk_pay_company`  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_invoice`  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_client`   FOREIGN KEY (`client_id`)  REFERENCES `clients`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_creator`  FOREIGN KEY (`created_by`) REFERENCES `users`     (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
