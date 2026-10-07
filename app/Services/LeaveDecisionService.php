<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveDecisionService
{
    public function decide(LeaveRequest $leaveRequest, User $user, string $status, ?string $comment = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $user, $status, $comment) {
            $model = LeaveRequest::query()
                ->forTenant((int) $user->tenant_id)
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($model->status !== 'Pending') {
                throw ValidationException::withMessages(['status' => 'Only pending requests can be decided.']);
            }

            if ($status === 'Approved') {
                $balance = LeaveBalance::query()
                    ->forTenant((int) $user->tenant_id)
                    ->where([
                        'employee_id' => $model->employee_id,
                        'leave_type_id' => $model->leave_type_id,
                        'year' => $model->start_date->year,
                    ])
                    ->lockForUpdate()
                    ->first();

                if (!$balance || $balance->available_days < (float) $model->days_requested) {
                    throw ValidationException::withMessages(['status' => 'Insufficient leave balance.']);
                }

                $balance->increment('used_days', (float) $model->days_requested);
            }

            $model->update([
                'status' => $status,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'decision_comment' => $comment,
            ]);

            return $model->fresh()->load(['employee', 'leaveType', 'approver']);
        });
    }
}