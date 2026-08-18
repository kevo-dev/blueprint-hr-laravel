<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\PayrollPeriodPolicy;
use App\Policies\PayrollTransactionPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(PayrollPeriod::class, PayrollPeriodPolicy::class);
        Gate::policy(PayrollTransaction::class, PayrollTransactionPolicy::class);

        Gate::define('manageOrganization', [OrganizationPolicy::class, 'manage']);
        Gate::define('exportEmployees', [ReportPolicy::class, 'exportEmployees']);
        Gate::define('downloadPayslip', [ReportPolicy::class, 'downloadPayslip']);
    }
}
