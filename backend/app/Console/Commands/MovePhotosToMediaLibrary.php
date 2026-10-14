<?php

namespace App\Console\Commands;

use App\Models\Dish;
use App\Models\KitchenDish;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One-off: move photos stored before the media library (dishes.image, kitchen_dishes.photo_path)
 * into it, so they get thumbnails and the old files are removed. Safe to run again.
 */
class MovePhotosToMediaLibrary extends Command
{
    protected $signature = 'handi:photos-to-media {--keep : Keep the old files after copying}';

    protected $description = 'Move existing dish photos into the media library (thumbnails + smaller originals)';

    public function handle(): int
    {
        $disk = Storage::disk(config('handi.media_disk'));
        $moved = 0;
        $missing = 0;

        $move = function (Model $model, string $column) use ($disk, &$moved, &$missing): void {
            /** @var Dish|KitchenDish $model */
            $path = $model->getAttribute($column);
            if (! $path || ! $disk->exists($path)) {
                $missing++;
                $model->forceFill([$column => null])->saveQuietly();

                return;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'mv');
            file_put_contents($tmp, $disk->get($path));
            try {
                $model->attachPhoto($tmp);
            } finally {
                @unlink($tmp);
            }
            if (! $this->option('keep')) {
                $disk->delete($path);
            }
            $model->forceFill([$column => null])->saveQuietly();
            $moved++;
        };

        Dish::whereNotNull('image')->each(fn (Dish $d) => $move($d, 'image'));
        KitchenDish::whereNotNull('photo_path')->each(fn (KitchenDish $d) => $move($d, 'photo_path'));

        \App\Support\CatalogCache::flush();
        $this->info("Moved {$moved} photo(s) into the media library".($missing ? ", cleared {$missing} missing file(s)." : '.'));

        return self::SUCCESS;
    }
}
