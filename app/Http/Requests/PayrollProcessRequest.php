<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class PayrollProcessRequest extends FormRequest { public function authorize(): bool { return $this->user()?->role?->canProcessPayroll() ?? false; } public function rules(): array { return ['payroll_period_id'=>['required','integer','exists:payroll_periods,id']]; } }
