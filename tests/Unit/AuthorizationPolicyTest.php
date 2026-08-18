<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollTransaction;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\PayrollTransactionPolicy;
use App\Policies\ReportPolicy;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    public function test_employee_policy_is_tenant_and_self_service_aware(): void
    {
        $user = $this->user(Role::Employee, 1, 10);
        $own = (new Employee)->forceFill(['id' => 10, 'tenant_id' => 1]);
        $peer = (new Employee)->forceFill(['id' => 11, 'tenant_id' => 1]);
        $foreign = (new Employee)->forceFill(['id' => 12, 'tenant_id' => 2]);
        $policy = new EmployeePolicy();

        $this->assertTrue($policy->view($user, $own));
        $this->assertFalse($policy->view($user, $peer));
        $this->assertFalse($policy->view($user, $foreign));
        $this->assertFalse($policy->create($user));
    }

    public function test_people_roles_can_manage_only_their_own_tenant(): void
    {
        $user = $this->user(Role::HRManager, 1);
        $employee = (new Employee)->forceFill(['id' => 10, 'tenant_id' => 1]);
        $foreign = (new Employee)->forceFill(['id' => 11, 'tenant_id' => 2]);
        $policy = new EmployeePolicy();

        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $employee));
        $this->assertFalse($policy->update($user, $foreign));
    }

    public function test_leave_decision_requires_approval_role_and_same_tenant(): void
    {
        $hr = $this->user(Role::HRManager, 1);
        $request = (new LeaveRequest)->forceFill(['tenant_id' => 1, 'employee_id' => 10]);
        $foreign = (new LeaveRequest)->forceFill(['tenant_id' => 2, 'employee_id' => 20]);
        $employee = $this->user(Role::Employee, 1, 10);
        $policy = new LeaveRequestPolicy();

        $this->assertTrue($policy->decide($hr, $request));
        $this->assertFalse($policy->decide($hr, $foreign));
        $this->assertFalse($policy->decide($employee, $request));
    }

    public function test_payslip_access_is_owner_or_payroll_role_within_tenant(): void
    {
        $employee = $this->user(Role::Employee, 1, 10);
        $payroll = $this->user(Role::PayrollManager, 1);
        $own = (new PayrollTransaction)->forceFill(['tenant_id' => 1, 'employee_id' => 10]);
        $peer = (new PayrollTransaction)->forceFill(['tenant_id' => 1, 'employee_id' => 11]);
        $foreign = (new PayrollTransaction)->forceFill(['tenant_id' => 2, 'employee_id' => 10]);
        $policy = new PayrollTransactionPolicy();

        $this->assertTrue($policy->view($employee, $own));
        $this->assertFalse($policy->view($employee, $peer));
        $this->assertFalse($policy->view($employee, $foreign));
        $this->assertTrue($policy->view($payroll, $peer));
        $this->assertFalse($policy->view($payroll, $foreign));
    }

    public function test_employee_export_is_limited_to_people_management_roles(): void
    {
        $policy = new ReportPolicy();

        $this->assertTrue($policy->exportEmployees($this->user(Role::CompanyAdmin, 1)));
        $this->assertTrue($policy->exportEmployees($this->user(Role::HRManager, 1)));
        $this->assertFalse($policy->exportEmployees($this->user(Role::PayrollManager, 1)));
        $this->assertFalse($policy->exportEmployees($this->user(Role::Employee, 1, 10)));
        $this->assertFalse($policy->exportEmployees($this->user(Role::HRManager, null)));
    }

    private function user(Role $role, ?int $tenantId, ?int $employeeId = null): User
    {
        $user = new User();
        $user->role = $role;
        $user->tenant_id = $tenantId;
        $user->employee_id = $employeeId;

        return $user;
    }
}
