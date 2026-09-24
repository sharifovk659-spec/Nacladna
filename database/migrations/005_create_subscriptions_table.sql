CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `plan` ENUM('trial','business') NOT NULL DEFAULT 'trial',
  `trial_start` DATETIME NULL,
  `trial_end` DATETIME NULL,
  `starts_at` DATETIME NULL,
  `ends_at` DATETIME NULL,
  `status` ENUM('trial','active','expired','cancelled') NOT NULL DEFAULT 'trial',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_company_id` (`company_id`),
  CONSTRAINT `fk_sub_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
