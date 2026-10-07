<?php
namespace App\Filament\Resources\Statutory\TaxBrackets;
use App\Filament\Resources\Statutory\TaxBrackets\Pages\{CreateTaxBracket,EditTaxBracket,ListTaxBrackets};
use App\Filament\Resources\TenantScopedResource; use App\Models\TaxBracket; use Filament\Forms\Components\TextInput; use Filament\Schemas\Schema; use Filament\Tables\Columns\TextColumn; use Filament\Tables\Table; use UnitEnum; use BackedEnum;
class TaxBracketResource extends TenantScopedResource {
 protected static ?string $model=TaxBracket::class; protected static string|UnitEnum|null $navigationGroup='Administration'; protected static string|BackedEnum|null $navigationIcon='heroicon-o-scale';
 public static function form(Schema $schema):Schema{return $schema->components([TextInput::make('lower_bound')->numeric()->required(),TextInput::make('upper_bound')->numeric()->required(),TextInput::make('rate')->numeric()->required(),TextInput::make('fixed_tax')->numeric()->default(0)->required()]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('lower_bound')->money('KES'),TextColumn::make('upper_bound')->money('KES'),TextColumn::make('rate'),TextColumn::make('fixed_tax')->money('KES')])->recordActions([\Filament\Actions\EditAction::make(),\Filament\Actions\DeleteAction::make()]);}
 public static function getPages():array{return ['index'=>ListTaxBrackets::route('/'),'create'=>CreateTaxBracket::route('/create'),'edit'=>EditTaxBracket::route('/{record}/edit')];}
}