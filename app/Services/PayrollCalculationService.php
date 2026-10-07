<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\HousingLevyRate;
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

            $employees = Employee::query()
                ->where('tenant_id', $period->tenant_id)
                ->where('employment_status', 'Active')
                ->get();

            $brackets = $this->effectiveRows(
                TaxBracket::query()->where('tenant_id', $period->tenant_id),
                $periodEnd,
                'band_order'
            );

            $nssf = $this->effectiveRows(
                NssfRate::query()->where('tenant_id', $period->tenant_id),
                $periodEnd,
                'lower_limit'
            );

            $shif = $this->effectiveRows(
                ShifRate::query()->where('tenant_id', $period->tenant_id),
                $periodEnd,
                'effective_from'
            )->first();

            $housing = $this->effectiveRows(
                HousingLevyRate::query()->where('tenant_id', $period->tenant_id),
                $periodEnd,
                'effective_from'
            )->first();

            $personalRelief = $this->effectiveRows(
                TaxRelief::query()
                    ->where('tenant_id', $period->tenant_id)
                    ->where('relief_name', 'Personal Relief'),
                $periodEnd,
                'relief_name'
            )->sum(fn (TaxRelief $relief) => (float) $relief->monthly_amount);

            $totals = ['gross' => 0, 'paye' => 0, 'nssf' => 0, 'shif' => 0, 'housing' => 0, 'net' => 0];

            foreach ($employees as $employee) {
                $basic = (float) $employee->basic_salary;
                $allowances = 0.0;
                $gross = $basic + $allowances;

                $nssfAmount = $this->calculateNssf($gross, $nssf);
                $shifAmount = max(
                    $shif ? $gross * (float) $shif->percentage : $gross * 0.0275,
                    $shif ? (float) $shif->min_amount : 300
                );
                $housingAmount = $gross * ($housing ? (float) $housing->employee_percentage : 0.015);

                // Kenyan PAYE computation allows NSSF, SHIF and Affordable Housing Levy
                // contributions as deductions when determining taxable employment income.
                $taxable = max(0, $gross - $nssfAmount - $shifAmount - $housingAmount);
                $grossTax = $this->progressiveTax($taxable, $brackets);
                $paye = max(0, $grossTax - $personalRelief);

                $deductions = $paye + $nssfAmount + $shifAmount + $housingAmount;
                $net = max(0, $gross - $deductions);

                PayrollTransaction::updateOrCreate(
                    ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                    [
                        'tenant_id' => $period->tenant_id,
                        'basic_salary' => $this->money($basic),
                        'allowances' => $this->money($allowances),
                        'gross_pay' => $this->money($gross),
                        'taxable_pay' => $this->money($taxable),
                        'paye' => $this->money($paye),
                        'personal_relief' => $this->money($personalRelief),
                        'nssf' => $this->money($nssfAmount),
                        'shif' => $this->money($shifAmount),
                        'housing_levy' => $this->money($housingAmount),
                        'other_deductions' => '0.00',
                        'total_deductions' => $this->money($deductions),
                        'net_pay' => $this->money($net),
                        'status' => 'Draft',
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
                'tenant_id' => $period->tenant_id,
                'payroll_period_id' => $period->id,
                'total_employees' => $employees->count(),
                'total_gross' => $this->money($totals['gross']),
                'total_paye' => $this->money($totals['paye']),
                'total_nssf' => $this->money($totals['nssf']),
                'total_shif' => $this->money($totals['shif']),
                'total_housing_levy' => $this->money($totals['housing']),
                'total_net' => $this->money($totals['net']),
                'processed_by' => $userId,
                'status' => 'Submitted',
                'processed_at' => now(),
            ]);

            $period->update(['status' => 'Processed']);

            return $run->load('period');
        });
    }

    private function effectiveRows($query, CarbonImmutable $periodEnd, string $sortBy): EloquentCollection
    {
        $rows = $query
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $periodEnd->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderBy($sortBy)
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $effectiveDate = $rows->first()->effective_from?->format('Y-m-d');

        return $rows
            ->filter(fn ($row) => ($row->effective_from?->format('Y-m-d') ?? null) === $effectiveDate)
            ->sortBy($sortBy)
            ->values();
    }

    private function calculateNssf(float $gross, EloquentCollection $rates): float
    {
        if ($rates->isEmpty()) {
            return round(min($gross, 108000) * 0.06, 2);
        }

        $total = 0.0;

        foreach ($rates as $rate) {
            $lower = (float) $rate->lower_limit;
            $upper = $rate->upper_limit === null ? $gross : (float) $rate->upper_limit;
            $base = max(0, min($gross, $upper) - $lower);
            $total += $base * (float) $rate->employee_rate;
        }

        return round($total, 2);
    }

    private function progressiveTax(float $taxable, EloquentCollection $brackets): float
    {
        if ($brackets->isEmpty()) {
            return 0.0;
        }

        $tax = 0.0;

        foreach ($brackets as $bracket) {
            $lower = (float) $bracket->lower_limit;
            $upper = $bracket->upper_limit === null ? $taxable : (float) $bracket->upper_limit;

            if ($taxable <= $lower) {
                continue;
            }

            $portion = min($taxable, $upper) - $lower;

            if ($portion > 0) {
                $tax += $portion * (float) $bracket->rate;
            }

            if ($bracket->upper_limit === null || $taxable <= $upper) {
                break;
            }
        }

        return round($tax, 2);
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
