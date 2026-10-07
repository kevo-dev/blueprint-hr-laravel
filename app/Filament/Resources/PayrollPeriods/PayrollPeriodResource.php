<?php
namespace App\Filament\Resources\PayrollPeriods;
use App\Filament\Resources\PayrollPeriods\Pages\{CreatePayrollPeriod,EditPayrollPeriod,ListPayrollPeriods};
use App\Filament\Resources\TenantScopedResource;
use App\Models\PayrollPeriod;
use Filament\Forms\Components\{DatePicker,Select,TextInput};
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum; use BackedEnum;
class PayrollPeriodResource extends TenantScopedResource {
 protected static ?string $model=PayrollPeriod::class;
 protected static string|UnitEnum|null $navigationGroup='Payroll';
 protected static string|BackedEnum|null $navigationIcon='heroicon-o-calendar';
 public static function form(Schema $schema): Schema { return $schema->components([
  TextInput::make('name')->required()->maxLength(100),
  DatePicker::make('start_date')->required(), DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
  Select::make('status')->options(['Open'=>'Open','Processing'=>'Processing','Closed'=>'Closed'])->default('Open')->required(),
 ]); }
 public static function table(Table $table): Table { return $table->columns([
  TextColumn::make('name')->searchable()->sortable(), TextColumn::make('start_date')->date()->sortable(),
  TextColumn::make('end_date')->date()->sortable(), TextColumn::make('status')->badge()->sortable(),
  TextColumn::make('created_at')->dateTime()->toggleable(),
 ])->recordActions([\Filament\Actions\EditAction::make()]); }
 public static function getPages(): array { return ['index'=>ListPayrollPeriods::route('/'),'create'=>CreatePayrollPeriod::route('/create'),'edit'=>EditPayrollPeriod::route('/{record}/edit')]; }
}