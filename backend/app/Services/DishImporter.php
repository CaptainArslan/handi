<?php

namespace App\Services;

use App\Enums\Cost;
use App\Enums\Effort;
use App\Enums\UsualMeal;
use App\Enums\Weather;
use App\Models\Category;
use App\Models\Dish;
use App\Support\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Bulk import of library dishes from a CSV or Excel (.xlsx) sheet.
 *
 * Columns (first row = headers, any order, case-insensitive):
 *   name*, category*, weather*, effort, cost, usual_meal, note, description, image_key, active
 * - category: key or name ("vegetables" / "Vegetables")
 * - weather: hot, mild, cold, rainy or any — several separated by , | ; or /
 * - effort: quick | medium | heavy (default medium) · cost: low | medium | high (default low)
 * - usual_meal: breakfast | lunch | dinner | both (default both)
 * - active: yes/no, true/false, 1/0 (default yes)
 * Rows are matched on the dish name (slug): existing dishes are updated or skipped.
 */
class DishImporter
{
    public const COLUMNS = ['name', 'category', 'weather', 'effort', 'cost', 'usual_meal', 'note', 'description', 'image_key', 'active'];

    public const MAX_ROWS = 2000;

    /**
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>, total: int}
     */
    public function import(string $path, bool $updateExisting = true, ?string $originalName = null): array
    {
        $rows = $this->read($path, $originalName ?? $path);
        if ($rows === []) {
            throw new RuntimeException('The file is empty.');
        }

        $headers = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->replace([' ', '-'], '_')->toString(), array_shift($rows));
        foreach (['name', 'category', 'weather'] as $required) {
            if (! in_array($required, $headers, true)) {
                throw new RuntimeException("Missing the “{$required}” column. Download the template to see the expected headers.");
            }
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Import up to '.self::MAX_ROWS.' dishes at a time.');
        }

        $categories = Category::all();
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'total' => 0];
        $seen = [];

        DB::transaction(function () use ($rows, $headers, $categories, $updateExisting, &$result, &$seen) {
            foreach ($rows as $i => $cells) {
                $line = $i + 2; // + header row, 1-based
                $row = [];
                foreach ($headers as $c => $h) {
                    $row[$h] = trim((string) ($cells[$c] ?? ''));
                }
                if (implode('', $row) === '') {
                    continue; // blank line
                }
                $result['total']++;

                [$data, $error] = $this->validateRow($row, $categories);
                if ($error) {
                    $result['errors'][$line] = $error;

                    continue;
                }

                $slug = Str::slug($data['name']);
                if (isset($seen[$slug])) {
                    $result['errors'][$line] = "“{$data['name']}” appears twice in the file (row {$seen[$slug]}).";

                    continue;
                }
                $seen[$slug] = $line;

                $existing = Dish::where('slug', $slug)->first();
                if ($existing && ! $updateExisting) {
                    $result['skipped']++;

                    continue;
                }

                if ($existing) {
                    $existing->update($data);
                    $result['updated']++;
                } else {
                    Dish::create($data + ['slug' => $slug]);
                    $result['created']++;
                }
            }
        });

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function validateRow(array $row, $categories): array
    {
        $name = Str::of($row['name'] ?? '')->squish()->toString();
        if (mb_strlen($name) < 2) {
            return [[], 'Name is missing or too short.'];
        }
        if (mb_strlen($name) > 150) {
            return [[], 'Name is longer than 150 characters.'];
        }

        $catInput = Str::lower($row['category'] ?? '');
        $category = $categories->first(fn (Category $c) => $c->key === $catInput || Str::lower($c->name) === $catInput);
        if (! $category) {
            return [[], 'Unknown category “'.($row['category'] ?? '').'”. Use one of: '.$categories->pluck('key')->join(', ').'.'];
        }

        $weather = collect(preg_split('/[,|;\/]+/', Str::lower($row['weather'] ?? '')))->map(fn ($w) => trim($w))->filter()->unique()->values();
        $bad = $weather->reject(fn ($w) => in_array($w, Weather::values(), true));
        if ($weather->isEmpty() || $bad->isNotEmpty()) {
            return [[], 'Weather must be hot, mild, cold, rainy or any'.($bad->isNotEmpty() ? ' (got “'.$bad->join(', ').'”)' : '').'.'];
        }
        if ($weather->contains('any')) {
            $weather = collect(['any']);
        }

        $pick = function (string $field, array $allowed, string $default) use ($row): ?string {
            $v = Str::lower($row[$field] ?? '');

            return $v === '' ? $default : (in_array($v, $allowed, true) ? $v : null);
        };

        $effort = $pick('effort', Effort::values(), 'medium');
        $cost = $pick('cost', Cost::values(), 'low');
        $meal = $pick('usual_meal', UsualMeal::values(), 'both');
        foreach (['effort' => [$effort, Effort::values()], 'cost' => [$cost, Cost::values()], 'usual_meal' => [$meal, UsualMeal::values()]] as $f => [$v, $allowed]) {
            if ($v === null) {
                return [[], ucfirst(str_replace('_', ' ', $f)).' must be '.implode(', ', $allowed).'.'];
            }
        }

        $imageKey = Str::lower($row['image_key'] ?? '');
        if ($imageKey !== '' && ! in_array($imageKey, Media::ART_KEYS, true)) {
            return [[], 'Unknown image_key “'.$imageKey.'”. Use one of: '.implode(', ', Media::ART_KEYS).' (or leave empty).'];
        }

        $activeRaw = Str::lower($row['active'] ?? '');
        $active = $activeRaw === '' ? true : in_array($activeRaw, ['1', 'yes', 'y', 'true', 'active'], true);

        return [[
            'name' => $name,
            'category_id' => $category->id,
            'default_weather' => $weather->all(),
            'default_effort' => $effort,
            'default_cost' => $cost,
            'usual_meal' => $meal,
            'note' => Str::limit($row['note'] ?? '', 80, '') ?: null,
            'description' => ($row['description'] ?? '') !== '' ? Str::limit($row['description'], 1000, '') : null,
            'image_key' => $imageKey ?: null,
            'is_active' => $active,
        ], null];
    }

    /** @return array<int, array<int, mixed>> all rows including the header */
    private function read(string $path, string $name): array
    {
        $ext = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $reader = match ($ext) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw new RuntimeException('Upload a .csv or .xlsx file.'),
        };

        $rows = [];
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();
                    // strip a UTF-8 BOM from the very first cell (Excel CSV)
                    if ($rows === [] && isset($values[0]) && is_string($values[0])) {
                        $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0]);
                    }
                    $rows[] = $values;
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /** A ready-to-fill CSV with the headers and two example rows. */
    public static function templateCsv(): string
    {
        $lines = [
            implode(',', self::COLUMNS),
            'Shimla Mirch Aloo,vegetables,"hot, mild",quick,low,both,Weeknight easy,Capsicum and potato stir-fry,bhindi,yes',
            'Chicken Pulao,rice,"cold|rainy",heavy,medium,dinner,Sunday special,,biryani,yes',
        ];

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }
}
