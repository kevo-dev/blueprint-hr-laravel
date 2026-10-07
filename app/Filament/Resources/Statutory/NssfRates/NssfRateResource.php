<?php

namespace App\Filament\Resources\Statutory\NssfRates;

use App\Filament\Resources\Statutory\NssfRates\Pages\CreateNssfRate;
use App\Filament\Resources\Statutory\NssfRates\Pages\EditNssfRate;
use App\Filament\Resources\Statutory\NssfRates\Pages\ListNssfRates;
use App\Filament\Resources\TenantScopedResource;
use App\Models\NssfRate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class NssfRateResource extends TenantScopedResource
{
    protected static ?string $model = NssfRate::class;
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('tier_name')->required()->maxLength(100),
            TextInput::make('lower_limit')->numeric()->minValue(0)->required(),
            TextInput::make('upper_limit')->numeric()->minValue(0)->nullable(),
            TextInput::make('employee_rate')->numeric()->minValue(0)->maxValue(1)->required(),
            TextInput::make('employer_rate')->numeric()->minValue(0)->maxValue(1)->required(),
            DatePicker::make('effective_from')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('tier_name')->sortable()->searchable(),
            TextColumn::make('lower_limit')->money('KES')->sortable(),
            TextColumn::make('upper_limit')->money('KES')->sortable(),
            TextColumn::make('employee_rate')->numeric(decimalPlaces: 2),
            TextColumn::make('employer_rate')->numeric(decimalPlaces: 2),
            TextColumn::make('effective_from')->date()->sortable(),
        ])->recordActions([
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNssfRates::route('/'),
            'create' => CreateNssfRate::route('/create'),
            'edit' => EditNssfRate::route('/{record}/edit'),
        ];
    }
}
