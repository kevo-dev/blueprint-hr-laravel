<?php

namespace App\\Filament\\Resources\\Branches;

use App\\Filament\\Resources\\Branches\\Pages\\CreateBranch;
use App\\Filament\\Resources\\Branches\\Pages\\EditBranch;
use App\\Filament\\Resources\\Branches\\Pages\\ListBranches;
use App\\Filament\\Resources\\TenantScopedResource;
use App\\Models\\Branch;
use Filament\\Resources\\Resource;
use Filament\\Schemas\\Schema;
use Filament\\Forms\\Components\\Select;
use Filament\\Forms\\Components\\TextInput;
use Filament\\Tables\\Columns\\TextColumn;
use Filament\\Tables\\Table;
use UnitEnum;
use BackedEnum;

class BranchResource extends TenantScopedResource
{
    protected static ?string $model = Branch::class;
    protected static string|UnitEnum|null $navigationGroup = 'Organization';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(150), TextInput::make('code')->maxLength(50), TextInput::make('location')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(), TextColumn::make('code')->searchable()->sortable(), TextColumn::make('location')->searchable(),
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
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
