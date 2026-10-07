<?php

namespace Tests\Feature;

use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('BLUEPRINT_DEMO_PASSWORD=testing-password');
        parent::setUp();
        $this->seed();
    }

    public function test_current_statutory_tables_are_used_when_processing_payroll(): void
    {
        $period = PayrollPeriod::query()
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->where('status', 'Open')
            ->firstOrFail();

        $admin = User::query()->where('email', 'admin@blueprint.test')->firstOrFail();

        $run = app(PayrollCalculationService::class)->process($period, (int) $admin->id);

        $this->assertInstanceOf(PayrollRun::class, $run);
        $this->assertSame('Processed', $period->fresh()->status);
        $this->assertSame(2, $run->total_employees);

        $george = PayrollTransaction::query()
            ->where('payroll_period_id', $period->id)
            ->whereHas('employee', fn ($q) => $q->where('employee_no', 'EMP-2026-001'))
            ->firstOrFail();

        $this->assertSame('6480.00', $george->nssf);
        $this->assertSame('4950.00', $george->shif);
        $this->assertSame('2700.00', $george->housing_levy);
        $this->assertSame('165870.00', $george->taxable_pay);
        $this->assertSame('42144.35', $george->paye);
        $this->assertSame('123725.65', $george->net_pay);
    }

    public function test_processed_period_cannot_be_processed_twice(): void
    {
        $period = PayrollPeriod::query()
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->where('status', 'Open')
            ->firstOrFail();

        $admin = User::query()->where('email', 'admin@blueprint.test')->firstOrFail();
        $service = app(PayrollCalculationService::class);

        $service->process($period, (int) $admin->id);

        $this->expectException(\RuntimeException::class);
        $service->process($period->fresh(), (int) $admin->id);
    }
}
