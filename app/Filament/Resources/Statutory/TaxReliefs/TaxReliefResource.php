<?php
namespace App\Filament\Resources\Statutory\TaxReliefs;
use App\Filament\Resources\Statutory\TaxReliefs\Pages\CreateTaxRelief; use App\Filament\Resources\Statutory\TaxReliefs\Pages\EditTaxRelief; use App\Filament\Resources\Statutory\TaxReliefs\Pages\ListTaxReliefs;
use App\Filament\Resources\TenantScopedResource; use App\Models\TaxRelief;
use Filament\Forms\Components\{TextInput,DatePicker}; use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class TaxReliefResource extends TenantScopedResource {
 protected static ?string $model=TaxRelief::class; protected static string|UnitEnum|null $navigationGroup='Administration'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-adjustments-horizontal';
 public static function form(Schema $schema):Schema{return $schema->components([TextInput::make('relief_name')->numeric()->required(),TextInput::make('monthly_amount')->numeric()->required(),DatePicker::make('effective_from')->required()]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('relief_name')->sortable(),TextColumn::make('monthly_amount')->sortable(),TextColumn::make('effective_from')->sortable()])->recordActions([\Filament\Actions\EditAction::make(),\Filament\Actions\DeleteAction::make()]);}
 public static function getPages():array{return ['index'=>ListTaxReliefs::route('/'),'create'=>CreateTaxRelief::route('/create'),'edit'=>EditTaxRelief::route('/{record}/edit')];}
}