<?php
namespace App\Filament\Imports;
use App\Models\HousingLevyRate; use Filament\Actions\Imports\ImportColumn; use Filament\Actions\Imports\Importer; use Filament\Actions\Imports\Models\Import;
class HousingLevyRateImporter extends Importer {
 protected static ?string $model=HousingLevyRate::class;
 public static function getColumns():array{return [
  ImportColumn::make('employee_percentage')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('employer_percentage')->requiredMapping()->rules(['required','numeric','min:0']),
  ImportColumn::make('effective_from')->requiredMapping()->rules(['required','date']),
 ];}
 public function resolveRecord():?HousingLevyRate{return HousingLevyRate::query()->where('tenant_id',(int)auth()->user()?->tenant_id)->where('employee_percentage',$this->data['employee_percentage']??null)->whereDate('effective_from',$this->data['effective_from']??null)->first()??new HousingLevyRate();}
 protected function beforeSave():void{$this->record->tenant_id=(int)auth()->user()?->tenant_id;}
 public static function getCompletedNotificationBody(Import $import):string{return number_format($import->successful_rows).' housing levy rates imported.'.($import->getFailedRowsCount()?' '.number_format($import->getFailedRowsCount()).' rows failed.':'');}
}