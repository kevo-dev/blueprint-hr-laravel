<?php
namespace App\Filament\Resources\Reports;
use App\Filament\Resources\Reports\Pages\ListEmployeeReports;
use App\Filament\Resources\TenantScopedResource; use App\Models\Employee;
use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class EmployeeReportResource extends TenantScopedResource {
 protected static ?string $model=Employee::class; protected static string|UnitEnum|null $navigationGroup='Governance'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-document-chart-bar'; protected static ?string $navigationLabel='Employee Report';
 public static function form(Schema $schema):Schema{return $schema;}
 public static function table(Table $table):Table{return $table->columns([
  TextColumn::make('employee_no')->searchable()->sortable(), TextColumn::make('full_name')->label('Employee')->state(fn(Employee $r)=>$r->full_name)->searchable(),
  TextColumn::make('department.name')->sortable(), TextColumn::make('designation.name')->sortable(), TextColumn::make('employment_status')->sortable(),
  TextColumn::make('employment_date')->date()->sortable(), TextColumn::make('basic_salary')->money('KES')->sortable(),
 ]);}
 public static function getPages():array{return ['index'=>ListEmployeeReports::route('/')];}
}