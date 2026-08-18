<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmploymentType;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');

        return response()->json([
            'branches' => Branch::query()->forTenant($tenantId)->with('departments')->get(),
            'departments' => Department::query()->forTenant($tenantId)->with('branch')->get(),
            'designations' => Designation::query()->forTenant($tenantId)->get(),
            'grades' => Grade::query()->forTenant($tenantId)->get(),
            'employment_types' => EmploymentType::query()->forTenant($tenantId)->get(),
        ]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        Gate::authorize('manageOrganization');
        $tenantId = (int) $request->attributes->get('tenant_id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', Rule::unique('branches')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['branch' => Branch::create($data + ['tenant_id' => $tenantId])], 201);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        Gate::authorize('manageOrganization');
        $tenantId = (int) $request->attributes->get('tenant_id');
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
