<?php

namespace App\Filament\Resources\ContactMessages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Filament\Resources\Users\UserResource;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Services\ContactService;
use App\Support\Activity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/** Messages sent from the "Contact us" form (also emailed to handi.contact.to), with an inbox workflow. */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $modelLabel = 'Contact message';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Contact messages';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    /** Messages still waiting for the team. */
    public static function getNavigationBadge(): ?string
    {
        $open = ContactMessage::where('status', ContactStatus::Pending)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending messages';
    }

    // ------------------------------------------------------------------ Actions

    /** Pick any status, with an optional private note. */
    public static function statusAction(): Action
    {
        return Action::make('status')
            ->label('Change status')
            ->icon('heroicon-o-arrows-right-left')
            ->modalWidth('lg')
            ->fillForm(fn (ContactMessage $record) => ['status' => $record->status, 'admin_note' => $record->admin_note])
            ->schema([
                ToggleButtons::make('status')->options(ContactStatus::class)->required()->inline(false),
                Textarea::make('admin_note')->label('Team note (private)')->rows(3)->maxLength(2000)
                    ->placeholder('e.g. Replied on WhatsApp, fixed in 1.0.3'),
            ])
            ->action(function (ContactMessage $record, array $data) {
                static::move($record, $data['status'] instanceof ContactStatus ? $data['status'] : ContactStatus::from($data['status']), $data['admin_note'] ?? '');
                Notification::make()->success()->title('Status: '.$record->status->getLabel())->send();
            });
    }

    /** One click: answered or fixed. */
    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label('Mark resolved')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ContactMessage $record) => $record->status !== ContactStatus::Resolved)
            ->action(function (ContactMessage $record) {
                static::move($record, ContactStatus::Resolved);
                Notification::make()->success()->title('Marked resolved')->send();
            });
    }

    public static function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Reopen')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (ContactMessage $record) => in_array($record->status, [ContactStatus::Resolved, ContactStatus::Spam], true))
            ->action(fn (ContactMessage $record) => static::move($record, ContactStatus::InProgress));
    }

    public static function spamAction(): Action
    {
        return Action::make('spam')
            ->label('Mark as spam')
            ->icon('heroicon-o-no-symbol')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (ContactMessage $record) => $record->status !== ContactStatus::Spam)
            ->action(fn (ContactMessage $record) => static::move($record, ContactStatus::Spam));
    }

    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Download attachment')
            ->icon('heroicon-o-paper-clip')
            ->visible(fn (ContactMessage $r) => $r->attachment_path && Storage::disk(ContactMessage::DISK)->exists($r->attachment_path))
            ->action(fn (ContactMessage $r) => Storage::disk(ContactMessage::DISK)->download($r->attachment_path, $r->attachment_name));
    }

    /** Write and send a reply from Handi (branded email), saved on the message. */
    public static function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Reply')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn (ContactMessage $record) => $record->status !== ContactStatus::Spam)
            ->modalHeading(fn (ContactMessage $record) => 'Reply to '.$record->name)
            ->modalDescription(fn (ContactMessage $record) => 'Sent by email from Handi to '.$record->email.'. If they answer, it goes to '.config('handi.contact.reply_to').'.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Send reply')
            ->fillForm(fn (ContactMessage $record) => [
                'subject' => 'Re: '.$record->topicLabel().' · Handi',
                'body' => 'Hi '.(strtok($record->name, ' ') ?: $record->name).",\n\n",
                'then' => ContactStatus::WaitingOnUser->value,
            ])
            ->schema([
                TextInput::make('subject')->required()->maxLength(150),
                Textarea::make('body')->label('Message')->required()->minLength(2)->maxLength(5000)->rows(9)
                    ->placeholder('Write your answer. Their original message is quoted below it in the email.'),
                ToggleButtons::make('then')->label('After sending, mark as')->inline()->required()
                    ->options([
                        ContactStatus::WaitingOnUser->value => 'Waiting on user',
                        ContactStatus::Resolved->value => 'Resolved',
                        'keep' => 'Keep current status',
                    ])
                    ->icons([
                        ContactStatus::WaitingOnUser->value => ContactStatus::WaitingOnUser->getIcon(),
                        ContactStatus::Resolved->value => ContactStatus::Resolved->getIcon(),
                        'keep' => 'heroicon-m-minus-circle',
                    ]),
            ])
            ->action(function (ContactMessage $record, array $data) {
                $then = $data['then'] === 'keep' ? null : ContactStatus::from($data['then']);
                $reply = app(ContactService::class)->reply($record, auth()->user(), $data['subject'], $data['body'], $then);

                if (! $reply->mailed_at) {
                    Notification::make()->danger()->persistent()->title('Reply saved but NOT emailed')
                        ->body('The mail server said: '.$reply->mail_error.' Check the MAIL_ settings in .env.')->send();

                    return;
                }

                Notification::make()->success()->title('Reply sent to '.$record->email)->send();
            });
    }

    /** Opens the admin's own mail app (only works if one is set up on this computer). */
    public static function mailAppAction(): Action
    {
        return Action::make('mailApp')
            ->label('Open in my email app')
            ->icon('heroicon-o-envelope')
            ->url(fn (ContactMessage $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Re: '.$r->topicLabel()));
    }

    public static function move(ContactMessage $record, ContactStatus $status, ?string $note = null): void
    {
        $from = $record->status;
        $record->moveTo($status, auth()->user(), $note);

        if ($from !== $status) {
            Activity::log(auth()->user(), 'admin.contact_status', "Contact message #{$record->id}: {$from->getLabel()} → {$status->getLabel()}", $record);
        }
    }

    // ------------------------------------------------------------------ View

    public static function infolist(Schema $schema): Schema
    {
        $tz = config('handi.timezone');

        return $schema->columns(3)->components([
            Section::make('Message')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('name')->weight('semibold'),
                TextEntry::make('email')->copyable()->url(fn (ContactMessage $r) => 'mailto:'.$r->email),
                TextEntry::make('topic')->badge()->formatStateUsing(fn (ContactMessage $r) => $r->topicLabel()),
                TextEntry::make('created_at')->label('Sent')->dateTime('j M Y, g:i A', $tz),
                TextEntry::make('message')->columnSpanFull()->prose()->formatStateUsing(fn (string $state) => nl2br(e($state)))->html(),
                TextEntry::make('attachment_name')->label('Attachment')->placeholder('None')
                    ->formatStateUsing(fn (ContactMessage $r) => $r->attachment_name.' ('.$r->attachmentSizeLabel().')'),
                TextEntry::make('user.email')->label('Account')->placeholder('Not signed in')
                    ->url(fn (ContactMessage $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
            ]),
            Section::make('Status')->columnSpan(1)->schema([
                TextEntry::make('status')->badge(),
                TextEntry::make('admin_note')->label('Team note')->placeholder('—'),
                TextEntry::make('status_changed_at')->label('Status changed')->since()->placeholder('—'),
                TextEntry::make('resolved_at')->label('Resolved')->dateTime('j M Y, g:i A', $tz)->placeholder('—')
                    ->visible(fn (ContactMessage $r) => $r->resolved_at !== null),
                TextEntry::make('resolver.name')->label('Resolved by')->placeholder('—')
                    ->visible(fn (ContactMessage $r) => $r->resolved_at !== null),
                TextEntry::make('mailed_at')->label('Emailed to team')->dateTime('j M Y, g:i A', $tz)->placeholder('Not emailed (mail error)'),
            ]),
            Section::make('Replies')->columnSpanFull()
                ->description(fn (ContactMessage $r) => $r->replies()->count() ? null : 'No replies yet. Use “Reply” to answer by email.')
                ->schema([
                    RepeatableEntry::make('replies')->hiddenLabel()->contained(false)
                        ->visible(fn (ContactMessage $r) => $r->replies()->exists())
                        ->schema([
                            TextEntry::make('subject')->weight('semibold')->hiddenLabel()
                                ->belowContent(fn (ContactReply $reply) => ($reply->author?->name ?? 'Admin').' · '.$reply->created_at->timezone(config('handi.timezone'))->format('j M Y, g:i A')),
                            TextEntry::make('mailed_at')->hiddenLabel()->badge()
                                ->state(fn (ContactReply $reply) => $reply->mailed_at ? 'Emailed' : 'Not emailed')
                                ->color(fn (ContactReply $reply) => $reply->mailed_at ? 'success' : 'danger')
                                ->tooltip(fn (ContactReply $reply) => $reply->mail_error),
                            TextEntry::make('body')->hiddenLabel()->prose()->formatStateUsing(fn (string $state) => nl2br(e($state)))->html(),
                        ]),
                ]),
            Section::make('Device')->columnSpanFull()->columns(3)->collapsed()->schema([
                TextEntry::make('device')->placeholder('—')
                    ->formatStateUsing(fn (ContactMessage $r) => trim($r->device.($r->app_version ? ' · v'.$r->app_version : ''))),
                TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                TextEntry::make('user_agent')->label('User agent')->placeholder('—'),
            ]),
        ]);
    }

    // ------------------------------------------------------------------ List

    public static function table(Table $table): Table
    {
        $tz = config('handi.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('replies'))
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (ContactMessage $r) => $r->read_at ? null : 'font-semibold')
            ->columns([
                TextColumn::make('status')->badge()->sortable()
                    ->description(fn (ContactMessage $r) => $r->replies_count ? $r->replies_count.' '.str('reply')->plural($r->replies_count) : null),
                TextColumn::make('name')->weight('semibold')->description(fn (ContactMessage $r) => $r->email)->searchable(['name', 'email']),
                TextColumn::make('message')->limit(48)->tooltip(fn (ContactMessage $r) => str($r->message)->limit(300))->searchable()
                    ->description(fn (ContactMessage $r) => $r->topicLabel().($r->admin_note ? ' · Note: '.str($r->admin_note)->limit(40) : '')),
                TextColumn::make('attachment_name')->label('File')->icon('heroicon-o-paper-clip')->color('gray')->limit(20)->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Sent')->since()->sortable()->color('gray')
                    ->description(fn (ContactMessage $r) => $r->created_at?->timezone($tz)->format('j M, g:i A')),
                TextColumn::make('resolved_at')->label('Resolved')->since()->sortable()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContactStatus::class)->multiple(),
                SelectFilter::make('topic')->options(config('handi.contact.topics')),
                Filter::make('unread')->label('Unread')->query(fn (Builder $query) => $query->whereNull('read_at')),
                Filter::make('has_file')->label('Has attachment')->query(fn (Builder $query) => $query->whereNotNull('attachment_path')),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                ActionGroup::make([
                    static::resolveAction(),
                    static::statusAction(),
                    static::replyAction(),
                    static::mailAppAction(),
                    static::reopenAction(),
                    static::spamAction(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ...collect(ContactStatus::cases())->map(fn (ContactStatus $status) => BulkAction::make('mark_'.$status->value)
                        ->label('Mark '.mb_strtolower($status->getLabel()))
                        ->icon($status->getIcon())
                        ->color($status->getColor())
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each(fn (ContactMessage $r) => static::move($r, $status)))),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis')
            ->emptyStateHeading('No messages here');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
