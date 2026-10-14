<?php

namespace App\Filament\Resources\Dishes\Pages;

use App\Filament\Resources\Dishes\DishResource;
use App\Services\DishImporter;
use App\Support\Activity;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ListDishes extends ListRecords
{
    protected static string $resource = DishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->importAction(),
                Action::make('template')
                    ->label('Download template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => response()->streamDownload(
                        fn () => print(DishImporter::templateCsv()),
                        'handi-dishes-template.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    )),
            ])->label('Import')->icon('heroicon-m-arrow-up-tray')->color('gray')->button(),
            CreateAction::make()->icon('heroicon-m-plus'),
        ];
    }

    private function importAction(): Action
    {
        return Action::make('import')
            ->label('Import dishes')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Import library dishes')
            ->modalDescription('Upload a CSV or Excel (.xlsx) sheet. Each row becomes a library dish; rows are matched on the dish name.')
            ->modalSubmitActionLabel('Import')
            ->modalWidth('xl')
            ->schema([
                FileUpload::make('file')
                    ->label('Sheet')
                    ->required()
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(5120)
                    ->storeFileNamesIn('file_name')
                    ->helperText('Up to 5 MB and 2,000 rows. First row = column headers.'),
                Toggle::make('update_existing')
                    ->label('Update dishes that already exist')
                    ->helperText('Off: rows whose name is already in the library are skipped.')
                    ->default(true),
                Text::make(new HtmlString(
                    '<div class="handi-import-help"><strong>Columns</strong>: <code>name</code>*, <code>category</code>*, <code>weather</code>*, '
                    .'<code>effort</code>, <code>cost</code>, <code>usual_meal</code>, <code>note</code>, <code>description</code>, <code>image_key</code>, <code>active</code>. '
                    .'Weather can list several: <code>hot, mild</code>. Use “Download template” for an example.</div>'
                )),
            ])
            ->action(function (array $data) {
                $stored = is_array($data['file']) ? reset($data['file']) : $data['file'];
                $path = $stored instanceof TemporaryUploadedFile ? $stored->getRealPath() : Storage::disk('local')->path((string) $stored);
                $original = $data['file_name'] ?? (is_string($stored) ? $stored : $stored->getClientOriginalName());
                if (is_array($original)) {
                    $original = reset($original);
                }

                try {
                    $r = app(DishImporter::class)->import($path, (bool) ($data['update_existing'] ?? true), (string) $original);
                } catch (RuntimeException $e) {
                    Notification::make()->title('Import failed')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                } finally {
                    if (is_string($stored)) {
                        Storage::disk('local')->delete($stored);
                    }
                }

                Activity::log(auth()->user(), 'admin.dishes_imported', "Imported dishes: {$r['created']} new, {$r['updated']} updated, ".count($r['errors']).' with errors', null, [
                    'file' => $original, 'created' => $r['created'], 'updated' => $r['updated'], 'skipped' => $r['skipped'], 'errors' => count($r['errors']),
                ]);

                $summary = "{$r['created']} added · {$r['updated']} updated · {$r['skipped']} skipped";
                if ($r['errors'] === []) {
                    Notification::make()->title('Dishes imported')->body($summary)->success()->send();

                    return;
                }

                $list = collect($r['errors'])->take(8)->map(fn ($msg, $line) => "Row {$line}: ".e($msg))->join('<br>');
                $more = count($r['errors']) > 8 ? '<br>…and '.(count($r['errors']) - 8).' more' : '';
                Notification::make()
                    ->title(count($r['errors']).' '.str('row')->plural(count($r['errors'])).' couldn’t be imported')
                    ->body(new HtmlString(e($summary).'<br><br>'.$list.$more))
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }
}
