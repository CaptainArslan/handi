<?php

use App\Models\Dish;
use App\Models\KitchenDish;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['handi.media_disk' => 'public', 'media-library.disk_name' => 'public', 'media-library.queue_conversions_by_default' => false]);
});

it('stores a dish photo in the media library with thumb and card sizes and a capped original', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();

    $res = $this->actingAs($user)->post("/api/v1/me/dishes/{$dish->id}/photo", [
        'photo' => UploadedFile::fake()->image('big.jpg', 4000, 3000),
    ], ['Accept' => 'application/json'])->assertOk();

    $media = $dish->fresh()->getFirstMedia('photo');
    expect($media)->not->toBeNull()
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->hasGeneratedConversion('card'))->toBeTrue();

    // Original downscaled to ≤ 1600 px on the long side.
    [$w, $h] = getimagesize(Storage::disk('public')->path($media->getPathRelativeToRoot()));
    expect(max($w, $h))->toBe(1600);

    [$tw, $th] = getimagesize(Storage::disk('public')->path($media->getPathRelativeToRoot('thumb')));
    expect([$tw, $th])->toBe([240, 240]);

    $res->assertJsonPath('data.has_photo', true);
    expect($res->json('data.thumb_url'))->toContain('-thumb.webp')
        ->and($res->json('data.image_url'))->toContain('-card.webp');
});

it('replaces the old photo instead of keeping both', function () {
    $user = appUser();
    $dish = $user->kitchenDishes()->first();

    foreach (['a.jpg', 'b.jpg'] as $name) {
        $this->actingAs($user)->post("/api/v1/me/dishes/{$dish->id}/photo", ['photo' => UploadedFile::fake()->image($name, 800, 800)], ['Accept' => 'application/json'])->assertOk();
    }

    expect($dish->fresh()->getMedia('photo'))->toHaveCount(1);
});

it('lists dishes with thumbnails without a query per dish', function () {
    $user = appUser();
    $user->kitchenDishes()->take(3)->get()->each(function (KitchenDish $d) {
        $file = UploadedFile::fake()->image('x.jpg', 500, 500);
        $d->attachPhoto($file);
    });

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $rows = $this->actingAs($user)->getJson('/api/v1/me/dishes')->assertOk()->json('data');
    $mediaQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"media"') || str_contains($q['query'], '`media`'));

    expect(collect($rows)->filter(fn ($r) => str_contains((string) $r['thumb_url'], '-thumb.webp')))->toHaveCount(3)
        ->and($mediaQueries->count())->toBeLessThanOrEqual(2);
});

it('moves photos stored the old way into the media library', function () {
    seedCatalog();
    $dish = Dish::where('name', 'Bhindi')->first();
    Storage::disk('public')->put('dishes/old.jpg', UploadedFile::fake()->image('old.jpg', 1000, 800)->get());
    $dish->update(['image' => 'dishes/old.jpg']);

    $this->artisan('handi:photos-to-media')->assertSuccessful();

    $dish->refresh();
    expect($dish->image)->toBeNull()
        ->and($dish->hasPhoto())->toBeTrue()
        ->and($dish->imageUrl('thumb'))->toContain('-thumb.webp');
    Storage::disk('public')->assertMissing('dishes/old.jpg');
});
