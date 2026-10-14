<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MealLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'mealLogs';

    protected static ?string $title = 'Meal history';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('dish_name')->label('Dish')->weight('semibold')->searchable()
                    ->description(fn ($record) => ucfirst((string) $record->category_key)),
                TextColumn::make('date')->date('D, j M Y')->sortable(),
                TextColumn::make('meal')->badge()->color('primary'),
                TextColumn::make('source')->badge()
                    ->formatStateUsing(fn ($state) => $state?->value === 'suggestion' ? 'From picks' : 'Logged manually')
                    ->color(fn ($state) => $state?->value === 'suggestion' ? 'success' : 'gray'),
                TextColumn::make('logged_at')->label('Logged')->since()->color('gray'),
                TextColumn::make('deleted_at')->label('Deleted')->since()->placeholder('—')->color('danger')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('meal')->options(['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner']),
                SelectFilter::make('source')->options(['suggestion' => 'From picks', 'manual' => 'Manual']),
                TrashedFilter::make(),
            ]);
    }
}
