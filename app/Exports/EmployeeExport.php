<?php
namespace App\Exports;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
class EmployeeExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private int $tenantId) {}
    public function query(): Builder { return Employee::query()->where('tenant_id',$this->tenantId)->with(['department','branch','designation'])->orderBy('last_name'); }
    public function headings(): array { return ['Employee No','Full Name','Email','Phone','Department','Branch','Designation','Status','Basic Salary']; }
    public function map($employee): array { return [$employee->employee_no,$employee->full_name,$employee->email,$employee->phone,$employee->department?->name,$employee->branch?->name,$employee->designation?->name,$employee->employment_status,$employee->basic_salary]; }
}
