<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeExport;
use App\Models\PayrollTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function employeeExcel(Request $request)
    {
        Gate::authorize('exportEmployees');

        return Excel::download(
            new EmployeeExport((int) $request->attributes->get('tenant_id')),
            'employees-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function payslip(Request $request, PayrollTransaction $transaction)
    {
        abort_unless($transaction->belongsToTenant((int) $request->attributes->get('tenant_id')), 404);
        Gate::authorize('downloadPayslip', $transaction);
        $transaction->load(['employee', 'period', 'employee.tenant']);

        return Pdf::loadView('reports.payslip', ['transaction' => $transaction])
            ->setPaper('a4')
            ->download('payslip-'.$transaction->employee->employee_no.'-'.$transaction->period->name.'.pdf');
    }
}
