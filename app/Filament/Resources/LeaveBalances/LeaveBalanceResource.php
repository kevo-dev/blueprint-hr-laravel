<?php

namespace App\Filament\Resources\LeaveBalances;

use App\Filament\Resources\LeaveBalances\Pages\CreateLeaveBalance;
use App\Filament\Resources\LeaveBalances\Pages\EditLeaveBalance;
use App\Filament\Resources\LeaveBalances\Pages\ListLeaveBalances;
use App\Filament\Resources\TenantScopedResource;
use App\Models\LeaveBalance;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class LeaveBalanceResource extends TenantScopedResource
{
    protected static ?string $model = LeaveBalance::class;
    protected static string|UnitEnum|null $navigationGroup = 'Time & Leave';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('employee_id')->relationship('employee', 'employee_no')->searchable()->preload()->required(),
            Select::make('leave_type_id')->relationship('leaveType', 'name')->searchable()->preload()->required(),
            TextInput::make('year')->numeric()->minValue(2000)->maxValue(2100)->default(now()->year)->required(),
            TextInput::make('allocated_days')->numeric()->minValue(0)->required(),
            TextInput::make('used_days')->numeric()->minValue(0)->default(0)->required(),
            TextInput::make('carried_forward')->numeric()->minValue(0)->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('employee.full_name')->label('Employee')->searchable(),
            TextColumn::make('leaveType.name')->label('Leave Type')->searchable(),
            TextColumn::make('year')->sortable(),
            TextColumn::make('allocated_days')->label('Allocated'),
            TextColumn::make('used_days')->label('Used'),
            TextColumn::make('available_days')->label('Available')->state(fn (LeaveBalance $record) => number_format($record->available_days, 2)),
        ])->recordActions([
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveBalances::route('/'),
            'create' => CreateLeaveBalance::route('/create'),
            'edit' => EditLeaveBalance::route('/{record}/edit'),
        ];
    }
}