<?php

namespace App\Filament\Pages\Auth;

use App\Support\Activity;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * The signed-in super admin's own profile, inside the panel (sidebar + header):
 * a summary card on the left, account details and password on the right.
 */
class EditProfile extends BaseEditProfile
{
    public static function isSimple(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'My profile';
    }

    public function getSubheading(): ?string
    {
        return 'Your super admin account for the Handi admin panel.';
    }

    public static function getLabel(): string
    {
        return 'My profile';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                View::make('filament.profile.summary')->columnSpan(['lg' => 1]),
                Group::make([
                    Section::make('Account details')
                        ->description('Your name is shown in the header and in the activity log.')
                        ->icon('heroicon-o-user-circle')
                        ->columns(2)
                        ->schema([
                            $this->getNameFormComponent(),
                            $this->getEmailFormComponent(),
                        ]),
                    Section::make('Password')
                        ->description('Leave empty to keep your current password. At least 8 characters.')
                        ->icon('heroicon-o-lock-closed')
                        ->columns(2)
                        ->schema([
                            $this->getPasswordFormComponent()->label('New password'),
                            $this->getPasswordConfirmationFormComponent()->label('Confirm new password'),
                            $this->getCurrentPasswordFormComponent()->columnSpanFull(),
                        ]),
                ])->columnSpan(['lg' => 2]),
            ]),
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $passwordChanged = filled($data['password'] ?? null);
        if ($passwordChanged) {
            $data['password_changed_at'] = now();
        }

        $record = parent::handleRecordUpdate($record, $data);

        Activity::log($record, $passwordChanged ? 'account.password_changed' : 'account.profile_updated',
            $passwordChanged ? 'Changed their admin password' : 'Updated their admin profile');

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Profile saved';
    }
}
