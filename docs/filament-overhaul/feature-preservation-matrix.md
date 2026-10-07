# Filament Overhaul — Feature Preservation Matrix

**Audit basis:** current `main` branch at the start of the overhaul. This document is intentionally implementation-neutral: it records what exists and what must be preserved before application code changes begin.

## 1. Runtime and architecture inventory

| Area | Current state | Preservation requirement |
|---|---|---|
| Framework | Laravel 12, PHP ^8.3 | Keep Laravel 12/PHP 8.3; no framework major upgrade |
| Authentication | Laravel web guard + Sanctum bearer/stateful SPA flow | Preserve login, logout, /me, tokens, sessions, CSRF behavior and role boundaries |
| UI | Vue 3 + Vite + Bootstrap 5 + Bootstrap Icons, mounted from `resources/views/app.blade.php` | Keep legacy SPA operational until each replacement path is proven |
| API | Laravel JSON API under `/api` | Preserve routes, response contracts and authorization |
| Tenancy | `tenant_id`, `BelongsToTenant` scope, tenant middleware | Preserve tenant isolation on every Filament query/action |
| Authorization | Role enum, role middleware, Laravel policies/gates | Reuse policy decisions; never rely on UI visibility as authorization |
| Database | Eloquent + migrations; production can use PostgreSQL/Supabase and CI uses MySQL | Preserve tables/columns/relationships; keep migrations additive |
| Payroll | `PayrollCalculationService`, statutory configuration tables | Preserve calculation rules and transaction semantics |
| Reports | Laravel Excel employee export; DomPDF payslips | Preserve formats, authorization and URL contracts |
| Audit | `AuditLog` + `AuditService` | Preserve existing audit records and sensitive workflow logging |
| Queue/cache | Laravel-configured drivers; current deployment uses sync/file | Do not introduce infrastructure dependencies without explicit configuration |
| Frontend build | Vite 7 + Vue plugin | Keep existing build green while Filament panel is added |
| React | No React dependency/components were found in the audited Laravel port | Do not add React merely to satisfy the brief; re-check repository inventory before any migration |

## 2. Existing domain and workflow matrix

| Feature | Current implementation | Current user flow | Proposed Filament implementation | Files to modify/create | Regression risks | Tests required |
|---|---|---|---|---|---|---|
| Authentication | `AuthController`, `LoginRequest`, Sanctum config, `User`, Vue login | User opens SPA → enters email/password → POST /api/auth/login → bearer token stored → loads tenant/dashboard | Filament authentication page/panel auth, while retaining existing API login route and token flow | New panel provider/auth pages; preserve existing auth files | Breaking API login, CSRF/session assumptions, seeded users | Existing auth tests + Filament login smoke + API login contract |
| Multi-tenant isolation | `Tenant`, `BelongsToTenant`, `EnsureTenantContext`, tenant-aware models/controllers | Authenticated request establishes tenant; queries/actions are scoped | Filament panel access and resources use tenant-scoped query builders/policies; no cross-tenant record discovery | Panel provider, resource base conventions, tenant-aware resource queries | Data leakage through relation managers, global search, widgets, exports | Cross-tenant authorization tests for every resource/action |
| Role/permission boundaries | `Role`, `RoleMiddleware`, policies and gates | API middleware + policy checks decide access | Filament navigation/action/field visibility mirrors policy, with server-side policy checks retained | Resource `can*` methods/policies; navigation rules | UI hidden but endpoint still callable, or role loses access | Existing RBAC suite + resource action authorization tests |
| HR dashboard | `DashboardController`, Vue dashboard | /api/dashboard returns tenant metrics, employees, payroll periods, audit data; Vue renders KPI cards | Filament dashboard widgets for headcount, payroll, branches, departments, pending leave and recent activity; drill-down links | Dashboard page/widgets | Metric scope changes; employee self-service overexposure | Snapshot/feature tests for each role and tenant |
| Employee master data | `EmployeeController`, `Employee`, request validation, Vue employee table/modal | HR roles create/update/delete; all authorized users can list; employees see only themselves | `EmployeeResource` with searchable/filterable table, responsive form, infolist/profile, relation managers | New Filament EmployeeResource/pages/relations | Missing fields, altered validation, exposure of hidden statutory/bank fields | CRUD + self-service + tenant + sensitive-field authorization tests |
| Employee profile/ESS | `User.employee`, Employee relations, Vue ESS area | Employee sees own profile/leave/payroll/payslip-related data | Filament employee-facing profile/infolist/page; preserve legacy API and self-scoped behavior | Resource/page/infolist | Employees gaining peer access | Dedicated employee-role feature tests |
| Organization structure | `OrganizationController`; Branch, Department, Designation, Grade, EmploymentType models | GET organization lists all tenant structure; HR roles can create branch/department | Filament resources/relations for Branch, Department, Designation, Grade and EmploymentType; policy-aware mutations | Resources + relation managers | Cross-tenant foreign keys, changing reference semantics | CRUD/reference integrity + cross-tenant tests |
| Leave types/balances | `LeaveType`, `LeaveBalance`, LeaveController | User views tenant leave types/balances; employee is self-scoped | Filament tables/infolists and relation managers; balance indicators and filters | Resources/pages/relations | Balance leakage or accidental mutation | Balance visibility + calculation tests |
| Leave requests/approval | `LeaveRequest`, `LeaveController`, `LeaveRequestPolicy`, audit service | Employee/authorized user submits → HR approves/rejects/cancels → balance updated transactionally | Filament resource/table with status badges, approval actions, comments, infolist, timeline | Resource/actions/infolist | Double approval, race conditions, changed status rules | Existing workflow tests + concurrency/status/balance tests |
| Payroll periods | `PayrollPeriod`, policy, PayrollController | Payroll roles create/open/process periods | Filament resource with status-aware actions and locked/processed states | Resource/actions | Processing already processed/locked periods | Authorization + state-transition tests |
| Payroll calculation | `PayrollCalculationService`, PayrollTransaction, PayrollRun, statutory models | Payroll manager processes an open period; service locks period, calculates deductions, writes transactions/run | Filament action/wizard invokes the existing service; UI must not duplicate calculation rules | Resource/action; no business-rule duplication | Tax/deduction drift, partial writes | Existing statutory tests + transaction/idempotency tests |
| Kenyan statutory configuration | TaxBracket, TaxRelief, NssfRate, ShifRate, HousingLevyRate + seeder | Seeder supplies configuration; payroll service consumes tenant-specific values | Filament configuration resources with effective dates, validation and authorization; history visible read-only where appropriate | Resources/forms/infolists | Incorrect rates or effective-date selection | Boundary/rate/effective-date tests |
| Payroll transactions | `PayrollTransaction`, policy, Vue table | Transactions displayed; authorized users/employee can access according to policy | Filament table + infolist; sensitive deductions hidden/authorized; payslip action | Resource/pages/actions | Payroll privacy breach | Role/tenant/payslip tests |
| Payroll runs | `PayrollRun` | Run created as part of processing; currently primarily backend data | Read-only Filament resource/infolist with drill-down to period/transactions | Resource/infolist | Misrepresenting run status/totals | Totals/status consistency tests |
| Employee Excel export | `ReportController`, Laravel Excel | HR roles click export endpoint | Filament header/action export, preserving existing endpoint for compatibility | Export action/class | Unauthorized bulk disclosure | Existing export tests + permission tests |
| Payslip PDF | `ReportController`, DomPDF, PayrollTransactionPolicy | User downloads authorized transaction PDF | Filament row/action download; preserve existing URL/format | Action/resource integration | Wrong employee's payslip | Existing payslip authorization/PDF tests |
| Audit trail | `AuditLog`, `AuditService`, endpoint/controller | HR roles view tenant audit logs; sensitive mutations record audit events | Filament read-only table/infolist with filters, actor/entity/status and before/after viewer | Audit resource/page | Audit records altered or hidden | Audit creation + read authorization tests |
| Legacy Vue/Blade application | `resources/js/app.js`, `HRDashboard.vue`, `resources/views/app.blade.php`, CSS/Vite | Single Vue SPA provides login and all current UI | Keep intact initially; route users to Filament only after equivalent resource paths are tested; restyle legacy UI later if retained | New panel routes/assets; legacy files remain | Broken Vite build, API behavior, duplicate navigation | Build + legacy API/UI smoke tests |
| API compatibility | `routes/api.php` and controllers | Existing external/internal clients can call documented endpoints | Filament calls application/domain services where possible; existing API remains first-class | No route deletion; shared services as needed | Contract drift | Route/JSON contract regression suite |
| Deployment/CI | Dockerfile, entrypoint, GitHub Actions | Docker builds Vite/PHP, entrypoint migrates/seeds/caches; CI tests and builds | Add Filament build/install checks without changing production DB behavior | Composer lock, CI, Docker only after approval | Build failures, accidental seeding | CI + container build + migration smoke |

## 3. Existing authorization matrix

| Role | Employee records | Organization | Leave approval | Payroll processing | Employee export | Audit logs | Self-service |
|---|---|---|---|---|---|---|---|
| Super Admin | Manage | Manage | Approve | Process | Export | View | Own/tenant according to policy |
| Company Admin | Manage | Manage | Approve | Process | Export | View | Own/tenant according to policy |
| HR Manager | Manage | Manage | Approve | No | Export | View | Own/tenant according to policy |
| Payroll Manager | No employee management | No organization mutation | No | Process | No | No | Payroll access according to policy |
| Employee | Own record only | No mutation | Submit own | No | No | No | Own record/data only |

The exact policy methods remain authoritative. This table is a planning summary, not a replacement for policy logic.

## 4. Inventory notes and gaps

- The Laravel port currently contains Vue, not React. The repository's `package.json` has Vue and Vite plugins and no React packages.
- The README explicitly describes attendance, recruitment, performance, assets, fleet, SACCO, insurance, branding, compliance, integrations, custom reports and archival as source-repository areas not yet delivered in this Laravel port. They are **not** treated as existing implemented features and must not be fabricated during this overhaul.
- The employee model already contains statutory identifiers and banking fields, but the current Vue create form exposes only a subset. The Filament employee form must preserve every existing model field and can add the broader staff import capability later without removing the API contract.
- The current payroll service references configuration-driven statutory tables. No payroll rule should be copied into Filament configuration code.
- The current Docker entrypoint runs migrations and seeders automatically. This is a deployment risk to review separately before production hardening; it is not changed in the audit phase.

## 5. Verification gate before implementation

Before Phase 1 application changes, the following must be verified from the complete repository contents:

1. Every migration filename/table/column/foreign key.
2. Every controller, request, policy, model, service, command, job, event, listener and notification.
3. Every Blade file and frontend component.
4. Every test file and its assertions.
5. Every config file that affects authentication, sessions, storage, queues, mail and database.
6. Complete route list and route middleware.
7. Current production/deployment assumptions.

No legacy application artifact will be deleted as part of the audit.
