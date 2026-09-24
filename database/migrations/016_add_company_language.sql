ALTER TABLE companies
  ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'ru' AFTER timezone;
