<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Activity;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return 'Everything about this account: kitchen, dishes, meal history, notifications, phones and activity.';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon('heroicon-m-pencil-square'),
            ActionGroup::make([
            Action::make('revokeSessions')
                ->label('Log out everywhere')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Signs this user out on every phone and stops their reminders until they log in again.')
                ->action(function (User $record) {
                    $record->tokens()->delete();
                    $record->devices()->delete();
                    Activity::log($record, 'admin.sessions_revoked', 'Signed out everywhere by '.auth()->user()?->email);
                    Notification::make()->title('Signed out on every phone')->success()->send();
                }),
            DeleteAction::make()
                ->modalDescription('Deletes the account and all its data (kitchen, dishes, history, notifications). This can’t be undone.')
                ->before(fn (User $record) => Activity::log(null, 'admin.user_deleted', "Deleted account {$record->email}", null, ['by' => auth()->user()?->email, 'user_id' => $record->id])),
            ])->label('More')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button(),
        ];
    }
}
