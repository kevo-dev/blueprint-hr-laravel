<?php
namespace App\Filament\Resources\Statutory\TaxBrackets\Pages;
use App\Filament\Resources\Statutory\TaxBrackets\TaxBracketResource; use Filament\Resources\Pages\ListRecords;
class ListTaxBrackets extends ListRecords {protected static string $resource=TaxBracketResource::class; protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];}}