<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Users\UserResource;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use App\Filament\Support\ActivityEvents;
use App\Filament\Support\Avatar;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Everything every user did: logins, meals, dish edits, settings, admin actions. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $pluralModelLabel = 'Activity log';

    protected static ?string $modelLabel = 'Activity entry';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label('When')->dateTime('j M Y, g:i:s A'),
            TextEntry::make('user.email')->label('User')->placeholder('— (deleted or system)'),
            TextEntry::make('event')->badge()->formatStateUsing(fn (string $state) => ActivityEvents::label($state))->color(fn (string $state) => ActivityEvents::color($state))->icon(fn (string $state) => ActivityEvents::icon($state)),
            TextEntry::make('description'),
            TextEntry::make('ip_address')->label('IP')->placeholder('—'),
            TextEntry::make('user_agent')->label('Device')->placeholder('—'),
            KeyValueEntry::make('properties')->columnSpanFull()->placeholder('—')
                ->state(fn (ActivityLog $r) => collect($r->properties ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v))->all()),
        ]);
    }

    public static function table(Table $table, bool $withUser = true): Table
    {
        $columns = [];

        if ($withUser) {
            $columns[] = ImageColumn::make('avatar')->label('')->circular()->imageSize(34)
                ->state(fn (ActivityLog $r) => Avatar::for($r->user?->name ?? 'System'));
            $columns[] = TextColumn::make('user.name')->label('User')->placeholder('System')->weight('semibold')
                ->description(fn (ActivityLog $r) => $r->user?->email ?? ($r->properties['email'] ?? null))
                ->url(fn (ActivityLog $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null)
                ->searchable();
        }

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ...$columns,
                TextColumn::make('event')->badge()->searchable()
                    ->formatStateUsing(fn (string $state) => ActivityEvents::label($state))
                    ->color(fn (string $state) => ActivityEvents::color($state))
                    ->icon(fn (string $state) => ActivityEvents::icon($state)),
                TextColumn::make('description')->wrap()->searchable(),
                TextColumn::make('created_at')->label('When')->since()->sortable()->color('gray')
                    ->description(fn (ActivityLog $r) => $r->created_at?->timezone(config('handi.timezone'))->format('j M, g:i A')),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options(ActivityEvents::options())->searchable()->multiple(),
                Filter::make('today')->label('Today')->query(fn (Builder $query) => $query->where('created_at', '>=', now()->startOfDay())),
                Filter::make('week')->label('Last 7 days')->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7))),
            ])
            ->recordActions([ViewAction::make()->iconButton()])
            ->emptyStateIcon('heroicon-o-bolt')
            ->emptyStateHeading('No activity yet');
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
