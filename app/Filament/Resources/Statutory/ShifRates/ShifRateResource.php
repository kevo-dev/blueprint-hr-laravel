<?php

namespace App\Filament\Resources\Statutory\ShifRates;

use App\Filament\Resources\Statutory\ShifRates\Pages\CreateShifRate;
use App\Filament\Resources\Statutory\ShifRates\Pages\EditShifRate;
use App\Filament\Resources\Statutory\ShifRates\Pages\ListShifRates;
use App\Filament\Resources\TenantScopedResource;
use App\Models\ShifRate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class ShifRateResource extends TenantScopedResource
{
    protected static ?string $model = ShifRate::class;
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('percentage')->numeric()->minValue(0)->maxValue(1)->required(),
            TextInput::make('min_amount')->numeric()->minValue(0)->required(),
            DatePicker::make('effective_from')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('percentage')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('min_amount')->money('KES')->sortable(),
            TextColumn::make('effective_from')->date()->sortable(),
        ])->recordActions([
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifRates::route('/'),
            'create' => CreateShifRate::route('/create'),
            'edit' => EditShifRate::route('/{record}/edit'),
        ];
    }
}
