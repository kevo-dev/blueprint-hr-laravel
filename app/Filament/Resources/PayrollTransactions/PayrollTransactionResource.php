<?php
namespace App\Filament\Resources\PayrollTransactions;
use App\Filament\Resources\PayrollTransactions\Pages\ListPayrollTransactions;
use App\Filament\Resources\TenantScopedResource; use App\Models\PayrollTransaction;
use Filament\Forms\Components\Select; use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; use UnitEnum; use BackedEnum;
class PayrollTransactionResource extends TenantScopedResource {
 protected static ?string $model=PayrollTransaction::class; protected static string|UnitEnum|null $navigationGroup='Payroll'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-banknotes';
 public static function getEloquentQuery():Builder{return parent::getEloquentQuery()->with(['employee','payrollPeriod']);}
 public static function form(Schema $schema):Schema{return $schema->components([Select::make('employee_id')->relationship('employee','employee_no')->searchable()->preload()->required(),Select::make('payroll_period_id')->relationship('payrollPeriod','name')->searchable()->preload()->required()]);}
 public static function table(Table $table):Table{return $table->columns([
  TextColumn::make('employee.full_name')->label('Employee')->searchable(), TextColumn::make('payrollPeriod.name')->label('Period')->searchable(),
  TextColumn::make('gross_pay')->money('KES')->sortable(), TextColumn::make('net_pay')->money('KES')->sortable(),
  TextColumn::make('created_at')->dateTime()->sortable(),
 ]);}
 public static function getPages():array{return ['index'=>ListPayrollTransactions::route('/')];}
}