<?php
namespace App\Filament\Resources\Statutory\NssfRates;
use App\Filament\Resources\Statutory\NssfRates\Pages\CreateNssfRate; use App\Filament\Resources\Statutory\NssfRates\Pages\EditNssfRate; use App\Filament\Resources\Statutory\NssfRates\Pages\ListNssfRates;
use App\Filament\Resources\TenantScopedResource; use App\Models\NssfRate;
use Filament\Forms\Components\{TextInput,DatePicker}; use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class NssfRateResource extends TenantScopedResource {
 protected static ?string $model=NssfRate::class; protected static string|UnitEnum|null $navigationGroup='Administration'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-adjustments-horizontal';
 public static function form(Schema $schema):Schema{return $schema->components([TextInput::make('tier_name')->numeric()->required(),TextInput::make('lower_limit')->numeric()->required(),TextInput::make('upper_limit')->numeric()->required(),TextInput::make('employee_rate')->numeric()->required(),TextInput::make('employer_rate')->numeric()->required(),DatePicker::make('effective_from')->required()]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('tier_name')->sortable(),TextColumn::make('lower_limit')->sortable(),TextColumn::make('upper_limit')->sortable(),TextColumn::make('employee_rate')->sortable(),TextColumn::make('employer_rate')->sortable(),TextColumn::make('effective_from')->sortable()])->recordActions([\Filament\Actions\EditAction::make(),\Filament\Actions\DeleteAction::make()]);}
 public static function getPages():array{return ['index'=>ListNssfRates::route('/'),'create'=>CreateNssfRate::route('/create'),'edit'=>EditNssfRate::route('/{record}/edit')];}
}