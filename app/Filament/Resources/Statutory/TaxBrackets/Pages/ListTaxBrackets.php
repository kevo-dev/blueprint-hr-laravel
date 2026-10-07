<?php
namespace App\Filament\Resources\Statutory\TaxBrackets\Pages;
use App\Filament\Imports\TaxBracketImporter; use App\Filament\Resources\Statutory\TaxBrackets\TaxBracketResource; use Filament\Resources\Pages\ListRecords;
class ListTaxBrackets extends ListRecords {protected static string $resource=TaxBracketResource::class; protected function getHeaderActions():array{return [\Filament\Actions\ImportAction::make()->label('Import Tax Table')->importer(TaxBracketImporter::class)->visible(fn()=>auth()->user()?->role?->canProcessPayroll()),\Filament\Actions\CreateAction::make()];}}