# BluePrint HR security and RBAC implementation guide

**Author:** Manus AI  
**Application:** BluePrint HR Laravel port  
**Target stack:** PHP 8.3, Laravel 12, MySQL 8, Vue 3, Bootstrap 5, Sanctum, DomPDF, Laravel Excel, Ubuntu Server, Nginx

## 1. Security objective and threat model

BluePrint HR contains employment records, salary data, statutory identifiers, leave decisions, payslips, audit history, and organization information. The security objective is therefore not merely to prevent anonymous access. It is to ensure that every authenticated request is authorized for the **tenant, resource, action, fields, and workflow state** involved.

> Authentication answers “who is this?” Authorization answers “may this actor perform this action on this resource in this tenant and state?”

Use the OWASP Application Security Verification Standard as the verification baseline for authentication, session management, access control, validation, data protection, and secure configuration.[1] The application should follow a **deny-by-default** model: a route or model is inaccessible unless a policy or permission explicitly allows it.

The current port has a sound starting point. It uses Sanctum stateful SPA authentication, session regeneration after login, logout invalidation, tenant middleware, an enum-backed role, route role middleware, tenant filters in several controllers, and audit logging. However, authorization is still partly controller-local and several sensitive boundaries need to be centralized before production.

## 2. Current-state audit

| Area | Current implementation | Security assessment | Required action |
|---|---|---|---|
| Authentication | `AuthController` uses `Auth::attempt`, regenerates the session, and invalidates it on logout | Good foundation; no visible login throttling, MFA, email verification, or session/device management | Add rate limiting, MFA/step-up authentication, verification, and session review |
| SPA authentication | Sanctum stateful middleware and `/sanctum/csrf-cookie` are used | Appropriate for the first-party Vue SPA; cookie and HTTPS settings remain deployment-sensitive | Use secure HTTPS cookies and strict stateful-domain configuration |
| Roles | Five enum roles: Super Admin, Company Admin, HR Manager, Payroll Manager, Employee | Clear initial model, but permission rules are hard-coded in route middleware and controllers | Keep roles as coarse gates and introduce policies plus permission constants |
| Tenancy | `EnsureTenantContext` copies the authenticated user’s `tenant_id` to the request | Good request boundary, but it does not automatically constrain every Eloquent query | Use tenant-aware query services, scoped validation, policies, and queue context |
| Object-level access | Employee and payslip checks are controller-local | High-risk: new endpoints can easily omit the check | Add model policies and authorize every resource action |
| Validation | Employee validation was hardened; leave request validation still uses global `exists` rules | A crafted foreign-tenant ID can pass request validation even if later controller logic rejects it | Make every tenant-owned foreign key tenant-scoped in its Form Request |
| Sensitive fields | Some employee and payroll data is returned directly from Eloquent models | High-risk overexposure risk, especially salary, payroll transactions, bank and statutory data | Use API Resources and role-specific field projections |
| Reports | Employee Excel and payslip PDF endpoints have basic role/tenant checks | Add report-specific policies, audit entries, rate limits, and protected delivery | Protect, log, and constrain every export |
| Frontend | Vue computes role booleans and hides some buttons | UI visibility is not authorization; it currently loads many APIs for every user | Load modules by permission and rely on server-side policies for enforcement |
| Tests | Happy-path workflows and selected tenant checks exist | Missing negative tests for self-service isolation, payroll reads, exports, policies, and throttling | Add a role-by-endpoint denial matrix |

The most important current risks are **absence of centralized policies**, **direct Eloquent serialization**, **global leave validation rules**, **broad payroll/dashboard reads**, and **mass-assignment exposure if a future user-management endpoint accepts the current `User::$fillable` fields**. The `User` model currently includes `tenant_id`, `role`, and `employee_id` in `$fillable`; these fields must never be writable from an ordinary profile request.

## 3. Recommended RBAC model

Use two layers of authorization.

First, use **roles** for stable organizational responsibilities. The current five roles are suitable as a starting point. Second, use **permissions and policies** for individual actions and object-level rules. Laravel’s authorization system is designed for this separation: gates are suitable for actions without a model, while policies group authorization around a model or resource.[2]

| Role | Read scope | Write scope | Sensitive access | Explicit exclusions |
|---|---|---|---|---|
| Super Admin | All tenants only if deliberately designed as a platform role | Platform and tenant administration | All tenant-sensitive data under audited support access | No unlogged impersonation or silent tenant switching |
| Company Admin | All records in own tenant | Organization, employees, settings, approvals | Salary, payroll, reports, audit logs | Cannot access another tenant |
| HR Manager | Employee, organization, leave, recruitment, performance in own tenant | Employee and leave workflows | Employee PII and HR reports; salary only when job-relevant | Cannot process or release payroll unless separately granted |
| Payroll Manager | Payroll population and payroll periods in own tenant | Payroll configuration, processing, corrections before lock | Salary, statutory deductions, payslips | Cannot change employee identity or approve their own payroll release |
| Employee | Own employee profile, own leave, own payslips, own notifications | Own profile fields explicitly allowed by policy; own leave requests | Own payslips and permitted personal data only | Cannot list employees, view payroll totals, approve leave, export data, or see audit logs |

Do not infer role from the email address in application code. The server should load the role from the authenticated database record. Email addresses may identify users, but they must not be an authorization claim.

Define permission names independently from labels so renaming a role does not change policy logic:

```php
namespace App\Enums;

enum Permission: string
{
    case EmployeesView = 'employees.view';
    case EmployeesManage = 'employees.manage';
    case PayrollView = 'payroll.view';
    case PayrollProcess = 'payroll.process';
    case PayrollRelease = 'payroll.release';
    case LeaveSubmit = 'leave.submit';
    case LeaveApprove = 'leave.approve';
    case ReportsEmployeesExport = 'reports.employees.export';
    case ReportsPayslipView = 'reports.payslip.view';
    case AuditView = 'audit.view';
}
```

For the current five fixed roles, a `User::hasPermission()` method backed by a role-to-permission map is sufficient. If customers need custom roles, branch-level roles, temporary grants, or delegated approval, move the map into tenant-scoped tables such as `roles`, `permissions`, `role_permissions`, and `user_roles`. Every assignment should contain `tenant_id`, and uniqueness should be enforced on `(tenant_id, role_id, user_id)`.

## 4. Centralize authorization with policies

Keep route middleware for coarse endpoint groups, but do not use it as the only control. Create policies for `Employee`, `LeaveRequest`, `PayrollPeriod`, `PayrollTransaction`, `AuditLog`, and report actions. A policy should check the role or permission, tenant ownership, self-service ownership, and workflow state.

A representative employee policy is:

```php
namespace App\Policies;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(
            Role::SuperAdmin,
            Role::CompanyAdmin,
            Role::HRManager,
            Role::PayrollManager,
        );
    }

    public function view(User $user, Employee $employee): Response
    {
        if ((int) $user->tenant_id !== (int) $employee->tenant_id) {
            return Response::denyAsNotFound();
        }

        if ($user->hasRole(Role::Employee)) {
            return $user->employee_id === $employee->id
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return $user->hasRole(
            Role::SuperAdmin,
            Role::CompanyAdmin,
            Role::HRManager,
            Role::PayrollManager,
        )
            ? Response::allow()
            : Response::deny();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin, Role::CompanyAdmin, Role::HRManager);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee)->allowed()
            && $user->hasRole(Role::SuperAdmin, Role::CompanyAdmin, Role::HRManager);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }
}
```

Register policies in `AppServiceProvider` or with Laravel’s policy discovery, then authorize inside controllers:

```php
public function show(Request $request, Employee $employee): JsonResponse
{
    $this->authorize('view', $employee);

    return response()->json([
        'employee' => EmployeeResource::make($employee),
    ]);
}
```

For model-free actions such as viewing the audit dashboard or processing payroll, define gates or dedicated policies. Laravel converts a failed `Gate::authorize()` call into a 403 response, while `denyAsNotFound()` is useful when revealing that a resource exists would cross a tenant boundary.[2]

The controller should no longer contain repeated `abort_unless` checks. Repetition causes authorization drift: one endpoint gets fixed while another remains permissive. A policy is also easier to test and review.

## 5. Enforce tenant isolation at every layer

The tenant ID must come from the authenticated server-side user or an explicitly authorized platform context. It must never be accepted from `tenant_id` request input, a hidden Vue field, a URL parameter, or an ordinary user update payload.

The current `EnsureTenantContext` middleware correctly derives the request tenant from `user->tenant_id`. Extend this boundary with a small `TenantContext` service. Controllers and services should call `TenantContext::id()` rather than reading request attributes directly. Queue jobs must carry the tenant ID explicitly and initialize the context before querying.

For each tenant-owned model, use a consistent query scope:

```php
public function scopeForTenant(Builder $query, int $tenantId): Builder
{
    return $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
}
```

Use it on every list, show, update, delete, export, and report query. Do not rely only on route model binding, because Laravel may resolve a record globally before the policy runs. Bind through tenant-scoped queries or authorize immediately after binding.

Form Requests must scope every foreign-key existence rule. The leave request validator currently uses global `exists:employees,id` and `exists:leave_types,id`. Replace it with rules such as:

```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    $tenantId = (int) $this->user()->tenant_id;

    return [
        'employee_id' => [
            'nullable',
            'integer',
            Rule::exists('employees', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
        ],
        'leave_type_id' => [
            'required',
            'integer',
            Rule::exists('leave_types', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
        ],
        'start_date' => ['required', 'date'],
        'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        'days_requested' => ['required', 'numeric', 'min:0.5'],
        'reason' => ['nullable', 'string', 'max:2000'],
    ];
}
```

For an employee, ignore a submitted `employee_id` and use the authenticated user’s linked employee. For managers, verify that the target employee belongs to the same tenant and, if branch or department scoping is introduced, to an organizational unit the manager is allowed to administer.

Add composite indexes and unique constraints where the business key is tenant-local. Examples include `(tenant_id, employee_no)`, `(tenant_id, code)` for branches and departments, and `(tenant_id, payroll_period_id, employee_id)` for payroll transactions. Use foreign keys for relationships, but still retain application-level authorization because a valid foreign key does not mean the actor is allowed to access the referenced row.

## 6. Prevent mass assignment and privilege escalation

The `User` model currently lists `tenant_id`, `role`, `employee_id`, and `must_change_password` in `$fillable`. These fields should not be writable through a generic profile request. Remove privileged fields from public `$fillable` arrays or use explicit DTOs and server-owned assignment:

```php
protected $fillable = [
    'name',
    'email',
    'phone',
];
```

When an administrator creates a user, assign the tenant and role in a dedicated service after authorizing the operation:

```php
$user = User::create([
    'name' => $data['name'],
    'email' => $data['email'],
    'password' => Hash::make($data['temporary_password']),
    'phone' => $data['phone'] ?? null,
]);

$user->forceFill([
    'tenant_id' => $authorizedTenantId,
    'role' => $authorizedRole,
    'employee_id' => $authorizedEmployeeId,
    'must_change_password' => true,
])->save();
```

A user must not assign themselves `Super Admin`, move themselves to another tenant, replace their employee link, clear `must_change_password`, or change the tenant of another account. Role changes should be audited with actor, target, old role, new role, reason, IP address, and request ID.

## 7. Protect sensitive HR fields with API Resources

Do not return Eloquent models directly from JSON endpoints. The current employee and dashboard controllers load relations and serialize model data directly. This makes future additions to `$visible`, `$hidden`, casts, or relationships capable of unintentionally exposing sensitive fields.

Create separate resources for public workforce data, HR detail, payroll detail, and employee self-service:

```php
class EmployeeResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();
        $isSelf = $user->employee_id === $this->id;
        $canSeePayroll = $user->hasRole('Company Admin', 'Payroll Manager', 'Super Admin');

        return array_filter([
            'id' => $this->id,
            'employee_no' => $this->employee_no,
            'name' => $this->full_name,
            'department' => $this->department?->name,
            'branch' => $this->branch?->name,
            'employment_status' => $this->employment_status,
            'email' => $isSelf || $user->hasRole('HR Manager', 'Company Admin', 'Super Admin')
                ? $this->email
                : null,
            'phone' => $isSelf || $user->hasRole('HR Manager', 'Company Admin', 'Super Admin')
                ? $this->phone
                : null,
            'basic_salary' => $canSeePayroll ? $this->basic_salary : null,
        ], static fn ($value) => $value !== null);
    }
}
```

In production, use explicit resource classes instead of the illustrative role checks above. Never include bank account numbers, national identifiers, tax identifiers, payroll transactions, or document URLs in a general employee list. Use a separate policy and resource for each sensitive category.

Dashboard metrics also need role-specific projections. An employee should not receive tenant headcount, monthly payroll totals, pending leave counts for other employees, recent audit logs, or a list of other employees merely because the Vue component hides some navigation buttons. The API should return a self-service dashboard payload for the Employee role and an administrative dashboard payload for authorized roles.

## 8. Payroll, leave, and report controls

Payroll requires separation of duties. At minimum, distinguish **process** from **release/approve**. A Payroll Manager may prepare a payroll run, while a Company Admin or separately authorized reviewer approves or releases it. The same user should not both create and release a high-value payroll run unless an explicit emergency policy permits it and the action is heavily audited.

Payroll periods should move through an explicit state machine such as `Draft -> Processing -> AwaitingApproval -> Approved -> Released -> Locked`. Each transition must be authorized, transactional, and idempotent. A locked period must reject recalculation and mutation except through a documented correction workflow.

Leave approval must reject self-approval. The policy should compare the requester’s employee ID with the approver’s employee ID and, if managers are introduced, confirm that the approver has authority over the relevant department or branch. The existing transactional balance update is good practice; preserve row locking and the “pending only” state transition.

Payslip PDF and Excel endpoints are high-value data exports. Apply a report policy before generating the file, constrain the query by tenant, and audit the event. An employee may access only their own payslip transaction. HR may export employee data only when the role permits it. Add rate limits and, for large exports, queue the generation and provide a short-lived, user-bound download reference. Do not use an unprotected public storage URL for payslips.

A report audit record should contain the actor, tenant, report type, filters, record count, target period, IP address, user agent, request ID, and timestamp. Avoid placing entire salary or identity datasets in logs.

## 9. Authentication and session hardening

The current login flow already regenerates the session after successful authentication and invalidates it on logout. Preserve this behavior. Add a named rate limiter to login using a composite key of normalized email and IP address. Laravel provides a cache-backed rate-limiting abstraction that can use Redis for distributed and efficient counters.[3]

Recommended controls include the following:

| Control | Recommended implementation |
|---|---|
| Login throttling | Five attempts per minute per email/IP combination, with a longer lockout after repeated failures |
| Password policy | Minimum 12 characters, breached-password screening where organizational policy allows, and no reuse of recent passwords |
| MFA | Require phishing-resistant WebAuthn or TOTP for Super Admin, Company Admin, and Payroll Manager; require step-up MFA before payroll release and bulk export |
| Email verification | Require verified email before privileged actions or account activation |
| Session cookie | `Secure`, `HttpOnly`, appropriate `SameSite`, short lifetime for privileged sessions, HTTPS-only production URL |
| Session rotation | Rotate after login, password change, MFA enrollment, privilege change, and impersonation start/stop |
| Session revocation | Provide “sign out all other sessions” and revoke Sanctum tokens on compromise or role change |
| Account lifecycle | Disable inactive users, force a password change for temporary credentials, and remove access promptly on termination |
| API tokens | Use tokens only for external integrations or mobile clients; assign narrow abilities, expirations, names, and revocation controls |

Sanctum’s first-party SPA mode uses cookie-based session authentication and CSRF protection; it should be used for this Vue application instead of creating a long-lived API token for the browser.[4] For external integrations, issue scoped tokens and protect routes with abilities in addition to user policies. A token ability alone must not replace tenant and model authorization.

Set production environment values similar to:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hr.example.com
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=.example.com
SANCTUM_STATEFUL_DOMAINS=hr.example.com
```

Use the narrowest possible `SANCTUM_STATEFUL_DOMAINS` list. Never include development hosts in a production deployment.

## 10. Request validation and input handling

Every Form Request should perform two independent checks: whether the actor may perform the action, and whether the submitted values are valid. `authorize()` should call a policy or permission, not merely return `true` for any authenticated user. Validation rules should include tenant ownership, allowed state transitions, date logic, numeric bounds, and business invariants.

Use allowlists for statuses, employment types, report names, sort columns, and export filters. Do not interpolate user-controlled column names into SQL. Use Laravel query builder bindings and validated arrays. Reject unexpected fields with DTOs or explicit request extraction instead of passing `$request->all()` to models.

For file uploads, validate MIME type and size, generate a server-side filename, store the file on a private disk, prevent executable extensions, scan sensitive uploads where required, and authorize each download. DomPDF and Laravel Excel output should be generated from authorized, tenant-scoped datasets only.

## 11. Audit logging and incident response

Audit logging must be append-only from the application user’s perspective. The audit record should include `tenant_id`, actor ID, action, target type, target ID, before/after changes with sensitive-field redaction, request ID, IP address, user agent, and timestamp. Do not allow a normal admin endpoint to delete or rewrite audit entries.

Record at least login success/failure, logout, password changes, MFA enrollment and recovery, role and tenant changes, employee creation/update/deactivation, salary changes, leave decisions, payroll processing/release/lock, report exports, payslip downloads, token creation/revocation, and failed authorization attempts for sensitive endpoints.

Use a correlation/request ID in application and Nginx logs. Keep logs out of public storage, rotate them, limit access, and define retention according to the organization’s employment and tax obligations. Redact passwords, session cookies, Sanctum tokens, authorization headers, and full identity or bank values.

## 12. Nginx and deployment controls

The Nginx root must be Laravel’s `public` directory. Deny access to `.env`, Git metadata, storage internals, Composer files, and source maps in production. Force HTTPS and add security headers after checking that the Vue application and any legitimate embedding use cases still work.

A starting Nginx header set is:

```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header X-Frame-Options "DENY" always;
```

Use a carefully tested Content Security Policy rather than copying a permissive policy. Production should have `APP_DEBUG=false`, secrets outside source control, least-privilege MySQL credentials, restricted Redis binding, private backups, patched OS packages, Composer and NPM audit checks, and a documented restore test. Laravel’s cached configuration must be rebuilt after `.env` changes, and queue workers must be restarted after code deployments because they are long-lived processes.

## 13. Authorization test matrix

The feature suite should test both positive and negative decisions. The following matrix should become a maintained regression suite.

| Test case | Expected result |
|---|---|
| Unauthenticated request to any protected route | 401 or redirect according to API contract |
| Employee lists employees | Only own record, or 403 if the endpoint is not part of self-service |
| Employee opens another employee’s detail URL | 404 or 403; never the other record |
| Employee reads tenant payroll totals | 403 or a self-only response with no aggregate payroll data |
| Employee downloads another employee’s payslip | 404 or 403 |
| HR Manager creates or updates an employee in another tenant | 404/403 and no database mutation |
| Payroll Manager processes payroll | Allowed only for an authorized draft period |
| Payroll Manager releases payroll without reviewer permission | 403 |
| HR Manager processes payroll | 403 unless explicitly granted payroll processing permission |
| Company Admin exports employees | Allowed, tenant-scoped, audited, and rate-limited |
| Employee exports employees | 403 |
| Leave requester approves their own request | 403 |
| Leave request with foreign-tenant employee or leave type ID | Validation failure and no data access |
| Crafted `tenant_id`, `role`, or `employee_id` in a profile update | Fields ignored or rejected; no privilege change |
| Revoked Sanctum token | 401 |
| Login attempts over the limit | 429 with no credential enumeration |
| Report request after role removal | Denied immediately without relying on cached frontend state |
| Sensitive endpoint with a valid session but stale role | Re-evaluated from current server-side user state |

Use `actingAs()` for session-authenticated feature tests, `Sanctum::actingAs()` for token routes, `Notification::fake()` and `Mail::fake()` for notification tests, and `Storage::fake()` for protected documents. Include a second tenant and identical business keys in test fixtures so accidental global queries are detected.

## 14. Implementation order

| Priority | Work item | Acceptance condition |
|---:|---|---|
| P0 | Add policies for employees, leave, payroll periods/transactions, reports, audit logs, and user administration | Controllers use `authorize()` or `Gate::authorize()` for every protected resource action |
| P0 | Replace direct Eloquent JSON serialization with API Resources | Employee, dashboard, payroll, leave, and report payloads contain only role-appropriate fields |
| P0 | Tenant-scope all Form Request foreign keys and queries | Cross-tenant IDs fail validation and cross-tenant records cannot be read or mutated |
| P0 | Remove privileged fields from user mass assignment | Role, tenant, employee link, and password flags are assigned only by authorized services |
| P0 | Restrict employee dashboard payloads and payroll reads | Employee users see only self-service data |
| P1 | Add login throttling, email verification, MFA, session revocation, and step-up checks | Privileged accounts and high-risk actions require stronger authentication |
| P1 | Separate payroll processing from approval/release | Four-eyes workflow is enforced in policy and database state transitions |
| P1 | Harden exports and payslips | Every export is tenant-scoped, policy-checked, rate-limited, private, and audited |
| P1 | Expand negative feature tests | Every matrix row above has an automated regression test |
| P2 | Add permission tables or a maintained permission map | Role labels are no longer scattered through controllers and Vue files |
| P2 | Add security monitoring and restore exercises | Alerts, log retention, dependency patching, and backup restoration are operationally tested |

## 15. Definition of done

The RBAC implementation is production-ready when authorization is enforced on the server through policies, all tenant-owned queries and validators are tenant-scoped, sensitive fields are returned through role-specific resources, and the UI merely reflects server-provided capabilities. Privileged account lifecycle, MFA, session revocation, auditability, report protection, queue context, deployment headers, backups, and negative feature tests must all be documented and exercised.

## References

[1]: https://owasp.org/www-project-application-security-verification-standard/ OWASP Application Security Verification Standard 5.0
[2]: https://laravel.com/docs/12.x/authorization Laravel 12 Authorization
[3]: https://laravel.com/docs/12.x/rate-limiting Laravel 12 Rate Limiting
[4]: https://laravel.com/docs/12.x/sanctum Laravel 12 Sanctum
[5]: https://laravel.com/docs/12.x/authentication Laravel 12 Authentication
[6]: https://laravel.com/docs/12.x/validation Laravel 12 Validation
[7]: https://laravel.com/docs/12.x/csrf Laravel 12 CSRF Protection
[8]: https://laravel.com/docs/12.x/encryption Laravel 12 Encryption
[9]: https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html OWASP Session Management Cheat Sheet
