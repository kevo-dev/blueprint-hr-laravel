<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Imports\EmployeeImporter;
use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->label('Import Staff')
                ->icon('heroicon-o-arrow-up-tray')
                ->importer(EmployeeImporter::class)
                ->visible(fn (): bool => (bool) auth()->user()?->role?->canManagePeople()),
            Actions\CreateAction::make(),
        ];
    }
}
