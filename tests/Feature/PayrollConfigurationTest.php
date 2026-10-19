<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_code_is_scoped_to_authenticated_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantA->id]);
        $this->actingAs($user);

        $code = PayrollCode::create([
            'tenant_id' => $tenantB->id, 'code' => 'OTHER', 'name' => 'Other tenant code',
            'kind' => 'earning', 'calculation_method' => 'fixed', 'default_amount' => 100,
            'percentage_basis' => 'basic_salary', 'taxable' => true,
        ]);

        $response = $this->getJson('/api/payroll/configuration/codes');
        $response->assertOk()->assertJsonCount(0, 'codes');
        $this->assertNotNull($code->fresh());
    }
}