<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leave_types', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days_requested' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
