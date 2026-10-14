<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityLog;
use Filament\Actions\Action;
use App\Filament\Support\ActivityEvents;
use App\Filament\Support\Avatar;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivity extends TableWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest activity')
            ->query(fn () => ActivityLog::query()->with('user')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(32)
                    ->state(fn (ActivityLog $r) => Avatar::for($r->user?->name ?? 'System')),
                TextColumn::make('user.name')->label('User')->placeholder('System')->weight('semibold')
                    ->description(fn (ActivityLog $r) => $r->created_at?->diffForHumans())
                    ->url(fn (ActivityLog $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('event')->label('Event')->badge()
                    ->formatStateUsing(fn (string $state) => ActivityEvents::label($state))
                    ->color(fn (string $state) => ActivityEvents::color($state))
                    ->icon(fn (string $state) => ActivityEvents::icon($state)),
                TextColumn::make('description')->wrap()->color('gray'),
            ])
            ->headerActions([Action::make('all')->label('See all')->url(ActivityLogResource::getUrl('index'))->link()])
            ->emptyStateHeading('Nothing yet')
            ->emptyStateIcon('heroicon-o-bolt');
    }
}
