<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\KitchenDish;
use App\Services\PickService;
use App\Support\UserClock;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Support\ImagePreview;

class KitchenDishesRelationManager extends RelationManager
{
    protected static string $relationship = 'kitchenDishes';

    protected static ?string $title = 'Dishes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-cake';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $last = app(PickService::class)->lastCooked($this->ownerRecord);
        $today = UserClock::today($this->ownerRecord);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['dish.category', 'ownCategory']))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('photo')->label('')->state(fn (KitchenDish $r) => $r->imageUrl('thumb'))->circular()->imageSize(44)
                    ->action(ImagePreview::action(fn (KitchenDish $r) => $r->imageUrl('card'), fn (KitchenDish $r) => $r->effectiveName(), fn (KitchenDish $r) => \App\Support\Media::artBg($r->imageKey()))),
                TextColumn::make('name')->label('Dish')->weight('semibold')->state(fn (KitchenDish $r) => $r->effectiveName())
                    ->description(fn (KitchenDish $r) => $r->effectiveCategory()?->name)
                    ->searchable(query: fn (Builder $q, string $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhereHas('dish', fn ($d) => $d->where('name', 'like', "%{$s}%")))),
                TextColumn::make('is_custom')->label('Source')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Custom' : 'Library')->color(fn (bool $state) => $state ? 'warning' : 'gray'),
                TextColumn::make('weather')->state(fn (KitchenDish $r) => $r->effectiveWeather())->formatStateUsing(fn ($state) => ucfirst($state instanceof \BackedEnum ? $state->value : (string) $state))->badge()->color(fn (string $state) => match ($state) { 'hot' => 'warning', 'cold', 'rainy' => 'info', 'mild' => 'success', default => 'gray' }),
                TextColumn::make('effort')->state(fn (KitchenDish $r) => $r->effectiveEffort()->getLabel())->badge()->color('gray'),
                TextColumn::make('cost')->state(fn (KitchenDish $r) => $r->effectiveCost()->getLabel())->badge()->color('gray'),
                TextColumn::make('last_cooked')->label('Last cooked')
                    ->state(fn (KitchenDish $record) => isset($last[$record->id]) ? \App\Support\Ago::days(PickService::daysSince($last[$record->id], $today)) : 'Never'),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (KitchenDish $r) => $r->removed_at ? 'Removed' : 'In picks')
                    ->color(fn (string $state) => $state === 'Removed' ? 'danger' : 'success'),
            ])
            ->filters([
                TernaryFilter::make('is_custom')->label('Custom dishes'),
                TernaryFilter::make('removed')->label('Removed')
                    ->queries(true: fn ($q) => $q->whereNotNull('removed_at'), false: fn ($q) => $q->whereNull('removed_at')),
            ]);
    }
}
