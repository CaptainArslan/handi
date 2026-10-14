<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn ($record) => $record->dishes()->exists())
                ->tooltip(fn ($record) => $record->dishes()->exists() ? 'Categories with dishes can’t be deleted. Turn off “Active” instead.' : null),
        ];
    }
}
