<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmploymentType;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant_id');

        return response()->json([
            'branches' => Branch::where('tenant_id', $tenant)->with('departments')->get(),
            'departments' => Department::where('tenant_id', $tenant)->with('branch')->get(),
            'designations' => Designation::where('tenant_id', $tenant)->get(),
            'grades' => Grade::where('tenant_id', $tenant)->get(),
            'employment_types' => EmploymentType::where('tenant_id', $tenant)->get(),
        ]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', Rule::unique('branches')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['branch' => Branch::create($data + ['tenant_id' => $tenantId])], 201);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', Rule::unique('departments')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        return response()->json(['department' => Department::create($data + ['tenant_id' => $tenantId])], 201);
    }
}
