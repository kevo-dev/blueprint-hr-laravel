<?php
namespace App\Filament\Resources\Statutory\NssfRates\Pages;
use App\Filament\Resources\Statutory\NssfRates\NssfRateResource; use Filament\Resources\Pages\ListRecords;
class ListNssfRates extends ListRecords {protected static string $resource=NssfRateResource::class; protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];}}