<?php
namespace App\Filament\Resources\Statutory\TaxReliefs\Pages;
use App\Filament\Imports\TaxReliefImporter; use App\Filament\Resources\Statutory\TaxReliefs\TaxReliefResource; use Filament\Resources\Pages\ListRecords;
class ListTaxReliefs extends ListRecords {protected static string $resource=TaxReliefResource::class; protected function getHeaderActions():array{return [\Filament\Actions\ImportAction::make()->label('Import Relief Table')->importer(TaxReliefImporter::class)->visible(fn()=>auth()->user()?->role?->canProcessPayroll()),\Filament\Actions\CreateAction::make()];}}