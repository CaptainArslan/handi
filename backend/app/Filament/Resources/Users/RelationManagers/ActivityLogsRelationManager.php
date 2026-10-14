<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ActivityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Activity log';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-bolt';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return ActivityLogResource::table($table, withUser: false);
    }
}
