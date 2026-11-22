# Rozklad

Class and internship scheduling system for VSP "OTFK ONTU" — `rozklad.otfk.od.ua`.
Requirements (Ukrainian): [docs/requirements.md](docs/requirements.md).

## Stack

- Laravel 12, Filament 4 (admin panel at `/admin`)
- PHP 8.3 (pinned via `config.platform.php` — matches the Plesk hosting)
- MariaDB in production, SQLite for local development
- Frontend assets are built in CI (hosting Node is outdated)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
composer run dev        # server + queue + logs + vite
```

Create an admin user: `php artisan make:filament-user`.

## PhpStorm

Shared run configurations live in `.idea/runConfigurations`:
**Dev**, **Serve**, **Tests**, **Migrate fresh + seed**, **Vite build**.
