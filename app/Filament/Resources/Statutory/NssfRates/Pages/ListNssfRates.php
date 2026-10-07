<?php
namespace App\Filament\Resources\Statutory\NssfRates\Pages;
use App\Filament\Imports\NssfRateImporter; use App\Filament\Resources\Statutory\NssfRates\NssfRateResource; use Filament\Resources\Pages\ListRecords;
class ListNssfRates extends ListRecords {protected static string $resource=NssfRateResource::class; protected function getHeaderActions():array{return [\Filament\Actions\ImportAction::make()->label('Import NSSF Table')->importer(NssfRateImporter::class)->visible(fn()=>auth()->user()?->role?->canProcessPayroll()),\Filament\Actions\CreateAction::make()];}}