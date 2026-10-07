<?php
namespace App\Filament\Resources\Statutory\TaxReliefs\Pages;
use App\Filament\Resources\Statutory\TaxReliefs\TaxReliefResource; use Filament\Resources\Pages\ListRecords;
class ListTaxReliefs extends ListRecords {protected static string $resource=TaxReliefResource::class; protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];}}