<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Grade;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_tenant_routes(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/employees')->assertUnauthorized();
        $this->getJson('/api/payroll')->assertUnauthorized();
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }

    public function test_employee_cannot_manage_people_organization_or_audit_logs(): void
    {
        $tenant = $this->tenant('employee-denials');
        $org = $this->organization($tenant);
        $employee = $this->employee($tenant, $org, 50000, 'DENIALS');
        $user = $this->user($tenant, Role::Employee, 'employee-denials@example.test', $employee);

        $this->actingAs($user)->postJson('/api/employees', [
            'employee_no' => 'EMP-DENY-001',
            'first_name' => 'Blocked',
            'last_name' => 'Create',
            'basic_salary' => 50000,
            'branch_id' => $org['branch']->id,
            'department_id' => $org['department']->id,
        ])->assertForbidden();

        $this->actingAs($user)->postJson('/api/organization/branches', [
            'name' => 'Blocked Branch',
            'code' => 'BLOCKED',
        ])->assertForbidden();

        $this->actingAs($user)->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_role_boundaries_are_enforced_for_employee_and_payroll_actions(): void
    {
        $tenant = $this->tenant('role-boundaries');
        $org = $this->organization($tenant);
        $employee = $this->employee($tenant, $org, 50000, 'ROLE-EMPLOYEE');
        $hr = $this->user($tenant, Role::HRManager, 'role-hr@example.test');
        $payroll = $this->user($tenant, Role::PayrollManager, 'role-payroll@example.test');
        $employeeUser = $this->user($tenant, Role::Employee, 'role-employee@example.test', $employee);

        $this->actingAs($payroll)->postJson('/api/employees', [
            'employee_no' => 'EMP-ROLE-001',
            'first_name' => 'Payroll',
            'last_name' => 'Blocked',
            'basic_salary' => 50000,
            'branch_id' => $org['branch']->id,
            'department_id' => $org['department']->id,
        ])->assertForbidden();

        $this->actingAs($hr)->postJson('/api/payroll/process', [
            'payroll_period_id' => 999999,
        ])->assertForbidden();

        $type = $this->leaveType($tenant);
        $this->leaveBalance($tenant, $employee, $type);
        $leave = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'days_requested' => 1,
            'reason' => 'Role boundary test',
            'status' => 'Pending',
        ]);

        $this->actingAs($employeeUser)->postJson('/api/leave/requests/'.$leave->id.'/decision', [
            'status' => 'Approved',
        ])->assertForbidden();
    }

    public function test_company_admin_can_manage_employees_inside_own_tenant(): void
    {
        $tenant = $this->tenant('admin-employee-create');
        $org = $this->organization($tenant);
        $admin = $this->user($tenant, Role::CompanyAdmin, 'company-admin-create@example.test');

        $this->actingAs($admin)->postJson('/api/employees', [
            'employee_no' => 'EMP-ADMIN-001',
            'first_name' => 'Created',
            'last_name' => 'Employee',
            'basic_salary' => 75000,
            'branch_id' => $org['branch']->id,
            'department_id' => $org['department']->id,
        ])->assertCreated()->assertJsonPath('employee.tenant_id', $tenant->id);

        $this->assertDatabaseHas('employees', [
            'tenant_id' => $tenant->id,
            'employee_no' => 'EMP-ADMIN-001',
        ]);
    }

    public function test_employee_can_only_list_and_view_their_own_employee_record(): void
    {
        $tenant = $this->tenant('employee-isolation');
        $org = $this->organization($tenant);
        $own = $this->employee($tenant, $org, 50000, 'OWN');
        $other = $this->employee($tenant, $org, 70000, 'OTHER');
        $user = $this->user($tenant, Role::Employee, 'employee-isolation@example.test', $own);

        $this->actingAs($user)
            ->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->actingAs($user)->getJson('/api/employees/'.$own->id)
            ->assertOk()
            ->assertJsonPath('employee.id', $own->id);

        $this->actingAs($user)->getJson('/api/employees/'.$other->id)->assertForbidden();
    }

    public function test_employee_dashboard_is_limited_to_the_authenticated_employee_record(): void
    {
        $tenant = $this->tenant('employee-dashboard');
        $org = $this->organization($tenant);
        $own = $this->employee($tenant, $org, 50000, 'DASH-OWN');
        $this->employee($tenant, $org, 70000, 'DASH-OTHER');
        $user = $this->user($tenant, Role::Employee, 'employee-dashboard@example.test', $own);

        $response = $this->actingAs($user)->getJson('/api/dashboard')->assertOk();

        $this->assertCount(1, $response->json('employees'));
        $this->assertSame($own->id, $response->json('employees.0.id'));
        $this->assertSame(1, $response->json('metrics.headcount'));
    }

    public function test_employee_leave_listing_and_submission_are_self_scoped(): void
    {
        $tenant = $this->tenant('employee-leave');
        $org = $this->organization($tenant);
        $own = $this->employee($tenant, $org, 50000, 'LEAVE-OWN');
        $other = $this->employee($tenant, $org, 70000, 'LEAVE-OTHER');
        $user = $this->user($tenant, Role::Employee, 'employee-leave@example.test', $own);
        $type = $this->leaveType($tenant);
        $this->leaveBalance($tenant, $own, $type);
        $this->leaveBalance($tenant, $other, $type);

        LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $other->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'days_requested' => 1,
            'reason' => 'Other employee request',
            'status' => 'Pending',
        ]);

        $this->actingAs($user)->getJson('/api/leave')->assertOk()->assertJsonCount(0, 'requests');

        $response = $this->actingAs($user)->postJson('/api/leave/requests', [
            'employee_id' => $other->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'days_requested' => 1,
            'reason' => 'Self-service request',
        ])->assertCreated();

        $this->assertSame($own->id, $response->json('request.employee_id'));
    }

    public function test_cross_tenant_employee_references_are_rejected_before_creation(): void
    {
        $tenantA = $this->tenant('cross-tenant-a');
        $tenantB = $this->tenant('cross-tenant-b');
        $orgA = $this->organization($tenantA);
        $orgB = $this->organization($tenantB);
        $admin = $this->user($tenantA, Role::CompanyAdmin, 'cross-tenant-admin@example.test');

        $this->actingAs($admin)->postJson('/api/employees', [
            'employee_no' => 'EMP-CROSS-001',
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
            'basic_salary' => 50000,
            'branch_id' => $orgB['branch']->id,
            'department_id' => $orgA['department']->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('employees', ['employee_no' => 'EMP-CROSS-001']);
    }

    public function test_cross_tenant_leave_type_reference_is_rejected(): void
    {
        $tenantA = $this->tenant('leave-cross-a');
        $tenantB = $this->tenant('leave-cross-b');
        $orgA = $this->organization($tenantA);
        $orgB = $this->organization($tenantB);
        $employeeA = $this->employee($tenantA, $orgA, 50000, 'LEAVE-CROSS-A');
        $employeeB = $this->employee($tenantB, $orgB, 50000, 'LEAVE-CROSS-B');
        $admin = $this->user($tenantA, Role::HRManager, 'leave-cross-admin@example.test');
        $foreignType = $this->leaveType($tenantB);
        $this->leaveBalance($tenantA, $employeeA, $this->leaveType($tenantA));

        $this->actingAs($admin)->postJson('/api/leave/requests', [
            'employee_id' => $employeeA->id,
            'leave_type_id' => $foreignType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'days_requested' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertNotSame($employeeA->tenant_id, $employeeB->tenant_id);
    }

    public function test_employee_can_download_only_their_own_payslip(): void
    {
        $tenant = $this->tenant('payslip-owner');
        $org = $this->organization($tenant);
        $own = $this->employee($tenant, $org, 50000, 'PAYSLIP-OWN');
        $other = $this->employee($tenant, $org, 70000, 'PAYSLIP-OTHER');
        $user = $this->user($tenant, Role::Employee, 'payslip-owner@example.test', $own);
        $period = $this->payrollPeriod($tenant);
        $ownTransaction = $this->transaction($tenant, $period, $own, 50000);
        $otherTransaction = $this->transaction($tenant, $period, $other, 70000);

        $this->actingAs($user)->get('/api/reports/payslips/'.$ownTransaction->id.'.pdf')->assertOk();
        $this->actingAs($user)->get('/api/reports/payslips/'.$otherTransaction->id.'.pdf')->assertForbidden();
    }

    public function test_cross_tenant_payslip_is_hidden(): void
    {
        $tenantA = $this->tenant('payslip-tenant-a');
        $tenantB = $this->tenant('payslip-tenant-b');
        $orgA = $this->organization($tenantA);
        $orgB = $this->organization($tenantB);
        $employeeB = $this->employee($tenantB, $orgB, 50000, 'PAYSLIP-FOREIGN');
        $adminA = $this->user($tenantA, Role::CompanyAdmin, 'payslip-admin-a@example.test');
        $transactionB = $this->transaction($tenantB, $this->payrollPeriod($tenantB), $employeeB, 50000);

        $this->actingAs($adminA)->get('/api/reports/payslips/'.$transactionB->id.'.pdf')->assertNotFound();
    }

    public function test_foreign_payroll_period_cannot_be_processed(): void
    {
        $tenantA = $this->tenant('payroll-cross-a');
        $tenantB = $this->tenant('payroll-cross-b');
        $this->organization($tenantA);
        $this->organization($tenantB);
        $payroll = $this->user($tenantA, Role::PayrollManager, 'payroll-cross@example.test');
        $foreignPeriod = $this->payrollPeriod($tenantB);

        $this->actingAs($payroll)->postJson('/api/payroll/process', [
            'payroll_period_id' => $foreignPeriod->id,
        ])->assertNotFound();
    }

    public function test_employee_cannot_export_employee_records_or_audit_logs(): void
    {
        $tenant = $this->tenant('restricted-reports');
        $org = $this->organization($tenant);
        $employee = $this->employee($tenant, $org, 50000, 'REPORT-EMPLOYEE');
        $user = $this->user($tenant, Role::Employee, 'restricted-report-user@example.test', $employee);

        $this->actingAs($user)->get('/api/reports/employees.xlsx')->assertForbidden();
        $this->actingAs($user)->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_user_role_and_tenant_are_not_changed_by_untrusted_profile_fields(): void
    {
        $tenant = $this->tenant('privilege-escalation');
        $user = $this->user($tenant, Role::Employee, 'privilege-escalation@example.test');
        $originalTenantId = $user->tenant_id;

        $user->fill([
            'name' => 'Changed Name',
            'role' => Role::SuperAdmin->value,
            'tenant_id' => $originalTenantId + 999,
            'employee_id' => 999999,
        ]);

        $this->assertSame(Role::Employee->value, $user->role->value);
        $this->assertSame($originalTenantId, $user->tenant_id);
    }

    private function tenant(string $subdomain): Tenant
    {
        return Tenant::create([
            'company_name' => 'BluePrint '.strtoupper($subdomain),
            'subdomain' => $subdomain,
            'email' => $subdomain.'@example.test',
            'status' => 'Active',
            'currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
        ]);
    }

    private function user(Tenant $tenant, Role $role, string $email, ?Employee $employee = null): User
    {
        $user = User::create([
            'name' => $role->value.' User',
            'email' => $email,
            'password' => Hash::make('secret-password'),
        ]);
        $user->forceFill([
            'tenant_id' => $tenant->id,
            'role' => $role->value,
            'employee_id' => $employee?->id,
        ])->save();

        return $user->refresh();
    }

    private function organization(Tenant $tenant): array
    {
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Head Office',
            'code' => 'HQ-'.$tenant->id,
        ]);
        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'code' => 'ENG-'.$tenant->id,
            'branch_id' => $branch->id,
        ]);
        $designation = Designation::create(['tenant_id' => $tenant->id, 'name' => 'Engineer']);
        $grade = Grade::create([
            'tenant_id' => $tenant->id,
            'name' => 'Grade A',
            'level' => 'A',
            'min_salary' => 0,
            'max_salary' => 1000000,
        ]);
        $employmentType = EmploymentType::create(['tenant_id' => $tenant->id, 'name' => 'Permanent']);

        return compact('branch', 'department', 'designation', 'grade', 'employmentType');
    }

    private function employee(Tenant $tenant, ?array $org = null, float $salary = 50000, string $suffix = '001'): Employee
    {
        $org ??= $this->organization($tenant);

        return Employee::create([
            'tenant_id' => $tenant->id,
            'employee_no' => 'EMP-'.$tenant->id.'-'.$suffix,
            'payroll_no' => 'PAY-'.$tenant->id.'-'.$suffix,
            'first_name' => 'Test',
            'last_name' => $suffix,
            'email' => strtolower($suffix).'@example.test',
            'branch_id' => $org['branch']->id,
            'department_id' => $org['department']->id,
            'designation_id' => $org['designation']->id,
            'grade_id' => $org['grade']->id,
            'employment_type_id' => $org['employmentType']->id,
            'employment_status' => 'Active',
            'employment_date' => '2023-01-15',
            'basic_salary' => $salary,
        ]);
    }

    private function leaveType(Tenant $tenant): LeaveType
    {
        return LeaveType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 21,
            'paid' => 'Yes',
        ]);
    }

    private function leaveBalance(Tenant $tenant, Employee $employee, LeaveType $type): LeaveBalance
    {
        return LeaveBalance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => now()->year,
            'allocated_days' => 21,
            'used_days' => 0,
            'carried_forward' => 0,
        ]);
    }

    private function payrollPeriod(Tenant $tenant): PayrollPeriod
    {
        return PayrollPeriod::create([
            'tenant_id' => $tenant->id,
            'name' => 'August 2026',
            'month' => 8,
            'year' => 2026,
            'status' => 'Open',
        ]);
    }

    private function transaction(Tenant $tenant, PayrollPeriod $period, Employee $employee, float $salary): PayrollTransaction
    {
        return PayrollTransaction::create([
            'tenant_id' => $tenant->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'basic_salary' => $salary,
            'allowances' => 0,
            'gross_pay' => $salary,
            'taxable_pay' => $salary,
            'paye' => 5000,
            'personal_relief' => 2400,
            'nssf' => 3600,
            'shif' => 1375,
            'housing_levy' => 750,
            'other_deductions' => 0,
            'total_deductions' => 10725,
            'net_pay' => $salary - 10725,
            'status' => 'Processed',
        ]);
    }
}
