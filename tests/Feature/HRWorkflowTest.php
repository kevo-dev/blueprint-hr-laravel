<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Grade;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NssfRate;
use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use App\Models\ShifRate;
use App\Models\TaxBracket;
use App\Models\TaxRelief;
use App\Models\Tenant;
use App\Models\User;
use App\Models\HousingLevyRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HRWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_login_and_view_dashboard(): void
    {
        $tenant = $this->tenant('blueprint');
        $this->employee($tenant);
        $user = $this->user($tenant, Role::CompanyAdmin, 'admin@example.test');

        $this->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])->assertOk()->assertJsonPath('user.email', $user->email);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.tenant_id', $tenant->id);
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.headcount', 1)
            ->assertJsonPath('tenant.id', $tenant->id);
    }

    public function test_tenant_boundaries_reject_foreign_organization_references(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');
        $orgA = $this->organization($tenantA);
        $orgB = $this->organization($tenantB);
        $admin = $this->user($tenantA, Role::CompanyAdmin, 'admin-a@example.test');

        $this->actingAs($admin)->postJson('/api/employees', [
            'employee_no' => 'EMP-A-001',
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
            'basic_salary' => 50000,
            'branch_id' => $orgB['branch']->id,
            'department_id' => $orgA['department']->id,
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/organization/departments', [
            'name' => 'Foreign Branch Department',
            'code' => 'FOREIGN',
            'branch_id' => $orgB['branch']->id,
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/employees', [
            'employee_no' => 'EMP-A-001',
            'first_name' => 'Valid',
            'last_name' => 'Employee',
            'basic_salary' => 50000,
            'branch_id' => $orgA['branch']->id,
            'department_id' => $orgA['department']->id,
        ])->assertCreated();
    }

    public function test_leave_request_can_be_submitted_approved_and_balanced(): void
    {
        $tenant = $this->tenant('leave-tenant');
        $org = $this->organization($tenant);
        $employee = $this->employee($tenant, $org);
        $admin = $this->user($tenant, Role::HRManager, 'hr@example.test');
        $leaveType = LeaveType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 21,
            'paid' => 'Yes',
        ]);
        $balance = LeaveBalance::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 21,
            'used_days' => 0,
            'carried_forward' => 0,
        ]);

        $request = $this->actingAs($admin)->postJson('/api/leave/requests', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'days_requested' => 3,
            'reason' => 'Family leave',
        ])->assertCreated()->json('request');

        $this->postJson('/api/leave/requests/'.$request['id'].'/decision', [
            'status' => 'Approved',
            'comments' => 'Approved for testing',
        ])->assertOk();

        $this->assertDatabaseHas('leave_requests', ['id' => $request['id'], 'status' => 'Approved']);
        $this->assertDatabaseHas('leave_balances', ['id' => $balance->id, 'used_days' => 3]);
    }

    public function test_payroll_processes_statutory_deductions_and_reports(): void
    {
        $tenant = $this->tenant('payroll-tenant');
        $org = $this->organization($tenant);
        $employee = $this->employee($tenant, $org, 180000);
        $admin = $this->user($tenant, Role::PayrollManager, 'payroll@example.test');
        $this->statutoryRates($tenant);
        $period = PayrollPeriod::create([
            'tenant_id' => $tenant->id,
            'name' => 'August 2026',
            'month' => 8,
            'year' => 2026,
            'status' => 'Open',
        ]);

        $this->actingAs($admin)->postJson('/api/payroll/process', [
            'payroll_period_id' => $period->id,
        ])->assertOk()->assertJsonPath('run.total_employees', 1);

        $transaction = PayrollTransaction::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('180000.00', $transaction->gross_pay);
        $this->assertGreaterThan(0, (float) $transaction->paye);
        $this->assertGreaterThan(0, (float) $transaction->nssf);
        $this->assertGreaterThan(0, (float) $transaction->shif);
        $this->assertSame('Processed', $period->refresh()->status);
        $this->actingAs($admin)->postJson('/api/payroll/process', ['payroll_period_id' => $period->id])->assertStatus(422);

        $reportUser = $this->user($tenant, Role::HRManager, 'hr-reporter@example.test');
        $this->actingAs($reportUser)->get('/api/reports/employees.xlsx')->assertOk();
        $this->actingAs($reportUser)->get('/api/reports/payslips/'.$transaction->id.'.pdf')->assertOk();
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

    private function user(Tenant $tenant, Role $role, string $email): User
    {
        $user = User::create([
            'name' => $role->value.' User',
            'email' => $email,
            'password' => Hash::make('secret-password'),
        ]);
        $user->forceFill([
            'tenant_id' => $tenant->id,
            'role' => $role->value,
        ])->save();

        return $user->refresh();
    }

    private function organization(Tenant $tenant): array
    {
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Head Office', 'code' => 'HQ-'.$tenant->id]);
        $department = Department::create(['tenant_id' => $tenant->id, 'name' => 'Engineering', 'code' => 'ENG-'.$tenant->id, 'branch_id' => $branch->id]);
        $designation = \App\Models\Designation::create(['tenant_id' => $tenant->id, 'name' => 'Engineer']);
        $grade = Grade::create(['tenant_id' => $tenant->id, 'name' => 'Grade A', 'level' => 'A', 'min_salary' => 0, 'max_salary' => 1000000]);
        $employmentType = EmploymentType::create(['tenant_id' => $tenant->id, 'name' => 'Permanent']);

        return compact('branch', 'department', 'designation', 'grade', 'employmentType');
    }

    private function employee(Tenant $tenant, ?array $org = null, float $salary = 50000): Employee
    {
        $org ??= $this->organization($tenant);

        return Employee::create([
            'tenant_id' => $tenant->id,
            'employee_no' => 'EMP-'.$tenant->id.'-001',
            'payroll_no' => 'PAY-'.$tenant->id.'-001',
            'first_name' => 'George',
            'last_name' => 'Wamola',
            'email' => 'george'.$tenant->id.'@example.test',
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

    private function statutoryRates(Tenant $tenant): void
    {
        foreach ([[1, 0, 24000, .10], [2, 24001, 32333, .25], [3, 32334, 500000, .30], [4, 500001, 800000, .325], [5, 800001, null, .35]] as $row) {
            TaxBracket::create(['tenant_id' => $tenant->id, 'band_order' => $row[0], 'lower_limit' => $row[1], 'upper_limit' => $row[2], 'rate' => $row[3]]);
        }
        TaxRelief::create(['tenant_id' => $tenant->id, 'relief_name' => 'Personal Relief', 'monthly_amount' => 2400]);
        NssfRate::create(['tenant_id' => $tenant->id, 'tier_name' => 'Tier I', 'lower_limit' => 0, 'upper_limit' => 8000, 'employee_rate' => .06, 'employer_rate' => .06]);
        NssfRate::create(['tenant_id' => $tenant->id, 'tier_name' => 'Tier II', 'lower_limit' => 8001, 'upper_limit' => 72000, 'employee_rate' => .06, 'employer_rate' => .06]);
        ShifRate::create(['tenant_id' => $tenant->id, 'percentage' => .0275, 'min_amount' => 300]);
        HousingLevyRate::create(['tenant_id' => $tenant->id, 'employee_percentage' => .015, 'employer_percentage' => .015]);
    }
}
