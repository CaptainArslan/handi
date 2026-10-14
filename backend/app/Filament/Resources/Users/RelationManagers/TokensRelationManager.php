<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** App logins (Sanctum tokens), one per phone. */
class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Logins';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-key';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Device'),
                TextColumn::make('created_at')->label('Logged in')->dateTime('j M Y, g:i A'),
                TextColumn::make('last_used_at')->label('Last used')->since()->placeholder('Never'),
            ])
            ->recordActions([DeleteAction::make()->label('Log out')->modalHeading('Log out this phone?')]);
    }
}
