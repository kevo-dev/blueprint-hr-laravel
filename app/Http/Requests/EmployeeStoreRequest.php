<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->canManagePeople() ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $routeEmployee = $this->route('employee');
        $ignoreId = $routeEmployee instanceof Employee ? $routeEmployee->getKey() : $routeEmployee;
        $tenantOwned = static fn (string $table) => Rule::exists($table, 'id')
            ->where(static fn ($query) => $query->where('tenant_id', $tenantId));

        return [
            'employee_no' => ['required', 'string', 'max:40', Rule::unique('employees', 'employee_no')->where(fn ($query) => $query->where('tenant_id', $tenantId))->ignore($ignoreId)],
            'payroll_no' => ['nullable', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:30'],
            'dob' => ['nullable', 'date'],
            'id_no' => ['nullable', 'string', 'max:50'],
            'kra_pin' => ['nullable', 'string', 'max:32'],
            'nssf_no' => ['nullable', 'string', 'max:50'],
            'shif_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'branch_id' => ['nullable', 'integer', $tenantOwned('branches')],
            'department_id' => ['nullable', 'integer', $tenantOwned('departments')],
            'designation_id' => ['nullable', 'integer', $tenantOwned('designations')],
            'grade_id' => ['nullable', 'integer', $tenantOwned('grades')],
            'employment_type_id' => ['nullable', 'integer', $tenantOwned('employment_types')],
            'employment_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'in:Active,Inactive,On Leave,Terminated'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_branch' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:80'],
        ];
    }
}
