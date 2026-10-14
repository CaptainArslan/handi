<?php

use App\Filament\Resources\Dishes\Pages\EditDish;
use App\Filament\Resources\Dishes\Pages\ListDishes;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    seedCatalog();
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('opens a dish picture in a preview modal', function () {
    $dish = Dish::where('name', 'Bhindi')->first();

    Livewire::test(ListDishes::class)
        ->callTableColumnAction('image', $dish)
        ->assertSee('images/dishes/bhindi.png');
});

it('shows the bundled art in the edit dropzone and keeps it as art on save', function () {
    $dish = Dish::where('name', 'Bhindi')->first();
    expect($dish->image)->toBeNull();

    $page = Livewire::test(EditDish::class, ['record' => $dish->getRouteKey()]);
    $state = $page->get('data.image');
    expect(collect($state)->first())->toBe('dishes/_art/bhindi.png');
    Storage::disk('public')->assertExists('dishes/_art/bhindi.png');

    $page->set('data.note', 'Okra in summer')->call('save')->assertHasNoFormErrors();
    expect($dish->fresh()->image)->toBeNull()->and($dish->fresh()->note)->toBe('Okra in summer');
});
