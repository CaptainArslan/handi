<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'appNotifications';

    protected static ?string $title = 'Notifications';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-bell';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Sent')->dateTime('j M, g:i A')->sortable(),
                TextColumn::make('title')->weight('semibold')->description(fn ($record) => $record->body)->wrap(),
                TextColumn::make('type')->badge()->color('gray')->formatStateUsing(fn ($state) => str((string) ($state->value ?? $state))->replace('_', ' ')->ucfirst()),
                TextColumn::make('read')->label('Status')->badge()->state(fn ($record) => $record->read_at ? 'Read' : 'Unread')
                    ->color(fn (string $state) => $state === 'Unread' ? 'warning' : 'gray'),
                TextColumn::make('deleted_at')->label('Cleared')->since()->placeholder('—'),
            ]);
    }
}
