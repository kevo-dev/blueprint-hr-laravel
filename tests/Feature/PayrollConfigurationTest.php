<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PayrollCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PayrollConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_codes_are_scoped_to_authenticated_tenant(): void
    {
        $tenantA = Tenant::create(['company_name' => 'Tenant A', 'subdomain' => 'tenant-a-test']);
        $tenantB = Tenant::create(['company_name' => 'Tenant B', 'subdomain' => 'tenant-b-test']);
        $user = User::create(['name' => 'Payroll Manager', 'email' => 'payroll-test@example.test', 'password' => Hash::make('secret')]);
        $user->tenant_id = $tenantA->id;
        $user->role = Role::PayrollManager;
        $user->save();

        PayrollCode::create([
            'tenant_id' => $tenantB->id, 'code' => 'OTHER', 'name' => 'Other tenant code',
            'kind' => 'earning', 'calculation_method' => 'fixed', 'default_amount' => 100,
            'percentage_basis' => 'basic_salary', 'taxable' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payroll/configuration/codes')
            ->assertOk()
            ->assertJsonCount(0, 'codes');
    }
}
