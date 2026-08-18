# RBAC and Multi-Tenancy Test Suite

## Purpose

`tests/Feature/RbacAuthorizationTest.php` is a PHPUnit feature suite for the BluePrint HR Laravel port. It treats authorization as an API security boundary: a request must be authenticated, assigned an allowed role, associated with the correct tenant, and—where applicable—limited to the authenticated employee’s own records.

The suite uses Laravel’s `RefreshDatabase` trait and the project’s SQLite test environment. Each test creates its own tenant, organization records, employees, users, leave data, and payroll fixtures. No development credentials or production data are used.

## Coverage matrix

| Area | Covered behavior | Expected result |
|---|---|---|
| Authentication | Anonymous access to dashboard, employees, payroll, and audit routes | `401 Unauthorized` |
| Role denial | Employee cannot create employees, branches, or access audit logs | `403 Forbidden` |
| Role separation | Payroll manager cannot manage employees; HR manager cannot process payroll; employee cannot approve leave | `403 Forbidden` |
| People management | Company admin can create an employee within the active tenant | `201 Created` and tenant match |
| Employee self-service | Employee list and detail endpoints expose only the authenticated employee | One record; foreign record forbidden |
| Dashboard isolation | Employee dashboard headcount and employee collection are self-scoped | Only one employee |
| Leave self-service | Employee cannot use a submitted foreign employee ID to create a request | Created request belongs to authenticated employee |
| Tenant validation | Employee organization references cannot point to another tenant | `422 Unprocessable Entity`; no row created |
| Tenant validation | Leave type references cannot point to another tenant | `422 Unprocessable Entity`; no row created |
| Payslip ownership | Employee can download their own payslip but not another employee’s | `200` for owner; `403` for peer |
| Payslip tenancy | Tenant A cannot download Tenant B’s payslip | `404 Not Found` |
| Payroll tenancy | A payroll manager cannot process a payroll period from another tenant | `404 Not Found` |
| Sensitive reports | Employee cannot export all employee records or view audit logs | `403 Forbidden` |
| Privilege escalation | Untrusted mass-assignment cannot change `role`, `tenant_id`, or `employee_id` | Protected values remain unchanged |

## Running the suite

Run only the security suite during development:

```bash
cd /var/www/blueprint-hr
php artisan test --filter=RbacAuthorizationTest
```

Run all application tests before merging:

```bash
php artisan test
```

Run with an explicit test environment and compact output in CI:

```bash
APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=:memory: \
php artisan test --no-coverage
```

For coverage reporting, install the PHP coverage driver on the CI runner and execute:

```bash
php artisan test --coverage --min=80
```

The `--min=80` threshold should be introduced after the CI environment consistently includes the required coverage extension. Feature authorization tests should remain mandatory even if the global application coverage target changes.

## CI requirements

The CI pipeline should run the following stages in order:

```bash
composer validate --strict
composer audit --no-interaction
php -l app/Models/User.php
php artisan config:clear
php artisan migrate:fresh --env=testing
php artisan test --no-interaction
npm ci
npm run build
npm audit --omit=dev
```

The test database must be disposable. CI must not connect to a staging or production MySQL instance for this suite. If MySQL-specific behavior is required, add a separate service-container job and keep the SQLite feature job as the fast mandatory gate.

## Interpreting failures

A `401` failure usually indicates that the route is missing authentication middleware or that the test client is using an unexpected guard. A `403` failure generally means the role middleware or Form Request authorization is missing or has changed. A `404` failure on a foreign tenant object is intentional for endpoints that conceal object existence; a `422` failure is expected for tenant-scoped request validation such as employee and leave-type references.

A test that unexpectedly receives `200` is a security regression and must not be fixed by weakening the assertion. Inspect the route middleware, Form Request `authorize()` method, tenant-scoped query, model policy, and API Resource fields before changing the test.

## Test extension rules

Every new tenant-owned endpoint should receive at least three tests: an allowed same-tenant action, a denied role action, and a denied cross-tenant action. Every employee self-service endpoint should add both an own-record success case and a peer-record denial case. Report endpoints should test access by role, tenant, and ownership, and should verify that sensitive fields are absent from JSON responses.

When an endpoint changes from controller-local authorization to a policy, retain the behavioral assertions and move the implementation check to the policy. When role names or permissions change, update the test matrix and the enum/permission contract together so authorization drift is visible in review.
