# GitHub Actions deploy

Repository: `https://github.com/sharifovk659-spec/Nacladna`

## Branches

- **GitHub default:** `main`
- **Workflow triggers:** push to `main` or `master`, or manual `workflow_dispatch`

## Required repository secrets

| Secret | Example / notes |
|--------|------------------|
| `SSH_HOST` | `45.84.204.68` |
| `SSH_PORT` | `65002` |
| `SSH_USER` | `u417315406` |
| `SSH_PRIVATE_KEY` | Full private key (PEM), deploy key on server |
| `APP_DIR` | `/home/u417315406/apps/nakladna` |
| `PUBLIC_DIR` | `/home/u417315406/domains/inovaauto.com/public_html/nakladna` |
| `APP_URL` | `https://nakladna.inovaauto.com` |
| `DB_HOST` | `localhost` (on server) |
| `DB_PORT` | `3306` |
| `DB_NAME` | Production database name |
| `DB_USER` | Production DB user |
| `DB_PASS` | Production DB password |

Optional: `BACKUP_ROOT` (defaults to `/home/<SSH_USER>/backups/nakladna`)

## Set secrets (maintainer machine)

```powershell
$env:NAK_DB_PASS = '<production-db-password>'
powershell -File bin/set-github-secrets.ps1
```

Requires: `gh auth login`, deploy key at `%USERPROFILE%\.ssh\nakladna_deploy`

## CI pipeline

1. PHP syntax lint (all app PHP files)
2. PHPUnit **CI** suite (`TelegramAuthTest`, `MockLoginGuardTest`) — **must pass** (no `|| true`)
3. Deploy job: backup → extract → composer → migrate → health → `smoke_mvp` + `smoke_invoice`

Production `.env`, uploads, PDFs, and logs are preserved on each deploy.
