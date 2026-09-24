ALTER TABLE `invoices`
  MODIFY COLUMN `payment_status` ENUM('debt','unpaid','partial','paid') NOT NULL DEFAULT 'debt',
  MODIFY COLUMN `status` ENUM('active','draft','unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'active',
  ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`,
  ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`,
  ADD KEY `idx_invoices_company_status_date` (`company_id`, `status`, `invoice_date`),
  ADD KEY `idx_invoices_company_deleted` (`company_id`, `deleted_at`),
  ADD CONSTRAINT `fk_invoices_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

UPDATE `invoices`
SET `payment_status` = CASE
    WHEN `payment_status` = 'debt' THEN 'unpaid'
    ELSE `payment_status`
END;

UPDATE `invoices`
SET `status` = CASE
    WHEN `status` = 'cancelled' THEN 'cancelled'
    WHEN `payment_status` = 'paid' THEN 'paid'
    WHEN `payment_status` = 'partial' THEN 'partial'
    ELSE 'unpaid'
END
WHERE `deleted_at` IS NULL;

ALTER TABLE `invoices`
  MODIFY COLUMN `payment_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  MODIFY COLUMN `status` ENUM('draft','unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'draft';

ALTER TABLE `payments`
  MODIFY COLUMN `payment_method` ENUM('cash','card','bank','transfer','other') NOT NULL DEFAULT 'cash';

ALTER TABLE `invoice_items`
  ADD COLUMN `discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`;
