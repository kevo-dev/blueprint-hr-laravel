<?php

namespace App\Filament\Resources\Statutory\TaxBrackets;

use App\Filament\Resources\Statutory\TaxBrackets\Pages\CreateTaxBracket;
use App\Filament\Resources\Statutory\TaxBrackets\Pages\EditTaxBracket;
use App\Filament\Resources\Statutory\TaxBrackets\Pages\ListTaxBrackets;
use App\Filament\Resources\TenantScopedResource;
use App\Models\TaxBracket;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class TaxBracketResource extends TenantScopedResource
{
    protected static ?string $model = TaxBracket::class;
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('band_order')->numeric()->minValue(1)->required(),
            TextInput::make('lower_limit')->numeric()->minValue(0)->required(),
            TextInput::make('upper_limit')->numeric()->minValue(0)->nullable(),
            TextInput::make('rate')->numeric()->minValue(0)->maxValue(1)->required(),
            DatePicker::make('effective_from')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('band_order')->sortable(),
            TextColumn::make('lower_limit')->money('KES')->sortable(),
            TextColumn::make('upper_limit')->money('KES')->sortable(),
            TextColumn::make('rate')->numeric(decimalPlaces: 2),
            TextColumn::make('effective_from')->date()->sortable(),
        ])->recordActions([
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxBrackets::route('/'),
            'create' => CreateTaxBracket::route('/create'),
            'edit' => EditTaxBracket::route('/{record}/edit'),
        ];
    }
}
