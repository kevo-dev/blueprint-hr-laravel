<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            AuditLog::query()
                ->forTenant((int) $request->attributes->get('tenant_id'))
                ->with('user:id,name')
                ->latest()
                ->paginate(50)
        );
    }
}
