<?php

use App\Filament\Resources\Dishes\Pages\EditDish;
use App\Filament\Resources\Dishes\Pages\ListDishes;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    config(['media-library.disk_name' => 'public', 'media-library.queue_conversions_by_default' => false]);
    seedCatalog();
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('opens a dish picture in a preview modal (bundled art without a photo)', function () {
    $dish = Dish::where('name', 'Bhindi')->first();

    Livewire::test(ListDishes::class)
        ->callTableColumnAction('image', $dish)
        ->assertSee('images/dishes/bhindi.png');
});

it('shows the uploaded photo thumbnail in the list and the card size in the preview', function () {
    $dish = Dish::where('name', 'Bhindi')->first();
    $file = UploadedFile::fake()->image('b.jpg', 1200, 900);
    $dish->attachPhoto($file);

    expect($dish->fresh()->imageUrl('thumb'))->toContain('-thumb.webp')
        ->and($dish->fresh()->imageUrl('card'))->toContain('-card.webp');
    Livewire::test(ListDishes::class)->assertSee('-thumb.webp');
});

it('saves the edit form without touching a dish that has no photo', function () {
    $dish = Dish::where('name', 'Bhindi')->first();

    Livewire::test(EditDish::class, ['record' => $dish->getRouteKey()])
        ->set('data.note', 'Okra in summer')->call('save')->assertHasNoFormErrors();

    expect($dish->fresh()->note)->toBe('Okra in summer')->and($dish->fresh()->hasPhoto())->toBeFalse();
});
