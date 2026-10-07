<?php

namespace App\\Filament\\Resources\\Designations;

use App\\Filament\\Resources\\Designations\\Pages\\CreateDesignation;
use App\\Filament\\Resources\\Designations\\Pages\\EditDesignation;
use App\\Filament\\Resources\\Designations\\Pages\\ListDesignations;
use App\\Filament\\Resources\\TenantScopedResource;
use App\\Models\\Designation;
use Filament\\Resources\\Resource;
use Filament\\Schemas\\Schema;
use Filament\\Forms\\Components\\Select;
use Filament\\Forms\\Components\\TextInput;
use Filament\\Tables\\Columns\\TextColumn;
use Filament\\Tables\\Table;
use UnitEnum;
use BackedEnum;

class DesignationResource extends TenantScopedResource
{
    protected static ?string $model = Designation::class;
    protected static string|UnitEnum|null $navigationGroup = 'Organization';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(150), TextInput::make('description')->maxLength(1000),
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
            'index' => ListDesignations::route('/'),
            'create' => CreateDesignation::route('/create'),
            'edit' => EditDesignation::route('/{record}/edit'),
        ];
    }
}
