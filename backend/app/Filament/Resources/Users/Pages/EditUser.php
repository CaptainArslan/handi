<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Activity;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function afterSave(): void
    {
        $password = $this->form->getRawState()['new_password'] ?? null;
        UserResource::applyNewPassword($this->record, $password);

        Activity::log($this->record, 'admin.user_updated', 'Account edited by '.auth()->user()?->email, null, ['password_reset' => filled($password)]);
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('view', ['record' => $this->record]);
    }
}
