<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveRequestStoreRequest;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AuditService;
use App\Services\LeaveDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    public function __construct(private AuditService $audit, private LeaveDecisionService $decisions) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');
        $user = $request->user();
        $employeeId = $user->hasRole('Employee') ? $user->employee_id : $request->integer('employee_id');

        return response()->json([
            'types' => LeaveType::query()->forTenant($tenantId)->get(),
            'balances' => LeaveBalance::query()->forTenant($tenantId)->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))->with(['employee', 'leaveType'])->where('year', now()->year)->get(),
            'requests' => LeaveRequest::query()->forTenant($tenantId)->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))->with(['employee', 'leaveType', 'approver'])->latest()->get(),
        ]);
    }

    public function store(LeaveRequestStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', LeaveRequest::class);
        $tenantId = (int) $request->attributes->get('tenant_id');
        $user = $request->user();
        $data = $request->validated();
        $employeeId = $user->hasRole('Employee') ? $user->employee_id : ($data['employee_id'] ?? null);

        if (!$employeeId) {
            throw ValidationException::withMessages(['employee_id' => 'An employee is required.']);
        }

        $employee = Employee::query()->forTenant($tenantId)->findOrFail($employeeId);
        $type = LeaveType::query()->forTenant($tenantId)->findOrFail($data['leave_type_id']);
        $balance = LeaveBalance::query()->forTenant($tenantId)->where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => now()->year])->first();

        if ($balance && $balance->available_days < (float) $data['days_requested']) {
            throw ValidationException::withMessages(['days_requested' => 'The requested days exceed the available balance.']);
        }

        $requestModel = LeaveRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_requested' => $data['days_requested'],
            'reason' => $data['reason'] ?? null,
            'status' => 'Pending',
        ]);
        $this->audit->record('CREATE', 'LeaveRequest', $requestModel->id, null, $requestModel->toArray());

        return response()->json(['request' => $requestModel->load(['employee', 'leaveType'])], 201);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless($leaveRequest->belongsToTenant((int) $request->attributes->get('tenant_id')), 404);
        Gate::authorize('decide', $leaveRequest);
        $data = $request->validate([
            'status' => 'required|in:Approved,Rejected,Cancelled',
            'decision_comment' => 'nullable|string|max:2000',
        ]);

        $result = $this->decisions->decide($leaveRequest, $request->user(), $data['status'], $data['decision_comment'] ?? null);
        $this->audit->record('STATUS_CHANGE', 'LeaveRequest', $result->id, $leaveRequest->toArray(), $result->toArray());

        return response()->json(['request' => $result]);
    }
}
