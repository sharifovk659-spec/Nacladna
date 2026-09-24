ALTER TABLE subscriptions
  ADD COLUMN period_months TINYINT UNSIGNED NULL DEFAULT NULL AFTER plan;

CREATE TABLE IF NOT EXISTS subscription_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    plan VARCHAR(32) NOT NULL,
    period_months TINYINT UNSIGNED NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status VARCHAR(32) NOT NULL,
    actor_type ENUM('system', 'admin', 'user') NOT NULL DEFAULT 'system',
    actor_id INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sub_history_company (company_id),
    KEY idx_sub_history_created (created_at),
    CONSTRAINT fk_sub_history_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
