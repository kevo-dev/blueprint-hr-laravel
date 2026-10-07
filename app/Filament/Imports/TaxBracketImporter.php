<?php
namespace App\Filament\Imports;
use App\Models\TaxBracket; use Filament\Actions\Imports\ImportColumn; use Filament\Actions\Imports\Importer; use Filament\Actions\Imports\Models\Import;
class TaxBracketImporter extends Importer {
 protected static ?string $model=TaxBracket::class;
 public static function getColumns():array{return [
  ImportColumn::make('band_order')->requiredMapping()->rules(['required','integer','min:1']),
  ImportColumn::make('lower_limit')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('upper_limit')->rules(['nullable','numeric','gte:lower_limit']),
  ImportColumn::make('rate')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('effective_from')->requiredMapping()->rules(['required','date']),
 ];}
 public function resolveRecord():?TaxBracket{return TaxBracket::query()->where('tenant_id',(int)auth()->user()?->tenant_id)->where('band_order',$this->data['band_order']??null)->whereDate('effective_from',$this->data['effective_from']??null)->first()??new TaxBracket();}
 protected function beforeSave():void{$this->record->tenant_id=(int)auth()->user()?->tenant_id;}
 public static function getCompletedNotificationBody(Import $import):string{return number_format($import->successful_rows).' tax brackets imported.'.($import->getFailedRowsCount()?' '.number_format($import->getFailedRowsCount()).' rows failed.':'');}
}