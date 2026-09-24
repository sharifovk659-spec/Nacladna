# Nakladna Cloud MVP Foundation

Production-ready PHP foundation for `nakladna.inovaauto.com`.

## Stack

- PHP 8.2+
- PDO MySQL
- Composer
- Vanilla JavaScript
- Clean simple MVC

## Included in this foundation

- Front controller at `public/index.php`
- Simple router for `GET` and `POST`
- PDO database connection from `.env`
- Migration runner
- CSRF helper
- Validation helper
- JSON response helper
- Centralized error handling
- File logger
- Session bootstrap
- Placeholder middleware for auth, company isolation, and subscription checks
- Temporary responsive homepage
- `GET /health`
- GitHub Actions deployment workflow with backup and rollback steps

## Local setup

1. Copy `.env.example` to `.env`
2. Set existing database credentials in `.env`
3. Run:

```bash
composer install
php bin/migrate.php
php -S localhost:8000 -t public
```

4. Open:

- `http://localhost:8000/`
- `http://localhost:8000/health`

## Important rules

- Do not commit `.env`
- Do not commit secrets, uploads, logs, PDFs, or database dumps
- Do not recreate production infrastructure
- Work only inside this project directory

## Deployment

Deployment is configured in `.github/workflows/deploy.yml`.

Expected GitHub Secrets:

- `SSH_HOST`
- `SSH_PORT`
- `SSH_USER`
- `SSH_PRIVATE_KEY`
- `APP_DIR`
- `PUBLIC_DIR`
- `BACKUP_ROOT` (optional)
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_URL`
