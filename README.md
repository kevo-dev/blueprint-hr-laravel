# BluePrint HR — Laravel 12 port

This directory is the Laravel 12 port of the cloned [blueprint-hr](https://github.com/kateglabs-hub/blueprint-hr) HR platform. The original application is a React, Express, tRPC, and Drizzle implementation. This port preserves its core HR workflows behind a conventional Laravel application with a stateful Sanctum SPA, a Bootstrap 5/Vue frontend, Eloquent models, migrations, policy-style role middleware, DomPDF payslips, and Laravel Excel exports.

## Preferred stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.3, Composer |
| Backend | Laravel 12, Eloquent ORM, Form Requests, service layer |
| Database | MySQL 8 in production; SQLite is available for local smoke tests |
| Frontend | Vue 3, Vite, Bootstrap 5, Bootstrap Icons, Axios |
| Authentication | Laravel Sanctum stateful SPA authentication and session cookies |
| Reports | `barryvdh/laravel-dompdf`, `maatwebsite/excel` |
| Server | Ubuntu Server, PHP-FPM, Nginx, Supervisor for queue workers |

## Implemented port scope

The current port provides a production-oriented foundation and working vertical slices for multi-tenant organization management, role-aware authentication, employee master data, Kenyan payroll calculations, leave requests and approvals, employee self-service data, tenant-scoped audit logs, employee Excel exports, payslip PDF downloads, seeded statutory configuration, and a responsive Bootstrap/Vue dashboard.

The source repository contains additional domain areas such as attendance, recruitment, performance, assets, fleet, SACCO, insurance, branding, compliance, integrations, custom reports, and archival. The migration map in `../blueprint-hr/analysis/laravel_port_plan.md` records how those modules should be completed as the next bounded Laravel feature slices. The current database and service boundaries intentionally make those future modules additive rather than requiring a rewrite of the delivered foundation.

## Local setup

```bash
cp .env.example .env
php artisan key:generate

# Configure MySQL 8 in .env for normal development.
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=blueprint_hr
# DB_USERNAME=blueprint_hr
# DB_PASSWORD=change-this-password

composer install
npm install
php artisan migrate --seed
npm run build
php artisan serve
```

For a quick isolated smoke test, use SQLite:

```bash
touch database/database.sqlite
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan migrate:fresh --seed
php artisan test
```

The seeder creates a demo tenant, organization structure, Kenyan statutory rates, leave balances, an open payroll period, and development users. Set `BLUEPRINT_ADMIN_EMAIL` and `BLUEPRINT_DEMO_PASSWORD` before seeding to control the seeded accounts. The seeder intentionally refuses to run without an explicit demo secret; **use a strong local value and never reuse it outside development**.

## Authentication and API

The Vue client uses Sanctum’s stateful SPA flow. It first requests `/sanctum/csrf-cookie`, then posts credentials to `/api/auth/login`; Axios is configured to send the XSRF token and session credentials. The authenticated API is tenant-scoped through `EnsureTenantContext` and role-gated through `RoleMiddleware`. Resource-level decisions are enforced again through Laravel policies registered in `AppServiceProvider`: employees, leave requests, payroll periods, payroll transactions, organization mutations, employee exports, and payslip downloads all apply tenant ownership and role/employee ownership rules at the controller boundary. Tenant-owned models expose a reusable `forTenant()` Eloquent scope through `App\Models\Concerns\BelongsToTenant`; controllers and exports use this scope instead of relying on scattered raw `where('tenant_id', ...)` predicates.

Important endpoints include:

| Endpoint | Purpose |
|---|---|
| `POST /api/auth/login` | Sign in with the Sanctum session flow |
| `GET /api/auth/me` | Return the authenticated user and tenant context |
| `GET /api/dashboard` | Tenant metrics and recent workforce data |
| `GET/POST/PUT/DELETE /api/employees` | Employee master data CRUD |
| `GET /api/organization` | Tenant-scoped branches, departments, grades, and types |
| `GET /api/leave` | Leave types, balances, and requests |
| `POST /api/leave/requests` | Submit a leave request |
| `POST /api/leave/requests/{id}/decision` | Approve, reject, or cancel a pending request |
| `GET /api/payroll` | Payroll periods and transactions |
| `POST /api/payroll/process` | Calculate PAYE, NSSF, SHIF, housing levy, and net pay |
| `GET /api/reports/employees.xlsx` | Download tenant-scoped employee data |
| `GET /api/reports/payslips/{transaction}.pdf` | Download an authorized payslip |
| `GET /api/audit-logs` | Review tenant-scoped audit events |

## Payroll implementation notes

Payroll is configuration-driven rather than hard-coding all statutory values in controllers. Tenant-specific tax brackets, reliefs, NSSF tiers, SHIF rates, and housing levy rates are stored in database tables and consumed by `PayrollCalculationService`. A payroll process is wrapped in a transaction, locks the period, writes per-employee transactions, creates a payroll run, and records an audit event. Statutory rates should be reviewed and updated by an authorized payroll administrator whenever Kenyan regulations change.

## Testing and quality checks

The repository includes feature tests covering Sanctum login and dashboard access, cross-tenant reference rejection, leave submission and approval side effects, payroll statutory deductions, Excel export, and DomPDF payslips. `tests/Feature/RbacAuthorizationTest.php` covers route-level authorization, employee self-service isolation, tenant concealment, report ownership, and privilege-escalation attempts. `tests/Unit/AuthorizationPolicyTest.php` tests policy decisions directly. GitHub Actions in `.github/workflows/ci.yml` runs PHP linting, Composer audit, MySQL migrations, the complete PHPUnit suite, the Vite build, and the production NPM audit on pushes and pull requests.

```bash
php artisan test
find app database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l
npm run build
```

## Ubuntu Server and Nginx deployment

The production document root must be the Laravel `public` directory, never the repository root. A sample Nginx server block is provided at `deploy/nginx/blueprint-hr.conf`. A basic Supervisor worker configuration is provided at `deploy/supervisor/blueprint-hr-worker.conf`, and a Redis-oriented multi-pool configuration for critical, email, and report work is provided at `deploy/supervisor/blueprint-hr-workers.conf`. The full Redis, queue, notification, and worker reliability guide is in `docs/queues-redis-supervisor.md`.

A typical deployment sequence is:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx
```

Use a managed or hardened MySQL 8 instance, TLS at the Nginx boundary, environment secrets outside source control, a scheduled `php artisan schedule:run` entry, queue supervision, database backups, and log rotation. Do not commit `.env`, production credentials, generated reports, or user-uploaded documents.

## Source analysis artifacts

The original repository remains at `../blueprint-hr`. Its analysis artifacts are in `../blueprint-hr/analysis/`, including the repository inventory, schema and router extracts, and the detailed Laravel feature map. The port implementation is intentionally kept in this separate directory so the original source remains available for comparison and incremental migration.
