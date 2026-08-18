<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['message' => 'A tenant context is required.'], 403);
        }
        $request->attributes->set('tenant_id', (int) $user->tenant_id);
        return $next($request);
    }
}
