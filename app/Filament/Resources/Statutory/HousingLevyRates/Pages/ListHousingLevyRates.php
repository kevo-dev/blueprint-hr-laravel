<?php
namespace App\Filament\Resources\Statutory\HousingLevyRates\Pages;
use App\Filament\Resources\Statutory\HousingLevyRates\HousingLevyRateResource; use Filament\Resources\Pages\ListRecords;
class ListHousingLevyRates extends ListRecords {protected static string $resource=HousingLevyRateResource::class; protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];}}