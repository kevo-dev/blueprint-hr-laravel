<?php
namespace App\Filament\Resources\PayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns; use App\Filament\Resources\TenantScopedResource; use App\Models\PayrollRun;
use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class PayrollRunResource extends TenantScopedResource {
 protected static ?string $model=PayrollRun::class; protected static string|UnitEnum|null $navigationGroup='Payroll'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-play-circle';
 public static function form(Schema $schema):Schema{return $schema;}
 public static function table(Table $table):Table{return $table->columns([
  TextColumn::make('payrollPeriod.name')->label('Period')->searchable(), TextColumn::make('status')->badge()->sortable(),
  TextColumn::make('started_at')->dateTime()->sortable(), TextColumn::make('completed_at')->dateTime()->sortable(),
 ]);}
 public static function getPages():array{return ['index'=>ListPayrollRuns::route('/')];}
}