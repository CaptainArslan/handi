<?php

namespace App\Filament\Resources\Cities\Pages;

use App\Filament\Resources\Cities\CityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCity extends EditRecord
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Kitchens point at their city for weather; deleting one in use would silently clear their location.
            DeleteAction::make()
                ->disabled(fn () => \App\Models\Kitchen::where('city_id', $this->record->getKey())->exists())
                ->tooltip(fn () => \App\Models\Kitchen::where('city_id', $this->record->getKey())->exists() ? 'Kitchens use this city. Turn it off instead.' : null),
        ];
    }
}
