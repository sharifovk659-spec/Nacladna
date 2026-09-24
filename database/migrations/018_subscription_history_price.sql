-- Store requested tariff price on subscription history (renewal requests)
ALTER TABLE `subscription_history`
  ADD COLUMN `price_som` DECIMAL(10,2) NULL DEFAULT NULL AFTER `period_months`;
