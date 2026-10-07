<?php
namespace App\Filament\Resources\Statutory\ShifRates\Pages;
use App\Filament\Resources\Statutory\ShifRates\ShifRateResource; use Filament\Resources\Pages\ListRecords;
class ListShifRates extends ListRecords {protected static string $resource=ShifRateResource::class; protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];}}