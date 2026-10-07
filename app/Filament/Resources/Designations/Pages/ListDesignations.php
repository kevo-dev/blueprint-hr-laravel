<?php

namespace App\Filament\Resources\Designations\Pages;

use App\Filament\Resources\Designations\DesignationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListDesignations extends ListRecords
{
    protected static string $resource = DesignationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
