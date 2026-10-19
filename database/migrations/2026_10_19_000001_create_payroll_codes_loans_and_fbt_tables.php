<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('kind', 30); // earning, deduction, employer_contribution, benefit
            $table->string('calculation_method', 30)->default('fixed'); // fixed, percentage
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->decimal('default_percentage', 8, 4)->default(0);
            $table->string('percentage_basis', 30)->default('basic_salary'); // basic_salary, gross
            $table->boolean('taxable')->default(true);
            $table->boolean('affects_nssf')->default(false);
            $table->boolean('affects_shif')->default(false);
            $table->boolean('affects_housing_levy')->default(false);
            $table->boolean('active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'active', 'effective_from']);
        });

        Schema::create('employee_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_code_id')->constrained('payroll_codes')->cascadeOnDelete();
            $table->string('calculation_method', 30)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('percentage', 8, 4)->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['employee_id', 'payroll_code_id']);
            $table->index(['tenant_id', 'employee_id', 'active']);
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('loan_number', 50);
            $table->string('loan_type', 60)->default('Staff loan');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('annual_interest_rate', 8, 4)->default(0);
            $table->decimal('monthly_repayment', 15, 2);
            $table->date('disbursed_at')->nullable();
            $table->date('first_repayment_date');
            $table->unsignedSmallInteger('term_months');
            $table->string('status', 20)->default('Pending'); // Pending, Active, Settled, Cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'loan_number']);
            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_component', 15, 2)->default(0);
            $table->decimal('interest_component', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2);
            $table->timestamps();
            $table->unique(['employee_loan_id', 'payroll_period_id']);
        });

        Schema::create('fringe_benefit_tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('prescribed_annual_rate', 8, 4);
            $table->decimal('fbt_tax_rate', 8, 4)->nullable();
            $table->string('source_reference')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'effective_from', 'effective_to']);
        });

        Schema::table('payroll_transactions', function (Blueprint $table) {
            $table->json('component_breakdown')->nullable();
            $table->decimal('employer_fbt', 15, 2)->default(0);
        });
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->decimal('total_employer_fbt', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn('total_employer_fbt');
        });
        Schema::table('payroll_transactions', function (Blueprint $table) {
            $table->dropColumn(['component_breakdown', 'employer_fbt']);
        });
        Schema::dropIfExists('fringe_benefit_tax_rates');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('employee_payroll_items');
        Schema::dropIfExists('payroll_codes');
    }
};