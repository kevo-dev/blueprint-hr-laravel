<?php
namespace App\Filament\Resources\PayrollPeriods;

use App\Filament\Resources\PayrollPeriods\Pages\{CreatePayrollPeriod,EditPayrollPeriod,ListPayrollPeriods};
use App\Filament\Resources\TenantScopedResource;
use App\Models\PayrollPeriod;
use Filament\Forms\Components\{Select,TextInput};
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class PayrollPeriodResource extends TenantScopedResource
{
    protected static ?string $model = PayrollPeriod::class;
    protected static string|UnitEnum|null $navigationGroup = 'Payroll';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            Select::make('month')->options(array_combine(range(1, 12), range(1, 12)))->required(),
            Select::make('year')->options(array_combine(range((int) date('Y') - 2, (int) date('Y') + 2), range((int) date('Y') - 2, (int) date('Y') + 2)))->required(),
            Select::make('status')->options(['Open' => 'Open', 'Processing' => 'Processing', 'Closed' => 'Closed'])->default('Open')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('month')->sortable(),
            TextColumn::make('year')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('created_at')->dateTime()->toggleable(),
        ])->recordActions([
            \Filament\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollPeriods::route('/'),
            'create' => CreatePayrollPeriod::route('/create'),
            'edit' => EditPayrollPeriod::route('/{record}/edit'),
        ];
    }
}
