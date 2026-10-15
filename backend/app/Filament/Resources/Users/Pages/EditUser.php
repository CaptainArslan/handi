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

    /** `is_admin` isn't mass-assignable (so the API can never set it); save it explicitly here. */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $makeAdmin = array_key_exists('is_admin', $data) ? (bool) $data['is_admin'] : null;
        unset($data['is_admin']);

        $record->update($data);

        if ($makeAdmin !== null && ! $record->is(auth()->user())) {
            $record->forceFill(['is_admin' => $makeAdmin])->save();
        }

        return $record;
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
