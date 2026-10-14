<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\Dish;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Categories, the dish library and Pakistani cities. Safe to re-run (matched by key/slug). */
class CatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        // key, name, default art
        ['vegetables', 'Vegetables', 'bhindi'],
        ['lentils', 'Lentils', 'lentils'],
        ['chicken', 'Chicken', 'karahi'],
        ['beef', 'Beef', 'qeema'],
        ['mutton', 'Mutton', 'karahi'],
        ['fish', 'Fish', 'fish'],
        ['rice', 'Rice', 'biryani'],
        ['breakfast', 'Breakfast', 'paratha'],
        ['snacks', 'Snacks', 'karhi'],
    ];

    /** province => [short, [city, lat, lng, popular]] */
    private const CITIES = [
        'Punjab' => ['Punjab', [
            ['Lahore', 31.5497, 74.3436, true], ['Faisalabad', 31.4180, 73.0790, false], ['Rawalpindi', 33.5973, 73.0479, true],
            ['Multan', 30.1575, 71.5249, false], ['Gujranwala', 32.1617, 74.1883, false], ['Sialkot', 32.4945, 74.5229, false],
            ['Bahawalpur', 29.3956, 71.6836, false], ['Sargodha', 32.0836, 72.6711, false],
        ]],
        'Sindh' => ['Sindh', [
            ['Karachi', 24.8607, 67.0011, true], ['Hyderabad', 25.3960, 68.3578, false], ['Sukkur', 27.7052, 68.8574, false], ['Larkana', 27.5570, 68.2264, false],
        ]],
        'Khyber Pakhtunkhwa' => ['KP', [
            ['Peshawar', 34.0151, 71.5249, true], ['Abbottabad', 34.1688, 73.2215, false], ['Mardan', 34.1986, 72.0404, false], ['Swat', 34.7717, 72.3600, false],
        ]],
        'Balochistan' => ['Balochistan', [
            ['Quetta', 30.1798, 66.9750, false], ['Gwadar', 25.1264, 62.3225, false],
        ]],
        'Islamabad Capital Territory' => ['ICT', [
            ['Islamabad', 33.6844, 73.0479, true],
        ]],
        'Gilgit-Baltistan' => ['GB', [
            ['Gilgit', 35.9208, 74.3089, false], ['Skardu', 35.2971, 75.6333, false],
        ]],
        'Azad Kashmir' => ['AJK', [
            ['Muzaffarabad', 34.3700, 73.4711, false], ['Mirpur', 33.1480, 73.7519, false],
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $categories = collect(self::CATEGORIES)->mapWithKeys(function (array $c, int $i) {
                return [$c[0] => Category::updateOrCreate(['key' => $c[0]], ['name' => $c[1], 'image_key' => $c[2], 'sort_order' => $i + 1, 'is_active' => true])];
            });

            foreach (require __DIR__.'/data/library.php' as [$name, $category, $note, $imageKey, $weather, $effort, $cost, $meal]) {
                Dish::updateOrCreate(['slug' => Str::slug($name)], [
                    'name' => $name,
                    'category_id' => $categories[$category]->id,
                    'note' => $note,
                    'image_key' => $imageKey,
                    'default_weather' => $weather,
                    'default_effort' => $effort,
                    'default_cost' => $cost,
                    'usual_meal' => $meal,
                    'is_active' => true,
                ]);
            }

            $p = 0;
            foreach (self::CITIES as $provinceName => [$short, $cities]) {
                $province = Province::updateOrCreate(['name' => $provinceName], ['short_name' => $short, 'sort_order' => ++$p]);

                foreach ($cities as $i => [$city, $lat, $lng, $popular]) {
                    City::updateOrCreate(['slug' => Str::slug($city)], [
                        'province_id' => $province->id, 'name' => $city, 'lat' => $lat, 'lng' => $lng,
                        'is_popular' => $popular, 'sort_order' => $i + 1,
                    ]);
                }
            }
        });
    }
}
