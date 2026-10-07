<?php
namespace App\Filament\Resources\Statutory\ShifRates;
use App\Filament\Resources\Statutory\ShifRates\Pages\CreateShifRate; use App\Filament\Resources\Statutory\ShifRates\Pages\EditShifRate; use App\Filament\Resources\Statutory\ShifRates\Pages\ListShifRates;
use App\Filament\Resources\TenantScopedResource; use App\Models\ShifRate;
use Filament\Forms\Components\{TextInput,DatePicker}; use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class ShifRateResource extends TenantScopedResource {
 protected static ?string $model=ShifRate::class; protected static string|UnitEnum|null $navigationGroup='Administration'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-adjustments-horizontal';
 public static function form(Schema $schema):Schema{return $schema->components([TextInput::make('percentage')->numeric()->required(),TextInput::make('min_amount')->numeric()->required(),DatePicker::make('effective_from')->required()]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('percentage')->sortable(),TextColumn::make('min_amount')->sortable(),TextColumn::make('effective_from')->sortable()])->recordActions([\Filament\Actions\EditAction::make(),\Filament\Actions\DeleteAction::make()]);}
 public static function getPages():array{return ['index'=>ListShifRates::route('/'),'create'=>CreateShifRate::route('/create'),'edit'=>EditShifRate::route('/{record}/edit')];}
}