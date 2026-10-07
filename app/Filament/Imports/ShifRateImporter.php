<?php
namespace App\Filament\Imports;
use App\Models\ShifRate; use Filament\Actions\Imports\ImportColumn; use Filament\Actions\Imports\Importer; use Filament\Actions\Imports\Models\Import;
class ShifRateImporter extends Importer {
 protected static ?string $model=ShifRate::class;
 public static function getColumns():array{return [
  ImportColumn::make('percentage')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('min_amount')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('effective_from')->requiredMapping()->rules(['required','date']),
 ];}
 public function resolveRecord():?ShifRate{return ShifRate::query()->where('tenant_id',(int)auth()->user()?->tenant_id)->where('percentage',$this->data['percentage']??null)->whereDate('effective_from',$this->data['effective_from']??null)->first()??new ShifRate();}
 protected function beforeSave():void{$this->record->tenant_id=(int)auth()->user()?->tenant_id;}
 public static function getCompletedNotificationBody(Import $import):string{return number_format($import->successful_rows).' SHIF rates imported.'.($import->getFailedRowsCount()?' '.number_format($import->getFailedRowsCount()).' rows failed.':'');}
}