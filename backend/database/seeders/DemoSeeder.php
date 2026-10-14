<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\City;
use App\Models\Dish;
use App\Models\KitchenDish;
use App\Models\MealLog;
use App\Models\User;
use App\Services\KitchenService;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * A demo app user with a kitchen, two weeks of history and notifications,
 * for trying the app and the admin. Runs only when DEMO_USER_EMAIL and DEMO_USER_PASSWORD are set.
 */
class DemoSeeder extends Seeder
{
    public function run(KitchenService $kitchens): void
    {
        $email = env('DEMO_USER_EMAIL');
        $password = env('DEMO_USER_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->info('DEMO_USER_EMAIL / DEMO_USER_PASSWORD not set: skipping the demo user.');

            return;
        }

        $user = User::updateOrCreate(['email' => strtolower($email)], [
            'name' => env('DEMO_USER_NAME', 'Hina Arslan'),
            'password' => $password,
            'terms_accepted_at' => now(),
            'password_changed_at' => now()->subMonths(3),
            'timezone' => config('handi.timezone'),
        ]);

        $kitchen = $user->kitchenOrDefault();
        $kitchen->update(['family_size' => 4, 'budget' => 'normal', 'repeat_gap_days' => 5, 'meals' => ['lunch', 'dinner']]);
        $kitchens->applyLocation($kitchen, ['mode' => 'city', 'city_id' => City::where('slug', 'lahore')->value('id')]);

        $names = ['Daal Chawal', 'Bhindi', 'Chicken Karahi', 'Aloo Gobi', 'Biryani', 'Qeema', 'Fish Fry', 'Chana Daal', 'Anda Paratha', 'Karela', 'Matar Pulao'];
        $kitchens->addLibraryDishes($user, Dish::whereIn('name', $names)->pluck('id')->all());

        foreach ([
            ['Mom’s Karhi', 'lentils', ['rainy', 'mild']],
            ['Grandma’s Qeema Matar', 'beef', ['any']],
            ['Masala Anda Paratha', 'breakfast', ['cold', 'rainy']],
        ] as [$name, $category, $weather]) {
            if (! $kitchens->nameTaken($user, $name)) {
                $kitchens->createCustomDish($user, ['name' => $name, 'category' => $category, 'weather' => $weather, 'usual_meal' => $category === 'breakfast' ? 'breakfast' : 'both']);
            }
        }

        if (! $user->mealLogs()->exists()) {
            $today = CarbonImmutable::today(config('handi.timezone'));
            $dishes = KitchenDish::withDetails()->where('user_id', $user->id)->get()->keyBy(fn ($d) => $d->effectiveName());

            foreach ([
                [1, 'Anda Paratha', 'lunch'], [2, 'Chana Daal', 'dinner'], [3, 'Qeema', 'dinner'], [4, 'Biryani', 'dinner'],
                [5, 'Grandma’s Qeema Matar', 'dinner'], [6, 'Biryani', 'lunch'], [9, 'Daal Chawal', 'dinner'], [10, 'Qeema', 'lunch'],
                [11, 'Aloo Gobi', 'dinner'], [12, 'Matar Pulao', 'dinner'], [14, 'Bhindi', 'dinner'], [18, 'Karela', 'lunch'],
                [21, 'Fish Fry', 'dinner'], [25, 'Mom’s Karhi', 'dinner'],
            ] as [$ago, $name, $meal]) {
                $dish = $dishes[$name] ?? null;
                if (! $dish) {
                    continue;
                }
                MealLog::create([
                    'user_id' => $user->id, 'kitchen_dish_id' => $dish->id, 'dish_name' => $name,
                    'category_key' => $dish->effectiveCategory()?->key, 'date' => $today->subDays($ago), 'meal' => $meal,
                    'source' => $ago % 3 === 0 ? 'manual' : 'suggestion', 'logged_at' => $today->subDays($ago)->setTime(20, 15),
                ]);
            }

            $user->appNotifications()->create(['type' => NotificationType::KitchenReady, 'title' => 'Your kitchen is ready',
                'body' => $dishes->count().' dishes saved. Your first morning picks arrive tomorrow at 9:00 AM.', 'cta_label' => 'View dishes', 'deep_link' => '/dishes', 'image_key' => 'biryani', 'read_at' => now()]);
            $user->appNotifications()->create(['type' => NotificationType::Sync, 'title' => '2 meals synced',
                'body' => 'The meals you logged offline yesterday are now saved to your account.', 'cta_label' => 'See history', 'deep_link' => '/history']);

            Activity::log($user, 'auth.registered', 'Created an account (demo data)', $user);
        }
    }
}
