<?php
namespace App\Http\Controllers;
use App\Http\Requests\EmployeeStoreRequest;
use App\Models\Employee;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class EmployeeController extends Controller
{
    public function __construct(private AuditService $audit) {}
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()->where('tenant_id',$request->attributes->get('tenant_id'))->with(['branch','department','designation','grade']);
        if ($request->user()->hasRole('Employee') && $request->user()->employee_id) $query->whereKey($request->user()->employee_id);
        if ($search = $request->string('search')->trim()->toString()) $query->where(fn($q)=>$q->where('first_name','like',"%$search%")->orWhere('last_name','like',"%$search%")->orWhere('employee_no','like',"%$search%"));
        return response()->json($query->orderBy('last_name')->paginate(25));
    }
    public function store(EmployeeStoreRequest $request): JsonResponse { $employee = Employee::create($request->validated()+['tenant_id'=>$request->attributes->get('tenant_id')]); $this->audit->record('CREATE','Employee',$employee->id,null,$employee->toArray()); return response()->json(['employee'=>$employee->load(['branch','department','designation','grade'])],201); }
    public function show(Request $request, Employee $employee): JsonResponse { abort_unless($employee->tenant_id === (int)$request->attributes->get('tenant_id'),404); if ($request->user()->hasRole('Employee') && $request->user()->employee_id !== $employee->id) abort(403); return response()->json(['employee'=>$employee->load(['branch','department','designation','grade','leaveBalances.leaveType','payrollTransactions.period'])]); }
    public function update(EmployeeStoreRequest $request, Employee $employee): JsonResponse { abort_unless($employee->tenant_id === (int)$request->attributes->get('tenant_id'),404); $before=$employee->toArray(); $employee->update($request->validated()); $this->audit->record('UPDATE','Employee',$employee->id,$before,$employee->fresh()->toArray()); return response()->json(['employee'=>$employee->fresh()->load(['branch','department','designation','grade'])]); }
    public function destroy(Request $request, Employee $employee): JsonResponse { abort_unless($employee->tenant_id === (int)$request->attributes->get('tenant_id'),404); $before=$employee->toArray(); $employee->update(['employment_status'=>'Inactive']); $employee->delete(); $this->audit->record('DEACTIVATE','Employee',$employee->id,$before,null); return response()->json(['message'=>'Employee deactivated.']); }
}
