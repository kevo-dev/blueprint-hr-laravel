<?php
namespace App\Http\Controllers;
use App\Exports\EmployeeExport;
use App\Models\PayrollTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
class ReportController extends Controller
{
    public function employeeExcel(Request $request) { return Excel::download(new EmployeeExport((int)$request->attributes->get('tenant_id')), 'employees-'.now()->format('Y-m-d').'.xlsx'); }
    public function payslip(Request $request, PayrollTransaction $transaction) { abort_unless($transaction->tenant_id === (int)$request->attributes->get('tenant_id'),404); abort_unless($request->user()->hasRole('Employee') === false || $request->user()->employee_id === $transaction->employee_id,403); $transaction->load(['employee','period','employee.tenant']); return Pdf::loadView('reports.payslip',['transaction'=>$transaction])->setPaper('a4')->download('payslip-'.$transaction->employee->employee_no.'-'.$transaction->period->name.'.pdf'); }
}
