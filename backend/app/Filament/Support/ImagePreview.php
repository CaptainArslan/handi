<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

/** Click a thumbnail → see the picture big in a modal (used by image columns). */
class ImagePreview
{
    /**
     * @param  Closure(mixed $record): ?string  $url  full image URL for the record
     * @param  Closure(mixed $record): ?string  $title  modal heading
     * @param  Closure(mixed $record): ?string|null  $bg  tint behind the picture
     */
    public static function action(Closure $url, Closure $title, ?Closure $bg = null): Action
    {
        return Action::make('previewImage')
            ->modalHeading(fn ($record) => $title($record))
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn ($record) => view('filament.image-preview', [
                'url' => $url($record),
                'alt' => $title($record),
                'bg' => $bg ? $bg($record) : null,
            ]));
    }
}
