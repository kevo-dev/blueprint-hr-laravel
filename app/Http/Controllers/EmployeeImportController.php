<?php

namespace App\\Http\\Controllers;

use App\\Imports\\EmployeeRowsImport;
use App\\Models\\Employee;
use App\\Services\\AuditService;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Arr;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Validator;
use Illuminate\\Validation\\Rule;
use Illuminate\\Validation\\ValidationException;
use Maatwebsite\\Excel\\Facades\\Excel;

class EmployeeImportController extends Controller
{
    private const FIELDS = [
        'employee_no', 'payroll_no', 'first_name', 'middle_name', 'last_name',
        'gender', 'dob', 'id_no', 'kra_pin', 'nssf_no', 'shif_no', 'phone',
        'email', 'branch_id', 'department_id', 'designation_id', 'grade_id',
        'employment_type_id', 'employment_date', 'employment_status',
        'basic_salary', 'bank_name', 'bank_branch', 'account_number',
    ];

    public function __invoke(Request $request, AuditService $audit): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $tenantId = (int) $request->attributes->get('tenant_id');
        $import = new EmployeeRowsImport();
        $rows = Excel::toCollection($import, $request->file('file'))->first() ?? collect();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet contains no employee rows.']);
        }

        $prepared = [];
        $errors = [];
        $seen = [];
        $tenantOwned = static fn (string $table) => Rule::exists($table, 'id')
            ->where(static fn ($query) => $query->where('tenant_id', $tenantId));

        $rules = [
            'employee_no' => ['required', 'string', 'max:40'],
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
            'employment_status' => ['nullable', Rule::in(['Active', 'Inactive', 'On Leave', 'Terminated'])],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_branch' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:80'],
        ];

        foreach ($rows as $index => $row) {
            $data = Arr::only(collect($row)->toArray(), self::FIELDS);
            if (collect($data)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                continue;
            }

            $rowNumber = $index + 2;
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["row_{$rowNumber}.{$field}"] = $messages;
                }
                continue;
            }

            $key = mb_strtolower(trim((string) $data['employee_no']));
            if (isset($seen[$key])) {
                $errors["row_{$rowNumber}.employee_no"] = ['Duplicate employee number in this upload; first seen on row '.$seen[$key].'.'];
                continue;
            }

            $seen[$key] = $rowNumber;
            $prepared[] = $data;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if ($prepared === []) {
            throw ValidationException::withMessages(['file' => 'No valid employee rows were found in the spreadsheet.']);
        }

        $result = DB::transaction(function () use ($prepared, $tenantId, $audit) {
            $created = 0;
            $updated = 0;

            foreach ($prepared as $data) {
                $employee = Employee::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_no', $data['employee_no'])
                    ->first();

                if ($employee) {
                    $before = $employee->toArray();
                    if ($employee->trashed()) {
                        $employee->restore();
                    }
                    $employee->fill($data);
                    $employee->save();
                    $audit->record('IMPORT_UPDATE', 'Employee', $employee->id, $before, $employee->fresh()->toArray());
                    $updated++;
                } else {
                    $employee = Employee::create($data + ['tenant_id' => $tenantId]);
                    $audit->record('IMPORT_CREATE', 'Employee', $employee->id, null, $employee->toArray());
                    $created++;
                }
            }

            return compact('created', 'updated');
        });

        return response()->json([
            'message' => 'Employee spreadsheet imported successfully.',
            'rows_processed' => count($prepared),
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]);
    }
}
