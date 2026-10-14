<?php

use App\Filament\Resources\Dishes\Pages\ListDishes;
use App\Models\Dish;
use App\Models\User;
use App\Services\DishImporter;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(fn () => seedCatalog());

function csvFile(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'dishes').'.csv';
    file_put_contents($path, $body);

    return $path;
}

it('imports new dishes and updates existing ones from CSV', function () {
    $csv = "name,category,weather,effort,cost,usual_meal,note,image_key,active\n"
        ."Shimla Mirch Aloo,vegetables,\"hot, mild\",quick,low,both,Weeknight easy,bhindi,yes\n"
        ."Bhindi,Vegetables,cold,heavy,high,dinner,Updated note,,yes\n";

    $r = app(DishImporter::class)->import(csvFile($csv), true, 'dishes.csv');

    expect($r)->toMatchArray(['created' => 1, 'updated' => 1, 'skipped' => 0, 'errors' => []]);
    $new = Dish::where('slug', 'shimla-mirch-aloo')->first();
    expect($new->default_weather)->toBe(['hot', 'mild'])->and($new->category->key)->toBe('vegetables')
        ->and(Dish::where('slug', 'bhindi')->first()->note)->toBe('Updated note');
});

it('reports row errors and skips existing dishes when asked', function () {
    $csv = "\xEF\xBB\xBFname,category,weather\n"
        ."Bhindi,vegetables,hot\n"
        ."X,vegetables,hot\n"
        ."Kadhi Pakora,soups,hot\n"
        ."Lauki,vegetables,sunny\n"
        ."Lauki Chana,vegetables,any\n";

    $r = app(DishImporter::class)->import(csvFile($csv), false, 'dishes.csv');

    expect($r['created'])->toBe(1)->and($r['skipped'])->toBe(1)->and(array_keys($r['errors']))->toBe([3, 4, 5])
        ->and($r['errors'][4])->toContain('Unknown category');
});

it('reads Excel files and needs the required columns', function () {
    $path = tempnam(sys_get_temp_dir(), 'dishes').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Name', 'Category', 'Weather', 'Effort']));
    $writer->addRow(Row::fromValues(['Mutton Pulao', 'rice', 'cold|rainy', 'heavy']));
    $writer->close();

    $r = app(DishImporter::class)->import($path, true, 'dishes.xlsx');
    expect($r['created'])->toBe(1)->and(Dish::where('slug', 'mutton-pulao')->first()->default_effort->value)->toBe('heavy');

    expect(fn () => app(DishImporter::class)->import(csvFile("title,category\nA,rice\n"), true, 'x.csv'))
        ->toThrow(RuntimeException::class, 'Missing the “name” column');
});

it('imports from the admin list page', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $file = UploadedFile::fake()->createWithContent('dishes.csv', "name,category,weather\nTinda Masala,vegetables,hot\n");

    Livewire::test(ListDishes::class)
        ->callAction('import', data: ['file' => $file, 'update_existing' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('Dishes imported');

    expect(Dish::where('slug', 'tinda-masala')->exists())->toBeTrue();
});
