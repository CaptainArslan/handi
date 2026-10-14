<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'Phones';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-device-phone-mobile';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->placeholder('—'),
                TextColumn::make('platform')->badge(),
                TextColumn::make('push')->label('Push')->badge()->state(fn ($record) => filled($record->expo_push_token) ? 'Enabled' : 'No token')
                    ->color(fn (string $state) => $state === 'Enabled' ? 'success' : 'gray'),
                TextColumn::make('last_seen_at')->label('Last seen')->since(),
                TextColumn::make('created_at')->label('Added')->date('j M Y'),
            ])
            ->recordActions([DeleteAction::make()->label('Remove')]);
    }
}
