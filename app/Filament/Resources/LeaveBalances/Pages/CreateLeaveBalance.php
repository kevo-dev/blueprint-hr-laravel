<?php
namespace App\Filament\Resources\LeaveBalances\Pages;
use App\Filament\Resources\LeaveBalances\LeaveBalanceResource;
use App\Filament\Resources\Pages\TenantCreateRecord;
class CreateLeaveBalance extends TenantCreateRecord { protected static string $resource = LeaveBalanceResource::class; }