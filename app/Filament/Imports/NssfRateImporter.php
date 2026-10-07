<?php
namespace App\Filament\Imports;
use App\Models\NssfRate; use Filament\Actions\Imports\ImportColumn; use Filament\Actions\Imports\Importer; use Filament\Actions\Imports\Models\Import;
class NssfRateImporter extends Importer {
 protected static ?string $model=NssfRate::class;
 public static function getColumns():array{return [
  ImportColumn::make('tier_name')->requiredMapping()->rules(['required','string','max:100']),
  ImportColumn::make('lower_limit')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('upper_limit')->rules(['nullable','numeric','gte:lower_limit']),
  ImportColumn::make('employee_rate')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('employer_rate')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('effective_from')->requiredMapping()->rules(['required','date']),
 ];}
 public function resolveRecord():?NssfRate{return NssfRate::query()->where('tenant_id',(int)auth()->user()?->tenant_id)->where('tier_name',$this->data['tier_name']??null)->whereDate('effective_from',$this->data['effective_from']??null)->first()??new NssfRate();}
 protected function beforeSave():void{$this->record->tenant_id=(int)auth()->user()?->tenant_id;}
 public static function getCompletedNotificationBody(Import $import):string{return number_format($import->successful_rows).' NSSF rates imported.'.($import->getFailedRowsCount()?' '.number_format($import->getFailedRowsCount()).' rows failed.':'');}
}