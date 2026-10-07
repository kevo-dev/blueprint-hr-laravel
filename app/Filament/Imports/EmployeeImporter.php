<?php
namespace App\Filament\Imports;

use App\Models\Employee;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\Rule;

class EmployeeImporter extends Importer
{
    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        $tenantId = (int) auth()->user()?->tenant_id;

        return [
            ImportColumn::make('employee_no')->requiredMapping()->rules(['required','string','max:50']),
            ImportColumn::make('payroll_no')->rules(['nullable','string','max:50']),
            ImportColumn::make('first_name')->requiredMapping()->rules(['required','string','max:100']),
            ImportColumn::make('middle_name')->rules(['nullable','string','max:100']),
            ImportColumn::make('last_name')->requiredMapping()->rules(['required','string','max:100']),
            ImportColumn::make('gender')->rules(['nullable','in:Male,Female,Other']),
            ImportColumn::make('dob')->rules(['nullable','date']),
            ImportColumn::make('id_no')->rules(['nullable','string','max:50']),
            ImportColumn::make('kra_pin')->rules(['nullable','string','max:50']),
            ImportColumn::make('nssf_no')->rules(['nullable','string','max:50']),
            ImportColumn::make('shif_no')->rules(['nullable','string','max:50']),
            ImportColumn::make('phone')->rules(['nullable','string','max:30']),
            ImportColumn::make('email')->rules(['nullable','email','max:150']),
            ImportColumn::make('branch_id')->rules(['nullable','integer',Rule::exists('branches','id')->where(fn ($q) => $q->where('tenant_id',$tenantId))]),
            ImportColumn::make('department_id')->rules(['nullable','integer',Rule::exists('departments','id')->where(fn ($q) => $q->where('tenant_id',$tenantId))]),
            ImportColumn::make('designation_id')->rules(['nullable','integer',Rule::exists('designations','id')->where(fn ($q) => $q->where('tenant_id',$tenantId))]),
            ImportColumn::make('grade_id')->rules(['nullable','integer',Rule::exists('grades','id')->where(fn ($q) => $q->where('tenant_id',$tenantId))]),
            ImportColumn::make('employment_type_id')->rules(['nullable','integer',Rule::exists('employment_types','id')->where(fn ($q) => $q->where('tenant_id',$tenantId))]),
            ImportColumn::make('employment_status')->rules(['required','in:Active,Inactive,Terminated,On Leave']),
            ImportColumn::make('employment_date')->rules(['nullable','date']),
            ImportColumn::make('basic_salary')->rules(['nullable','numeric','min:0']),
            ImportColumn::make('bank_name')->rules(['nullable','string','max:150']),
            ImportColumn::make('bank_branch')->rules(['nullable','string','max:150']),
            ImportColumn::make('account_number')->rules(['nullable','string','max:100']),
        ];
    }

    public function resolveRecord(): ?Employee
    {
        $tenantId = (int) auth()->user()?->tenant_id;
        return Employee::withoutGlobalScopes()->where('tenant_id',$tenantId)->where('employee_no',$this->data['employee_no'] ?? '')->first() ?? new Employee();
    }

    protected function beforeSave(): void
    {
        $this->record->tenant_id = (int) auth()->user()?->tenant_id;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Employee import completed: '.number_format($import->successful_rows).' '.str('employee')->plural($import->successful_rows).' imported.';
        if ($failed = $import->getFailedRowsCount()) $body .= ' '.number_format($failed).' '.str('row')->plural($failed).' failed.';
        return $body;
    }
}