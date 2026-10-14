<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Avatar;
use App\Models\User;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The latest sign-ups, with where they cook. */
class NewUsers extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Newest kitchens')
            ->query(fn () => User::query()->with('kitchen')->where('is_admin', false)->latest('id')->limit(3))
            ->paginated(false)
            ->recordUrl(fn (User $r) => UserResource::getUrl('view', ['record' => $r]))
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(32)->state(fn (User $r) => Avatar::for($r->name)),
                TextColumn::make('name')->weight('semibold')->description(fn (User $r) => $r->kitchen?->location_label ?? 'No location yet'),
                TextColumn::make('created_at')->label('Joined')->since()->color('gray')->alignEnd(),
            ])
            ->emptyStateHeading('No users yet')
            ->emptyStateIcon('heroicon-o-users');
    }
}
