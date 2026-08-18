<?php
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
    Route::middleware('role:Super Admin,Company Admin,HR Manager')->group(function () {
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::post('/organization/branches', [OrganizationController::class, 'storeBranch']);
        Route::post('/organization/departments', [OrganizationController::class, 'storeDepartment']);
    });
    Route::get('/organization', [OrganizationController::class, 'index']);
    Route::get('/leave', [LeaveController::class, 'index']);
    Route::post('/leave/requests', [LeaveController::class, 'store']);
    Route::middleware('role:Super Admin,Company Admin,HR Manager')->post('/leave/requests/{leaveRequest}/decision', [LeaveController::class, 'approve']);
    Route::get('/payroll', [PayrollController::class, 'index']);
    Route::middleware('role:Super Admin,Company Admin,Payroll Manager')->group(function () { Route::post('/payroll/periods', [PayrollController::class, 'store']); Route::post('/payroll/process', [PayrollController::class, 'process']); });
    Route::middleware('role:Super Admin,Company Admin,HR Manager')->get('/reports/employees.xlsx', [ReportController::class, 'employeeExcel']);
    Route::get('/reports/payslips/{transaction}.pdf', [ReportController::class, 'payslip']);
    Route::middleware('role:Super Admin,Company Admin,HR Manager')->get('/audit-logs', [AuditLogController::class, 'index']);
});
