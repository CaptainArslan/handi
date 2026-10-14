<?php

namespace App\Filament\Resources\Dishes\Pages;

use App\Filament\Resources\Dishes\DishResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditDish extends EditRecord
{
    protected static string $resource = DishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn ($record) => $record->kitchenDishes()->exists())
                ->tooltip(fn ($record) => $record->kitchenDishes()->exists() ? 'Dishes in users’ kitchens can’t be deleted. Turn off “Active” instead.' : null),
        ];
    }

    /** Once a media-library photo exists, a photo stored the old way (`image` column) is dropped. */
    protected function afterSave(): void
    {
        $dish = $this->record->refresh();
        if ($dish->image && $dish->hasPhoto()) {
            Storage::disk(config('handi.media_disk'))->delete($dish->image);
            $dish->forceFill(['image' => null])->saveQuietly();
        }
    }
}
