<?php
namespace App\Filament\Resources\LeaveTypes\Pages;
use App\Filament\Resources\LeaveTypes\LeaveTypeResource;
use App\Filament\Resources\Pages\TenantCreateRecord;
class CreateLeaveType extends TenantCreateRecord { protected static string $resource = LeaveTypeResource::class; }