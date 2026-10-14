<?php

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ActivityLogsRelationManager;
use App\Filament\Resources\Users\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\Users\RelationManagers\KitchenDishesRelationManager;
use App\Filament\Resources\Users\RelationManagers\MealLogsRelationManager;
use App\Filament\Resources\Users\RelationManagers\NotificationsRelationManager;
use App\Filament\Resources\Users\RelationManagers\TokensRelationManager;
use App\Models\User;
use Livewire\Livewire;

function admin(): User
{
    return User::firstWhere('email', 'admin@handi.test')
        ?? User::factory()->create(['is_admin' => true, 'email' => 'admin@handi.test']);
}

beforeEach(function () {
    $this->hina = appUser();
    $dish = $this->hina->kitchenDishes()->first();
    $this->actingAs($this->hina)->postJson('/api/v1/me/meals', ['kitchen_dish_id' => $dish->id, 'date' => now('Asia/Karachi')->toDateString(), 'meal' => 'lunch']);
    app('auth')->forgetGuards();
    $this->flushSession();
    app('auth')->shouldUse('web'); // auth:sanctum switched the default guard during the API calls above
});

it('blocks non-admins from the panel', function () {
    $this->actingAs($this->hina)->get('/admin')->assertForbidden();
});

it('renders every admin page', function (string $url) {
    $this->actingAs(admin())->get($url)->assertOk();
})->with([
    '/admin',
    '/admin/users',
    '/admin/activity-logs',
    '/admin/dishes',
    '/admin/dishes/create',
    '/admin/categories',
    '/admin/cities',
]);

it('shows a user’s full detail page', function () {
    $this->actingAs(admin())->get("/admin/users/{$this->hina->id}")->assertOk()
        ->assertSee('Hina Arslan')->assertSee('hina@example.com')->assertSee('Lahore');
    $this->actingAs(admin())->get("/admin/users/{$this->hina->id}/edit")->assertOk();
});

it('lists users and their relations', function () {
    $this->actingAs(admin());
    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$this->hina]);

    foreach ([KitchenDishesRelationManager::class, MealLogsRelationManager::class, NotificationsRelationManager::class,
        DevicesRelationManager::class, TokensRelationManager::class, ActivityLogsRelationManager::class] as $rm) {
        Livewire::test($rm, ['ownerRecord' => $this->hina, 'pageClass' => ViewUser::class])->assertSuccessful();
    }

    Livewire::test(KitchenDishesRelationManager::class, ['ownerRecord' => $this->hina, 'pageClass' => ViewUser::class])
        ->assertCountTableRecords(13);
    Livewire::test(ListActivityLogs::class)->assertSuccessful();
});

it('lets the admin reset a user password', function () {
    $this->actingAs(admin());
    Livewire::test(EditUser::class, ['record' => $this->hina->getRouteKey()])
        ->fillForm(['new_password' => 'resetpass1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(\Illuminate\Support\Facades\Hash::check('resetpass1', $this->hina->fresh()->password))->toBeTrue();
});
