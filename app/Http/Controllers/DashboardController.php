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
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $user = $request->user();
        $employeeQuery = Employee::query()->where('tenant_id',$tenantId)->where('employment_status','Active');
        if ($user->hasRole('Employee') && $user->employee_id) $employeeQuery->whereKey($user->employee_id);
        $employees = (clone $employeeQuery)->with(['department','branch'])->latest()->limit(5)->get();
        $payroll = PayrollTransaction::query()->where('tenant_id',$tenantId)->whereHas('employee',fn($q)=>$q->where('employment_status','Active'))->sum('gross_pay');
        return response()->json(['metrics'=>['headcount'=>$employeeQuery->count(),'monthly_payroll'=>(float)$payroll,'branches'=>Branch::where('tenant_id',$tenantId)->count(),'departments'=>Department::where('tenant_id',$tenantId)->count(),'pending_leave'=>LeaveRequest::where('tenant_id',$tenantId)->where('status','Pending')->count()], 'tenant'=>$user->tenant, 'employees'=>$employees, 'periods'=>PayrollPeriod::where('tenant_id',$tenantId)->latest()->get(), 'recent_audit'=>AuditLog::where('tenant_id',$tenantId)->latest()->limit(10)->get()]);
    }
}
