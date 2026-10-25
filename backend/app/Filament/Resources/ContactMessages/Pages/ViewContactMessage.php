<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ContactMessage $message */
        $message = $this->record;
        if (! $message->read_at) {
            $message->forceFill(['read_at' => now()])->save();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactMessageResource::replyAction(),
            ContactMessageResource::resolveAction(),
            ContactMessageResource::statusAction(),
            ActionGroup::make([
                ContactMessageResource::mailAppAction(),
                ContactMessageResource::downloadAction(),
                ContactMessageResource::reopenAction(),
                ContactMessageResource::spamAction(),
                DeleteAction::make(),
            ]),
        ];
    }
}
