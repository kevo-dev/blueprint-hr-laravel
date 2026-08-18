<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService
{
    public function record(string $action, string $entityType, ?int $entityId, ?array $before = null, ?array $after = null, ?string $details = null): void
    {
        $user = request()->user();
        AuditLog::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'details' => $details,
            'ip_address' => request()->ip(),
        ]);
    }
}
