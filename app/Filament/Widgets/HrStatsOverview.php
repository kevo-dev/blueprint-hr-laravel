<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HrStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $tenantId = (int) $user?->tenant_id;

        if (! $user || ! $tenantId) {
            return [];
        }

        $employeeQuery = Employee::query()
            ->forTenant($tenantId)
            ->where('employment_status', 'Active');

        $payrollQuery = PayrollTransaction::query()
            ->forTenant($tenantId)
            ->whereHas('employee', fn ($query) => $query->where('employment_status', 'Active'));

        $isEmployee = $user->hasRole('Employee');

        if ($isEmployee && $user->employee_id) {
            $employeeQuery->whereKey($user->employee_id);
            $payrollQuery->where('employee_id', $user->employee_id);
        }

        $pendingLeaveQuery = LeaveRequest::query()
            ->forTenant($tenantId)
            ->where('status', 'Pending');

        if ($isEmployee) {
            $pendingLeaveQuery->where('employee_id', $user->employee_id);
        }

        return [
            Stat::make('Active Employees', $employeeQuery->count())
                ->description('Current active headcount')
                ->icon('heroicon-o-users'),
            Stat::make('Monthly Payroll', 'KES '.number_format((float) $payrollQuery->sum('gross_pay'), 2))
                ->description('Gross payroll represented in transactions')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Branches', $isEmployee ? 0 : Branch::query()->forTenant($tenantId)->count())
                ->description('Organization branches')
                ->icon('heroicon-o-building-office-2'),
            Stat::make('Departments', $isEmployee ? 0 : Department::query()->forTenant($tenantId)->count())
                ->description('Organization departments')
                ->icon('heroicon-o-squares-2x2'),
            Stat::make('Pending Leave', $pendingLeaveQuery->count())
                ->description('Requests awaiting a decision')
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
