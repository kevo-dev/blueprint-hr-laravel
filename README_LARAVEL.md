# BluePrint HR Laravel Port

This directory is the Laravel 12 port of the cloned BluePrint HR repository. It uses PHP 8.3, MySQL 8, Bootstrap 5, Vue 3, Laravel Sanctum, DomPDF, Laravel Excel 3.1, Ubuntu Server, PHP-FPM, and Nginx.

## Local setup

Copy `.env.example` to `.env`, set `APP_KEY`, configure MySQL, and run:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For a quick local smoke test without MySQL, set `DB_CONNECTION=sqlite` and ensure `database/database.sqlite` exists. The preferred production database remains MySQL 8.

The seeded administrator email is controlled by `BLUEPRINT_ADMIN_EMAIL`, and the password is controlled by `BLUEPRINT_ADMIN_PASSWORD`. The development fallback password is `ChangeMe!2026`; this is intentionally marked for change on first sign-in and must never be used in production. Use a secret manager or deployment environment variable for production credentials.

## API and security

The Vue frontend uses same-origin cookie authentication through Sanctum. API routes are protected by `auth:sanctum` and tenant context middleware. Role middleware and policies must be extended as new enterprise modules are added. All employee, leave, payroll, and report queries are tenant-scoped.

Do not expose `.env`, `storage`, or source files through Nginx. Serve only the `public` directory. Use HTTPS, secure session cookies, a least-privileged MySQL user, queue workers under Supervisor, and the scheduler cron in `deploy/cron.txt`.

## Current port coverage

The implemented core covers authentication, dashboard metrics, tenant and organization structure, employee master, employee self-service, leave requests and approvals, leave balance deduction, payroll periods, configuration-driven PAYE/NSSF/SHIF/housing levy calculations, payroll transactions, payslip PDF downloads, employee Excel export, and audit history. The original repository’s enterprise integrations are intentionally left behind service boundaries for later gateway-specific adapters; no fake live KRA, M-Pesa, or statutory portal submission is claimed.
