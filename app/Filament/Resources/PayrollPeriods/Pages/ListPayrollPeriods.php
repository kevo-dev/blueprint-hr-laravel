<?php
namespace App\Filament\Resources\PayrollPeriods\Pages;
use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource; use Filament\Resources\Pages\ListRecords;
class ListPayrollPeriods extends ListRecords { protected static string $resource=PayrollPeriodResource::class; protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];} }