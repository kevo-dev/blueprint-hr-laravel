<?php
namespace App\Filament\Resources\Statutory\HousingLevyRates;
use App\Filament\Resources\Statutory\HousingLevyRates\Pages\CreateHousingLevyRate; use App\Filament\Resources\Statutory\HousingLevyRates\Pages\EditHousingLevyRate; use App\Filament\Resources\Statutory\HousingLevyRates\Pages\ListHousingLevyRates;
use App\Filament\Resources\TenantScopedResource; use App\Models\HousingLevyRate;
use Filament\Forms\Components\{TextInput,DatePicker}; use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class HousingLevyRateResource extends TenantScopedResource {
 protected static ?string $model=HousingLevyRate::class; protected static string|UnitEnum|null $navigationGroup='Administration'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-adjustments-horizontal';
 public static function form(Schema $schema):Schema{return $schema->components([TextInput::make('employee_percentage')->numeric()->required(),TextInput::make('employer_percentage')->numeric()->required(),DatePicker::make('effective_from')->required()]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('employee_percentage')->sortable(),TextColumn::make('employer_percentage')->sortable(),TextColumn::make('effective_from')->sortable()])->recordActions([\Filament\Actions\EditAction::make(),\Filament\Actions\DeleteAction::make()]);}
 public static function getPages():array{return ['index'=>ListHousingLevyRates::route('/'),'create'=>CreateHousingLevyRate::route('/create'),'edit'=>EditHousingLevyRate::route('/{record}/edit')];}
}