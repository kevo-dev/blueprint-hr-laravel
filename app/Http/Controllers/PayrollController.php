<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollProcessRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use App\Services\AuditService;
use App\Services\PayrollCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class PayrollController extends Controller
{
    public function __construct(private PayrollCalculationService $calculator, private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayrollPeriod::class);
        Gate::authorize('viewAny', PayrollTransaction::class);
        $tenantId = (int) $request->attributes->get('tenant_id');
        $user = $request->user();

        return response()->json([
            'periods' => PayrollPeriod::query()->forTenant($tenantId)->latest()->get(),
            'transactions' => PayrollTransaction::query()
                ->forTenant($tenantId)
                ->when($user->hasRole('Employee'), fn ($query) => $query->where('employee_id', $user->employee_id))
                ->with(['employee', 'period'])
                ->latest()
                ->limit(200)
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', PayrollPeriod::class);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);
        $period = PayrollPeriod::create($data + [
            'tenant_id' => (int) $request->attributes->get('tenant_id'),
            'status' => 'Open',
        ]);

        return response()->json(['period' => $period], 201);
    }

    public function process(PayrollProcessRequest $request): JsonResponse
    {
        $period = PayrollPeriod::query()
            ->forTenant((int) $request->attributes->get('tenant_id'))
            ->findOrFail($request->integer('payroll_period_id'));
        Gate::authorize('process', $period);

        try {
            $run = $this->calculator->process($period, $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->audit->record('PROCESS', 'PayrollRun', $run->id, null, $run->toArray());

        return response()->json(['run' => $run->load('period')]);
    }
}
