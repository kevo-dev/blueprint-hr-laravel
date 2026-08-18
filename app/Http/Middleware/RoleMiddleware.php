<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $role = $request->user()?->role?->value ?? (string) $request->user()?->role;
        if (!$request->user() || !in_array($role, $roles, true)) {
            return response()->json(['message' => 'You are not authorized for this action.'], 403);
        }
        return $next($request);
    }
}
