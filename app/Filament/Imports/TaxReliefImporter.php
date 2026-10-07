<?php
namespace App\Filament\Imports;
use App\Models\TaxRelief; use Filament\Actions\Imports\ImportColumn; use Filament\Actions\Imports\Importer; use Filament\Actions\Imports\Models\Import;
class TaxReliefImporter extends Importer {
 protected static ?string $model=TaxRelief::class;
 public static function getColumns():array{return [
  ImportColumn::make('relief_name')->requiredMapping()->rules(['required','string','max:150']),
  ImportColumn::make('monthly_amount')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('effective_from')->requiredMapping()->rules(['required','date']),
 ];}
 public function resolveRecord():?TaxRelief{return TaxRelief::query()->where('tenant_id',(int)auth()->user()?->tenant_id)->where('relief_name',$this->data['relief_name']??null)->whereDate('effective_from',$this->data['effective_from']??null)->first()??new TaxRelief();}
 protected function beforeSave():void{$this->record->tenant_id=(int)auth()->user()?->tenant_id;}
 public static function getCompletedNotificationBody(Import $import):string{return number_format($import->successful_rows).' tax reliefs imported.'.($import->getFailedRowsCount()?' '.number_format($import->getFailedRowsCount()).' rows failed.':'');}
}