<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    /** Open (pending + in progress) first, then one tab per status. */
    public function getTabs(): array
    {
        $counts = ContactMessage::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $open = ($counts[ContactStatus::Pending->value] ?? 0) + ($counts[ContactStatus::InProgress->value] ?? 0);

        $tabs = [
            'open' => Tab::make('Open')->icon('heroicon-m-inbox-stack')->badge($open ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ContactStatus::Pending, ContactStatus::InProgress])),
        ];

        foreach (ContactStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())->icon($status->getIcon())
                ->badge(($counts[$status->value] ?? 0) ?: null)->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        $tabs['all'] = Tab::make('All')->badge(array_sum($counts->all()) ?: null);

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }
}
