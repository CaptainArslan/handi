<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ActivityLogsRelationManager;
use App\Filament\Resources\Users\RelationManagers\DevicesRelationManager;
use App\Filament\Resources\Users\RelationManagers\KitchenDishesRelationManager;
use App\Filament\Resources\Users\RelationManagers\MealLogsRelationManager;
use App\Filament\Resources\Users\RelationManagers\NotificationsRelationManager;
use App\Filament\Resources\Users\RelationManagers\TokensRelationManager;
use App\Models\User;
use BackedEnum;
use App\Filament\Support\Avatar;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/** Every account with its kitchen, dishes, history, notifications, devices and activity. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|BackedEnum|null $activeNavigationIcon = 'heroicon-s-users';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) User::where('is_admin', false)->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Account')->icon('heroicon-o-user-circle')->description('Name, sign-in email and timezone used for “today”.')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(50)->prefixIcon('heroicon-m-user'),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->prefixIcon('heroicon-m-envelope'),
                TextInput::make('timezone')->required()->rule('timezone:all')->default('Asia/Karachi')->prefixIcon('heroicon-m-globe-asia-australia'),
            ]),
            Section::make('Security')->icon('heroicon-o-shield-check')->description('Reset the password or grant panel access.')->columns(2)->schema([
                TextInput::make('new_password')->label('Set a new password')->password()->revealable()->minLength(8)
                    ->dehydrated(false)->helperText('Leave empty to keep the current password.'),
                Toggle::make('is_admin')->label('Super admin')->helperText('Can sign in to this admin panel.')->inline(false)
                    ->disabled(fn (?User $record) => $record?->is(auth()->user())) // can't remove your own access,
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            // Header card: avatar, name, email, status pills + key numbers.
            ViewEntry::make('summary')->hiddenLabel()->columnSpanFull()->view('filament.users.summary'),

            Section::make('Profile')->icon('heroicon-o-user-circle')->columnSpan(1)->columns(2)->schema([
                TextEntry::make('name')->columnSpanFull()->weight('semibold'),
                TextEntry::make('email')->copyable()->icon('heroicon-m-envelope')->columnSpanFull(),
                TextEntry::make('is_admin')->label('Role')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Super admin' : 'App user')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray'),
                TextEntry::make('timezone')->icon('heroicon-m-globe-asia-australia'),
                TextEntry::make('created_at')->label('Joined')->date('j M Y')->helperText(fn (User $r) => $r->created_at?->diffForHumans()),
                TextEntry::make('terms_accepted_at')->label('Terms accepted')->date('j M Y')->placeholder('—'),
                TextEntry::make('last_login_at')->label('Last login')->since()->placeholder('Never')
                    ->helperText(fn (User $r) => $r->last_login_at?->timezone(config('handi.timezone'))->format('j M, g:i A')),
                TextEntry::make('last_active_at')->label('Last active')->since()->placeholder('Never'),
                TextEntry::make('password_changed_at')->label('Password changed')->since()->placeholder('—'),
            ]),
            Section::make('Kitchen')->icon('heroicon-o-home-modern')->columnSpan(1)->columns(2)->schema([
                TextEntry::make('kitchen.family_size')->label('Family size')->suffix(' people')->placeholder('—'),
                TextEntry::make('kitchen.budget')->label('Budget')->badge()->color('primary')->placeholder('—'),
                TextEntry::make('kitchen.repeat_gap_days')->label('No repeats within')->suffix(' days')->placeholder('—'),
                TextEntry::make('kitchen.meals')->label('Meals')->badge()->color('gray')->formatStateUsing(fn ($state) => ucfirst($state instanceof \BackedEnum ? $state->value : (string) $state))->placeholder('—'),
                TextEntry::make('kitchen.location_label')->label('Location')->icon('heroicon-m-map-pin')->placeholder('Not set')->columnSpanFull(),
                TextEntry::make('kitchen.location_mode')->label('Location from')->badge()->color('gray')->formatStateUsing(fn ($state) => ($state instanceof \BackedEnum ? $state->value : $state) === 'gps' ? 'Phone location' : 'Chosen city')->placeholder('—'),
                TextEntry::make('kitchen_coordinates')->label('Coordinates')
                    ->state(fn (User $r) => $r->kitchen?->lat !== null ? $r->kitchen->lat.', '.$r->kitchen->lng : null)->placeholder('—'),
            ]),
            Section::make('Reminders & phones')->icon('heroicon-o-bell')->columnSpan(1)->columns(2)->schema([
                TextEntry::make('kitchen.notifications_enabled')->label('Daily reminder')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'On' : 'Off')->color(fn ($state) => $state ? 'success' : 'gray'),
                TextEntry::make('kitchen.notify_time')->label('Reminder time')->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? date('g:i A', strtotime($state)) : null),
                TextEntry::make('devices_count')->label('Phones')->state(fn (User $r) => $r->devices()->count()),
                TextEntry::make('push_count')->label('With push')->state(fn (User $r) => $r->devices()->whereNotNull('expo_push_token')->count()),
                TextEntry::make('unread')->label('Unread notifications')->state(fn (User $r) => $r->appNotifications()->whereNull('read_at')->count()),
                TextEntry::make('kitchen.last_synced_at')->label('Last offline sync')->since()->placeholder('Never'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('kitchen')
                ->withCount(['kitchenDishes as dishes_count' => fn ($q) => $q->whereNull('removed_at'), 'mealLogs']))
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(40)
                    ->state(fn (User $r) => Avatar::for($r->name)),
                TextColumn::make('name')->label('User')->searchable(['name', 'email'])->sortable()->weight('semibold')
                    ->description(fn (User $r) => $r->email),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (User $r) => match (true) {
                        $r->is_admin => 'Admin',
                        $r->last_active_at?->gt(now()->subDay()) => 'Active today',
                        $r->last_active_at?->gt(now()->subDays(7)) => 'This week',
                        $r->created_at?->gt(now()->subDays(7)) => 'New',
                        default => 'Idle',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Admin' => 'warning', 'Active today' => 'success', 'This week' => 'primary', 'New' => 'info', default => 'gray',
                    }),
                TextColumn::make('kitchen.location_label')->label('Location')->icon('heroicon-m-map-pin')->iconColor('gray')
                    ->placeholder('—')->toggleable(),
                TextColumn::make('kitchen.family_size')->label('Family')->suffix(' people')->placeholder('—')->toggleable(),
                TextColumn::make('dishes_count')->label('Dishes')->sortable()->badge()->color('gray')->alignCenter(),
                TextColumn::make('meal_logs_count')->label('Meals')->sortable()->badge()->color('success')->alignCenter(),
                TextColumn::make('last_active_at')->label('Last active')->since()->sortable()->placeholder('Never')->color('gray'),
                TextColumn::make('created_at')->label('Joined')->date('j M Y')->sortable()->color('gray')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label('Super admins'),
                Filter::make('active_week')->label('Active in the last 7 days')
                    ->query(fn (Builder $query) => $query->where('last_active_at', '>=', now()->subDays(7))),
                Filter::make('new_week')->label('Joined in the last 7 days')
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7))),
                SelectFilter::make('city')->label('City')
                    ->relationship('kitchen.city', 'name')->searchable()->preload(),
            ])
            ->recordUrl(fn (User $r) => static::getUrl('view', ['record' => $r]))
            ->recordActions([
                ActionGroup::make([ViewAction::make(), EditAction::make()])->icon('heroicon-m-ellipsis-horizontal')->color('gray'),
            ])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('No users yet');
    }

    public static function getRelations(): array
    {
        return [
            KitchenDishesRelationManager::class,
            MealLogsRelationManager::class,
            NotificationsRelationManager::class,
            DevicesRelationManager::class,
            TokensRelationManager::class,
            ActivityLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /** Applied by EditUser when "Set a new password" is filled. */
    public static function applyNewPassword(User $user, ?string $password): void
    {
        if (filled($password)) {
            $user->forceFill(['password' => Hash::make($password), 'password_changed_at' => now()])->save();
        }
    }
}
