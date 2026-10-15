<?php

namespace App\Filament\Widgets;

use App\Models\MealLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopCookedDishes extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most cooked · top 5')
            ->query(fn (): Builder => MealLog::query()
                ->selectRaw('MIN(id) as id, dish_name, category_key, COUNT(*) as times, COUNT(DISTINCT user_id) as kitchens')
                ->where('date', '>=', now()->subDays(30)->toDateString())
                ->groupBy('dish_name', 'category_key')
                ->orderByDesc('times')
                ->orderBy('dish_name')
                ->limit(5))
            // Grouped query: Filament's extra "order by meal_logs.id" breaks MySQL's ONLY_FULL_GROUP_BY.
            ->defaultKeySort(false)
            ->paginated(false)
            ->columns([
                TextColumn::make('dish_name')->label('Dish')->weight('semibold')
                    ->description(fn ($record) => ucfirst((string) $record->category_key).' · '.$record->kitchens.' '.str('kitchen')->plural($record->kitchens)),
                TextColumn::make('times')->label('Cooked')->badge()->color('success')->suffix('×')->alignEnd(),
            ])
            ->emptyStateHeading('No meals logged yet');
    }
}
