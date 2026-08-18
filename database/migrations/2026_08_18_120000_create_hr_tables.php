<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('kra_pin', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('subdomain')->unique();
            $table->string('status')->default('Active');
            $table->string('currency', 3)->default('KES');
            $table->string('timezone')->default('Africa/Nairobi');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('kra_pin', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'company_name']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('location')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('level', 20)->nullable();
            $table->decimal('min_salary', 15, 2)->default(0);
            $table->decimal('max_salary', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('employee_no', 40);
            $table->string('payroll_no', 40)->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('gender', 30)->nullable();
            $table->date('dob')->nullable();
            $table->string('id_no', 50)->nullable();
            $table->string('kra_pin', 32)->nullable();
            $table->string('nssf_no', 50)->nullable();
            $table->string('shif_no', 50)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('employment_date')->nullable();
            $table->string('employment_status')->default('Active');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'employee_no']);
            $table->index(['tenant_id', 'employment_status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'entity_type', 'created_at']);
        });

        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('band_order');
            $table->decimal('lower_limit', 15, 2)->default(0);
            $table->decimal('upper_limit', 15, 2)->nullable();
            $table->decimal('rate', 8, 6);
            $table->date('effective_from')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'band_order']);
        });

        Schema::create('tax_reliefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('relief_name');
            $table->decimal('monthly_amount', 15, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('nssf_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tier_name');
            $table->decimal('lower_limit', 15, 2)->default(0);
            $table->decimal('upper_limit', 15, 2)->nullable();
            $table->decimal('employee_rate', 8, 6);
            $table->decimal('employer_rate', 8, 6);
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('shif_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 8, 6);
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('housing_levy_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('employee_percentage', 8, 6);
            $table->decimal('employer_percentage', 8, 6);
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('default_days')->default(0);
            $table->string('paid', 10)->default('Yes');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->decimal('carried_forward', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 8, 2);
            $table->text('reason')->nullable();
            $table->string('status')->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('status')->default('Open');
            $table->timestamps();
            $table->unique(['tenant_id', 'month', 'year']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_paye', 15, 2)->default(0);
            $table->decimal('total_nssf', 15, 2)->default(0);
            $table->decimal('total_shif', 15, 2)->default(0);
            $table->decimal('total_housing_levy', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('Draft');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'payroll_period_id']);
        });

        Schema::create('payroll_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('allowances', 15, 2)->default(0);
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('taxable_pay', 15, 2)->default(0);
            $table->decimal('paye', 15, 2)->default(0);
            $table->decimal('personal_relief', 15, 2)->default(0);
            $table->decimal('nssf', 15, 2)->default(0);
            $table->decimal('shif', 15, 2)->default(0);
            $table->decimal('housing_levy', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->string('status')->default('Draft');
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('payroll_transactions');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('housing_levy_rates');
        Schema::dropIfExists('shif_rates');
        Schema::dropIfExists('nssf_rates');
        Schema::dropIfExists('tax_reliefs');
        Schema::dropIfExists('tax_brackets');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('employment_types');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('tenants');
    }
};
