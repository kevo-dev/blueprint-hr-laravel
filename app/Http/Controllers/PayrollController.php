<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollProcessRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollTransaction;
use App\Services\AuditService;
use App\Services\PayrollCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PayrollController extends Controller
{
    public function __construct(private PayrollCalculationService $calculator, private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant_id');

        return response()->json([
            'periods' => PayrollPeriod::where('tenant_id', $tenant)->latest()->get(),
            'transactions' => PayrollTransaction::where('tenant_id', $tenant)->with(['employee', 'period'])->latest()->limit(200)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);
        $period = PayrollPeriod::create($data + ['tenant_id' => $request->attributes->get('tenant_id'), 'status' => 'Open']);

        return response()->json(['period' => $period], 201);
    }

    public function process(PayrollProcessRequest $request): JsonResponse
    {
        $period = PayrollPeriod::where('tenant_id', $request->attributes->get('tenant_id'))->findOrFail($request->integer('payroll_period_id'));

        try {
            $run = $this->calculator->process($period, $request->user()->id);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->audit->record('PROCESS', 'PayrollRun', $run->id, null, $run->toArray());

        return response()->json(['run' => $run->load('period')]);
    }
}
