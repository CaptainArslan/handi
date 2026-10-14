<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Dish photos through Spatie Media Library.
 *
 * - One photo per dish (`singleFile`): a new upload replaces the old file and its thumbnails.
 * - The stored original is capped at 1600 px / JPEG 85, so storage stays small even if a client
 *   sends a full camera image.
 * - Conversions (WebP): `thumb` 240×240 crop for lists, `card` ≤ 900 px for cards and the dish page.
 * - Files live on MEDIA_DISK (public locally, s3/R2 in production).
 */
trait HasDishPhoto
{
    use InteractsWithMedia;

    public const PHOTO_MAX_EDGE = 1600;

    abstract public function photoCollection(): string;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection($this->photoCollection())
            ->singleFile()
            ->useDisk(config('handi.media_disk'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 240, 240)
            ->format('webp')
            ->quality(78)
            ->nonOptimized()
            ->performOnCollections($this->photoCollection());

        $this->addMediaConversion('card')
            ->fit(Fit::Max, 900, 900)
            ->format('webp')
            ->quality(80)
            ->nonOptimized()
            ->performOnCollections($this->photoCollection());
    }

    /** Store a new photo (replacing the old one), downscaled to PHOTO_MAX_EDGE first. */
    public function attachPhoto(UploadedFile|string $file): Media
    {
        $source = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $base = tempnam(sys_get_temp_dir(), 'dish');
        $tmp = $base.'.jpg';
        @unlink($base);

        try {
            Image::load($source)
                ->fit(Fit::Max, self::PHOTO_MAX_EDGE, self::PHOTO_MAX_EDGE)
                ->quality(85)
                ->format('jpg')
                ->save($tmp);
        } catch (\Throwable $e) {
            report($e);
            copy($source, $tmp); // keep the upload as-is rather than failing
        }

        return $this->addMedia($tmp)
            ->usingName(Str::limit((string) ($this->name ?? 'dish'), 60, ''))
            ->usingFileName(Str::uuid().'.jpg')
            ->toMediaCollection($this->photoCollection());
    }

    public function hasPhoto(): bool
    {
        return $this->getFirstMedia($this->photoCollection()) !== null;
    }

    /** URL of the photo in a size (`thumb`, `card`, or '' for the original); null without a photo. */
    public function photoUrl(string $conversion = 'card'): ?string
    {
        $media = $this->getFirstMedia($this->photoCollection());
        if (! $media) {
            return null;
        }

        $url = $conversion !== '' && $media->hasGeneratedConversion($conversion) ? $media->getUrl($conversion) : $media->getUrl();

        // A relative disk URL (/storage/...) becomes absolute on the host of the current request.
        return Str::startsWith($url, '/') ? url($url) : $url;
    }
}
