<?php

namespace App\Filament\Resources\Dishes\Pages;

use App\Filament\Resources\Dishes\DishResource;
use App\Models\Category;
use App\Support\Media;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditDish extends EditRecord
{
    protected static string $resource = DishResource::class;

    /** Where a copy of the bundled art is put so the dropzone can show it. */
    public const ART_DIR = 'dishes/_art';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn ($record) => $record->kitchenDishes()->exists())
                ->tooltip(fn ($record) => $record->kitchenDishes()->exists() ? 'Dishes in users’ kitchens can’t be deleted. Turn off “Active” instead.' : null),
        ];
    }

    /**
     * The photo dropzone only shows files on the media disk. A library dish without an uploaded
     * photo uses bundled art, so a copy of that art is placed on the disk and shown instead —
     * the dropzone then always shows the picture people actually see.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (blank($data['image'] ?? null)) {
            $key = $data['image_key'] ?? Category::find($data['category_id'] ?? null)?->image_key;
            $data['image'] = self::artOnDisk($key);
        }

        return $data;
    }

    /** Saving the untouched art copy keeps the dish on bundled art (no photo stored). */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (is_string($data['image'] ?? null) && Str::startsWith($data['image'], self::ART_DIR.'/')) {
            $data['image'] = null;
        }

        return $data;
    }

    public static function artOnDisk(?string $key): ?string
    {
        if (! $key || ! in_array($key, Media::ART_KEYS, true)) {
            return null;
        }

        $source = public_path("images/dishes/{$key}.png");
        $path = self::ART_DIR."/{$key}.png";
        $disk = Storage::disk(config('handi.media_disk'));

        if (is_file($source) && ! $disk->exists($path)) {
            $disk->put($path, (string) file_get_contents($source), 'public');
        }

        return $disk->exists($path) ? $path : null;
    }
}
