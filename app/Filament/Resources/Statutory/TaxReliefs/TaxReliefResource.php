<?php

namespace App\Filament\Resources\Statutory\TaxReliefs;

use App\Filament\Resources\Statutory\TaxReliefs\Pages\CreateTaxRelief;
use App\Filament\Resources\Statutory\TaxReliefs\Pages\EditTaxRelief;
use App\Filament\Resources\Statutory\TaxReliefs\Pages\ListTaxReliefs;
use App\Filament\Resources\TenantScopedResource;
use App\Models\TaxRelief;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class TaxReliefResource extends TenantScopedResource
{
    protected static ?string $model = TaxRelief::class;
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('relief_name')->required()->maxLength(150),
            TextInput::make('monthly_amount')->numeric()->minValue(0)->required(),
            DatePicker::make('effective_from')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('relief_name')->sortable()->searchable(),
            TextColumn::make('monthly_amount')->money('KES')->sortable(),
            TextColumn::make('effective_from')->date()->sortable(),
        ])->recordActions([
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxReliefs::route('/'),
            'create' => CreateTaxRelief::route('/create'),
            'edit' => EditTaxRelief::route('/{record}/edit'),
        ];
    }
}
