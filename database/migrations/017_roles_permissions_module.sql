-- Roles & permissions module

UPDATE `company_users` SET `role` = 'cashier' WHERE `role` = 'member';

ALTER TABLE `company_users`
  MODIFY COLUMN `role` ENUM('owner','admin','manager','cashier','accountant') NOT NULL DEFAULT 'cashier';

UPDATE `company_users` SET `role` = 'cashier' WHERE `role` NOT IN ('owner','admin','manager','cashier','accountant');

ALTER TABLE `company_users`
  ADD COLUMN `display_name` VARCHAR(200) NULL AFTER `user_id`;

CREATE TABLE IF NOT EXISTS `permissions` (
  `slug` VARCHAR(64) NOT NULL PRIMARY KEY,
  `label` VARCHAR(120) NOT NULL,
  `module` VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role` ENUM('owner','admin','manager','cashier','accountant') NOT NULL,
  `permission_slug` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`role`, `permission_slug`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_slug`) REFERENCES `permissions` (`slug`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_user_permissions` (
  `company_user_id` INT UNSIGNED NOT NULL,
  `permission_slug` VARCHAR(64) NOT NULL,
  `granted` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`company_user_id`, `permission_slug`),
  CONSTRAINT `fk_cup_cu` FOREIGN KEY (`company_user_id`) REFERENCES `company_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cup_perm` FOREIGN KEY (`permission_slug`) REFERENCES `permissions` (`slug`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_invites` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `display_name` VARCHAR(200) NOT NULL,
  `role` ENUM('admin','manager','cashier','accountant') NOT NULL,
  `created_by_user_id` INT UNSIGNED NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `used_by_user_id` INT UNSIGNED NULL,
  `status` ENUM('pending','used','revoked','expired') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_employee_invite_token` (`token_hash`),
  KEY `idx_ei_company` (`company_id`),
  CONSTRAINT `fk_ei_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ei_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`slug`, `label`, `module`) VALUES
('dashboard.view', 'Просмотр главной', 'dashboard'),
('clients.view', 'Клиенты: просмотр', 'clients'),
('clients.create', 'Клиенты: создание', 'clients'),
('clients.edit', 'Клиенты: редактирование', 'clients'),
('clients.delete', 'Клиенты: удаление', 'clients'),
('products.view', 'Товары: просмотр', 'products'),
('products.create', 'Товары: создание', 'products'),
('products.edit', 'Товары: редактирование', 'products'),
('products.delete', 'Товары: удаление', 'products'),
('invoices.view', 'Накладные: просмотр', 'invoices'),
('invoices.create', 'Накладные: создание', 'invoices'),
('invoices.edit', 'Накладные: редактирование', 'invoices'),
('invoices.cancel', 'Накладные: отмена', 'invoices'),
('invoices.delete', 'Накладные: удаление', 'invoices'),
('invoices.print', 'Накладные: печать/PDF', 'invoices'),
('invoices.share', 'Накладные: публикация', 'invoices'),
('payments.view', 'Платежи: просмотр', 'payments'),
('payments.create', 'Платежи: создание', 'payments'),
('debts.view', 'Долги: просмотр', 'debts'),
('debts.manage', 'Долги: управление', 'debts'),
('reports.view', 'Отчёты', 'reports'),
('settings.view', 'Настройки: просмотр', 'settings'),
('settings.edit', 'Настройки: изменение', 'settings'),
('subscription.view', 'Подписка', 'subscription'),
('employees.view', 'Сотрудники: просмотр', 'employees'),
('employees.create', 'Сотрудники: добавление', 'employees'),
('employees.edit', 'Сотрудники: редактирование', 'employees'),
('employees.disable', 'Сотрудники: отключение', 'employees'),
('employees.permissions', 'Сотрудники: права доступа', 'employees')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- Admin: all except owner-only settings/subscription edits handled in app logic; grant broad set
INSERT IGNORE INTO `role_permissions` (`role`, `permission_slug`)
SELECT 'admin', slug FROM permissions;

INSERT IGNORE INTO `role_permissions` (`role`, `permission_slug`) VALUES
('manager', 'dashboard.view'),
('manager', 'clients.view'), ('manager', 'clients.create'), ('manager', 'clients.edit'), ('manager', 'clients.delete'),
('manager', 'products.view'), ('manager', 'products.create'), ('manager', 'products.edit'), ('manager', 'products.delete'),
('manager', 'invoices.view'), ('manager', 'invoices.create'), ('manager', 'invoices.edit'), ('manager', 'invoices.cancel'), ('manager', 'invoices.print'), ('manager', 'invoices.share'),
('manager', 'payments.view'), ('manager', 'payments.create'),
('manager', 'debts.view'), ('manager', 'debts.manage'),
('manager', 'reports.view'),
('manager', 'subscription.view'),
('cashier', 'dashboard.view'),
('cashier', 'invoices.view'), ('cashier', 'invoices.create'), ('cashier', 'invoices.print'), ('cashier', 'invoices.share'),
('cashier', 'payments.view'), ('cashier', 'payments.create'),
('cashier', 'debts.view'),
('accountant', 'dashboard.view'),
('accountant', 'invoices.view'),
('accountant', 'payments.view'),
('accountant', 'debts.view'),
('accountant', 'reports.view'),
('accountant', 'subscription.view');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_slug`)
SELECT 'owner', slug FROM permissions;
