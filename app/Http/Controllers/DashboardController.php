<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);
        $tenantId = (int) $request->attributes->get('tenant_id');
        $user = $request->user();
        $isEmployee = $user->hasRole('Employee');

        $employeeQuery = Employee::query()
            ->forTenant($tenantId)
            ->where('employment_status', 'Active');
        if ($isEmployee && $user->employee_id) {
            $employeeQuery->whereKey($user->employee_id);
        }

        $employees = (clone $employeeQuery)
            ->with(['department', 'branch'])
            ->latest()
            ->limit(5)
            ->get();

        $payrollQuery = PayrollTransaction::query()
            ->forTenant($tenantId)
            ->whereHas('employee', fn ($query) => $query->where('employment_status', 'Active'));
        if ($isEmployee && $user->employee_id) {
            $payrollQuery->where('employee_id', $user->employee_id);
        }

        $periodQuery = PayrollPeriod::query()->forTenant($tenantId);
        if ($isEmployee && $user->employee_id) {
            $periodQuery->whereHas('transactions', fn ($query) => $query->where('employee_id', $user->employee_id));
        }

        $recentAudit = $isEmployee
            ? collect()
            : AuditLog::query()->forTenant($tenantId)->latest()->limit(10)->get();

        return response()->json([
            'metrics' => [
                'headcount' => $employeeQuery->count(),
                'monthly_payroll' => (float) $payrollQuery->sum('gross_pay'),
                'branches' => $isEmployee ? 0 : Branch::query()->forTenant($tenantId)->count(),
                'departments' => $isEmployee ? 0 : Department::query()->forTenant($tenantId)->count(),
                'pending_leave' => $isEmployee
                    ? LeaveRequest::query()->forTenant($tenantId)->where('employee_id', $user->employee_id)->where('status', 'Pending')->count()
                    : LeaveRequest::query()->forTenant($tenantId)->where('status', 'Pending')->count(),
            ],
            'tenant' => $user->tenant()->select(['id', 'name'])->first(),
            'employees' => $employees,
            'periods' => $periodQuery->latest()->get(),
            'recent_audit' => $recentAudit,
        ]);
    }
}
