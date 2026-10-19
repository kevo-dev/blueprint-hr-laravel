<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeePayrollItem;
use App\Models\FringeBenefitTaxRate;
use App\Models\PayrollCode;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollConfigurationController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function codes(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        return response()->json([
            'codes' => PayrollCode::query()->forTenant($tenantId)->with('employeeItems')->orderBy('code')->get(),
            'assignments' => EmployeePayrollItem::query()->forTenant($tenantId)->with(['employee:id,first_name,last_name,employee_no', 'payrollCode'])->latest()->get(),
        ]);
    }

    public function storeCode(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'code' => ['required','string','max:40',Rule::unique('payroll_codes')->where('tenant_id',$tenantId)],
            'name' => 'required|string|max:150',
            'kind' => 'required|in:earning,deduction,employer_contribution,benefit',
            'calculation_method' => 'required|in:fixed,percentage',
            'default_amount' => 'nullable|numeric|min:0',
            'default_percentage' => 'nullable|numeric|min:0|max:100',
            'percentage_basis' => 'required|in:basic_salary,gross',
            'taxable' => 'required|boolean',
            'affects_nssf' => 'required|boolean',
            'affects_shif' => 'required|boolean',
            'affects_housing_levy' => 'required|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        // Statutory treatment is configurable for reporting, but this code builder cannot alter
        // the statutory formulas or make legally taxable items exempt from PAYE.
        $code = PayrollCode::create($data + ['tenant_id' => $tenantId, 'active' => true]);
        $this->audit->record('CREATE', 'PayrollCode', $code->id, null, $code->toArray());
        return response()->json(['code' => $code], 201);
    }

    public function updateCode(Request $request, PayrollCode $payrollCode): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless($payrollCode->belongsToTenant($tenantId), 404);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'kind' => 'sometimes|required|in:earning,deduction,employer_contribution,benefit',
            'calculation_method' => 'sometimes|required|in:fixed,percentage',
            'default_amount' => 'sometimes|numeric|min:0',
            'default_percentage' => 'sometimes|numeric|min:0|max:100',
            'percentage_basis' => 'sometimes|required|in:basic_salary,gross',
            'taxable' => 'sometimes|boolean',
            'affects_nssf' => 'sometimes|boolean',
            'affects_shif' => 'sometimes|boolean',
            'affects_housing_levy' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        $before = $payrollCode->toArray();
        $payrollCode->update($data);
        $this->audit->record('UPDATE', 'PayrollCode', $payrollCode->id, $before, $payrollCode->fresh()->toArray());
        return response()->json(['code' => $payrollCode->fresh()]);
    }

    public function assignCode(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'payroll_code_id' => 'required|integer',
            'calculation_method' => 'nullable|in:fixed,percentage',
            'amount' => 'nullable|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'active' => 'sometimes|boolean',
        ]);
        $employee = Employee::query()->forTenant($tenantId)->findOrFail($data['employee_id']);
        $code = PayrollCode::query()->forTenant($tenantId)->where('active', true)->findOrFail($data['payroll_code_id']);
        $assignment = EmployeePayrollItem::updateOrCreate(
            ['employee_id' => $employee->id, 'payroll_code_id' => $code->id],
            $data + ['tenant_id' => $tenantId, 'active' => true]
        );
        $this->audit->record('UPSERT', 'EmployeePayrollItem', $assignment->id, null, $assignment->toArray());
        return response()->json(['assignment' => $assignment->load(['employee','payrollCode'])], 201);
    }

    public function loans(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        return response()->json(['loans' => EmployeeLoan::query()->forTenant($tenantId)->with(['employee:id,first_name,last_name,employee_no','repayments'])->latest()->get()]);
    }

    public function storeLoan(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'loan_number' => ['required','string','max:50',Rule::unique('employee_loans')->where('tenant_id',$tenantId)],
            'loan_type' => 'required|string|max:60',
            'principal_amount' => 'required|numeric|gt:0',
            'annual_interest_rate' => 'nullable|numeric|min:0|max:100',
            'monthly_repayment' => 'required|numeric|gt:0',
            'disbursed_at' => 'nullable|date',
            'first_repayment_date' => 'required|date',
            'term_months' => 'required|integer|min:1|max:600',
            'notes' => 'nullable|string|max:2000',
        ]);
        Employee::query()->forTenant($tenantId)->findOrFail($data['employee_id']);
        $data['annual_interest_rate'] = $data['annual_interest_rate'] ?? 0;
        $loan = EmployeeLoan::create($data + ['tenant_id' => $tenantId, 'status' => 'Pending']);
        $this->audit->record('CREATE', 'EmployeeLoan', $loan->id, null, $loan->toArray());
        return response()->json(['loan' => $loan->load('employee')], 201);
    }

    public function updateLoanStatus(Request $request, EmployeeLoan $employeeLoan): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        abort_unless($employeeLoan->belongsToTenant($tenantId), 404);
        $data = $request->validate(['status' => 'required|in:Active,Cancelled']);
        if ($employeeLoan->status === 'Settled') {
            return response()->json(['message' => 'A settled loan cannot be reactivated or cancelled.'], 422);
        }
        if ($data['status'] === 'Active' && !$employeeLoan->disbursed_at) {
            return response()->json(['message' => 'Set the disbursement date before activating a loan.'], 422);
        }
        $before = $employeeLoan->toArray();
        $employeeLoan->update(['status' => $data['status']]);
        $this->audit->record('UPDATE', 'EmployeeLoan', $employeeLoan->id, $before, $employeeLoan->fresh()->toArray());
        return response()->json(['loan' => $employeeLoan->fresh()->load('employee')]);
    }

    public function fbtRates(Request $request): JsonResponse
    {
        return response()->json(['rates' => FringeBenefitTaxRate::query()->forTenant($this->tenantId($request))->orderByDesc('effective_from')->get()]);
    }

    public function storeFbtRate(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validate([
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'prescribed_annual_rate' => 'required|numeric|min:0|max:100',
            'fbt_tax_rate' => 'nullable|numeric|min:0|max:100',
            'source_reference' => 'required|string|max:255',
        ]);
        $overlap = FringeBenefitTaxRate::query()->forTenant($tenantId)
            ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->where(function ($query) use ($data) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['effective_from']);
            })->exists();
        if ($overlap) {
            return response()->json(['message' => 'This effective period overlaps an existing FBT rate. Close the earlier period before adding another.'], 422);
        }
        $rate = FringeBenefitTaxRate::create($data + ['tenant_id' => $tenantId, 'approved_by' => $request->user()->id]);
        $this->audit->record('CREATE', 'FringeBenefitTaxRate', $rate->id, null, $rate->toArray());
        return response()->json(['rate' => $rate], 201);
    }

    private function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenant_id');
    }
}