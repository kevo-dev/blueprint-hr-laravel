<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('Employee')->after('email');
            $table->foreignId('employee_id')->nullable()->after('role')->constrained('employees')->nullOnDelete();
            $table->boolean('must_change_password')->default(false)->after('employee_id');
            $table->string('phone', 40)->nullable()->after('must_change_password');
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'role', 'employee_id', 'must_change_password', 'phone']);
        });
    }
};
