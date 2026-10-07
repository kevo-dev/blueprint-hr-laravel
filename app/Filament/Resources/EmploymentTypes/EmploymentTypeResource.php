<?php

namespace App\\Filament\\Resources\\EmploymentTypes;

use App\\Filament\\Resources\\EmploymentTypes\\Pages\\CreateEmploymentType;
use App\\Filament\\Resources\\EmploymentTypes\\Pages\\EditEmploymentType;
use App\\Filament\\Resources\\EmploymentTypes\\Pages\\ListEmploymentTypes;
use App\\Filament\\Resources\\TenantScopedResource;
use App\\Models\\EmploymentType;
use Filament\\Resources\\Resource;
use Filament\\Schemas\\Schema;
use Filament\\Forms\\Components\\Select;
use Filament\\Forms\\Components\\TextInput;
use Filament\\Tables\\Columns\\TextColumn;
use Filament\\Tables\\Table;
use UnitEnum;
use BackedEnum;

class EmploymentTypeResource extends TenantScopedResource
{
    protected static ?string $model = EmploymentType::class;
    protected static string|UnitEnum|null $navigationGroup = 'Organization';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100), TextInput::make('description')->maxLength(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(), TextColumn::make('description')->limit(60),
        ])->recordActions([
            \Filament\\Actions\\EditAction::make(),
            \Filament\\Actions\\DeleteAction::make(),
        ])->toolbarActions([
            \Filament\\Actions\\BulkActionGroup::make([
                \Filament\\Actions\\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmploymentTypes::route('/'),
            'create' => CreateEmploymentType::route('/create'),
            'edit' => EditEmploymentType::route('/{record}/edit'),
        ];
    }
}
