<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeePayrollItem;
use App\Models\FringeBenefitTaxRate;
use App\Models\HousingLevyRate;
use App\Models\LoanRepayment;
use App\Models\NssfRate;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollTransaction;
use App\Models\ShifRate;
use App\Models\TaxBracket;
use App\Models\TaxRelief;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollCalculationService
{
    public function process(PayrollPeriod $period, int $userId): PayrollRun
    {
        return DB::transaction(function () use ($period, $userId) {
            $period = PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if (in_array($period->status, ['Processed', 'Locked'], true)) {
                throw new RuntimeException('This payroll period has already been processed or locked.');
            }

            $periodEnd = CarbonImmutable::create((int) $period->year, (int) $period->month, 1)->endOfMonth();
            $periodStart = $periodEnd->startOfMonth();
            $employees = Employee::query()->where('tenant_id', $period->tenant_id)->where('employment_status', 'Active')->get();

            $brackets = $this->effectiveRows(TaxBracket::query()->where('tenant_id', $period->tenant_id), $periodEnd, 'band_order');
            $nssf = $this->effectiveRows(NssfRate::query()->where('tenant_id', $period->tenant_id), $periodEnd, 'lower_limit');
            $shif = $this->effectiveRows(ShifRate::query()->where('tenant_id', $period->tenant_id), $periodEnd, 'effective_from')->first();
            $housing = $this->effectiveRows(HousingLevyRate::query()->where('tenant_id', $period->tenant_id), $periodEnd, 'effective_from')->first();
            $personalRelief = $this->effectiveRows(
                TaxRelief::query()->where('tenant_id', $period->tenant_id)->where('relief_name', 'Personal Relief'),
                $periodEnd, 'relief_name'
            )->sum(fn (TaxRelief $relief) => (float) $relief->monthly_amount);

            $fbtRate = FringeBenefitTaxRate::query()->where('tenant_id', $period->tenant_id)
                ->whereDate('effective_from', '<=', $periodEnd->toDateString())
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart->toDateString()))
                ->orderByDesc('effective_from')->first();

            $totals = ['gross' => 0, 'paye' => 0, 'nssf' => 0, 'shif' => 0, 'housing' => 0, 'net' => 0];

            foreach ($employees as $employee) {
                $basic = (float) $employee->basic_salary;
                $allowances = 0.0;
                $taxableGross = $basic;
                $nssfBase = $basic;
                $shifBase = $basic;
                $housingBase = $basic;
                $otherDeductions = 0.0;
                $componentRows = [];
                $employerFbt = 0.0;

                $items = EmployeePayrollItem::query()->where('tenant_id', $period->tenant_id)
                    ->where('employee_id', $employee->id)->where('active', true)
                    ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $periodEnd->toDateString()))
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart->toDateString()))
                    ->with('payrollCode')->get();

                foreach ($items as $item) {
                    $code = $item->payrollCode;
                    if (!$code || !$code->active) continue;
                    if ($code->effective_from && $code->effective_from->gt($periodEnd)) continue;
                    if ($code->effective_to && $code->effective_to->lt($periodStart)) continue;

                    $method = $item->calculation_method ?: $code->calculation_method;
                    $basis = $code->percentage_basis === 'gross' ? ($basic + $allowances) : $basic;
                    $amount = round($method === 'percentage'
                        ? $basis * (float) ($item->percentage ?: $code->default_percentage) / 100
                        : (float) ($item->amount ?: $code->default_amount), 2);
                    if ($amount <= 0) continue;

                    $componentRows[] = ['code' => $code->code, 'name' => $code->name, 'kind' => $code->kind, 'amount' => $amount, 'taxable' => (bool) $code->taxable];
                    if (in_array($code->kind, ['earning', 'benefit'], true)) {
                        $allowances += $amount;
                        if ($code->taxable) $taxableGross += $amount;
                        if ($code->affects_nssf) $nssfBase += $amount;
                        if ($code->affects_shif) $shifBase += $amount;
                        if ($code->affects_housing_levy) $housingBase += $amount;
                    } elseif ($code->kind === 'deduction') {
                        $otherDeductions += $amount;
                    }
                }

                $gross = $basic + $allowances;
                $nssfAmount = $this->calculateNssf($nssfBase, $nssf);
                $shifAmount = max($shif ? $shifBase * (float) $shif->percentage : $shifBase * 0.0275, $shif ? (float) $shif->min_amount : 300);
                $housingAmount = $housingBase * ($housing ? (float) $housing->employee_percentage : 0.015);
                $taxable = max(0, $taxableGross - $nssfAmount - $shifAmount - $housingAmount);
                $grossTax = $this->progressiveTax($taxable, $brackets);
                $paye = max(0, $grossTax - $personalRelief);

                $loans = EmployeeLoan::query()->where('tenant_id', $period->tenant_id)
                    ->where('employee_id', $employee->id)->where('status', 'Active')
                    ->whereDate('first_repayment_date', '<=', $periodEnd->toDateString())->lockForUpdate()->get();
                foreach ($loans as $loan) {
                    $alreadyRepaid = (float) LoanRepayment::query()->where('employee_loan_id', $loan->id)->sum('amount');
                    $balanceBefore = max(0, (float) $loan->principal_amount - $alreadyRepaid);
                    if ($balanceBefore <= 0) {
                        $loan->update(['status' => 'Settled']);
                        continue;
                    }
                    $alreadyForPeriod = LoanRepayment::query()->where('employee_loan_id', $loan->id)->where('payroll_period_id', $period->id)->exists();
                    if (!$alreadyForPeriod) {
                        $repayment = min($balanceBefore, (float) $loan->monthly_repayment, max(0, $gross - $paye - $nssfAmount - $shifAmount - $housingAmount - $otherDeductions));
                        if ($repayment > 0) {
                            $balanceAfter = max(0, $balanceBefore - $repayment);
                            LoanRepayment::create(['tenant_id' => $period->tenant_id, 'employee_loan_id' => $loan->id, 'payroll_period_id' => $period->id, 'amount' => $this->money($repayment), 'balance_after' => $this->money($balanceAfter)]);
                            $otherDeductions += $repayment;
                            $componentRows[] = ['code' => 'LOAN-'.$loan->loan_number, 'name' => 'Loan repayment', 'kind' => 'deduction', 'amount' => $repayment, 'taxable' => false];
                            if ($balanceAfter <= 0) $loan->update(['status' => 'Settled']);
                        }
                    }
                    if ($fbtRate && $fbtRate->fbt_tax_rate !== null) {
                        $rateGap = max(0, (float) $fbtRate->prescribed_annual_rate - (float) $loan->annual_interest_rate);
                        $deemedMonthlyInterest = $balanceBefore * ($rateGap / 100) / 12;
                        $employerFbt += $deemedMonthlyInterest * ((float) $fbtRate->fbt_tax_rate / 100);
                    }
                }

                $deductions = $paye + $nssfAmount + $shifAmount + $housingAmount + $otherDeductions;
                $net = max(0, $gross - $deductions);

                PayrollTransaction::updateOrCreate(
                    ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                    [
                        'tenant_id' => $period->tenant_id, 'basic_salary' => $this->money($basic),
                        'allowances' => $this->money($allowances), 'gross_pay' => $this->money($gross),
                        'taxable_pay' => $this->money($taxable), 'paye' => $this->money($paye),
                        'personal_relief' => $this->money($personalRelief), 'nssf' => $this->money($nssfAmount),
                        'shif' => $this->money($shifAmount), 'housing_levy' => $this->money($housingAmount),
                        'other_deductions' => $this->money($otherDeductions), 'total_deductions' => $this->money($deductions),
                        'net_pay' => $this->money($net), 'component_breakdown' => $componentRows,
                        'employer_fbt' => $this->money($employerFbt), 'status' => 'Draft',
                    ]
                );

                $totals['gross'] += $gross;
                $totals['paye'] += $paye;
                $totals['nssf'] += $nssfAmount;
                $totals['shif'] += $shifAmount;
                $totals['housing'] += $housingAmount;
                $totals['net'] += $net;
            }

            $run = PayrollRun::create([
                'tenant_id' => $period->tenant_id, 'payroll_period_id' => $period->id,
                'total_employees' => $employees->count(), 'total_gross' => $this->money($totals['gross']),
                'total_paye' => $this->money($totals['paye']), 'total_nssf' => $this->money($totals['nssf']),
                'total_shif' => $this->money($totals['shif']), 'total_housing_levy' => $this->money($totals['housing']),
                'total_net' => $this->money($totals['net']), 'processed_by' => $userId,
                'status' => 'Submitted', 'processed_at' => now(),
            ]);
            $period->update(['status' => 'Processed']);
            return $run->load('period');
        });
    }

    private function effectiveRows($query, CarbonImmutable $periodEnd, string $sortBy): EloquentCollection
    {
        $rows = $query->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $periodEnd->toDateString()))
            ->orderByDesc('effective_from')->orderBy($sortBy)->get();
        if ($rows->isEmpty()) return $rows;
        $effectiveDate = $rows->first()->effective_from?->format('Y-m-d');
        return $rows->filter(fn ($row) => ($row->effective_from?->format('Y-m-d') ?? null) === $effectiveDate)->sortBy($sortBy)->values();
    }

    private function calculateNssf(float $gross, EloquentCollection $rates): float
    {
        if ($rates->isEmpty()) return round(min($gross, 108000) * 0.06, 2);
        $total = 0.0;
        foreach ($rates as $rate) {
            $lower = (float) $rate->lower_limit;
            $upper = $rate->upper_limit === null ? $gross : (float) $rate->upper_limit;
            $total += max(0, min($gross, $upper) - $lower) * (float) $rate->employee_rate;
        }
        return round($total, 2);
    }

    private function progressiveTax(float $taxable, EloquentCollection $brackets): float
    {
        if ($brackets->isEmpty()) return 0.0;
        $tax = 0.0;
        foreach ($brackets as $bracket) {
            $lower = (float) $bracket->lower_limit;
            $upper = $bracket->upper_limit === null ? $taxable : (float) $bracket->upper_limit;
            if ($taxable <= $lower) continue;
            $portion = min($taxable, $upper) - $lower;
            if ($portion > 0) $tax += $portion * (float) $bracket->rate;
            if ($bracket->upper_limit === null || $taxable <= $upper) break;
        }
        return round($tax, 2);
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
