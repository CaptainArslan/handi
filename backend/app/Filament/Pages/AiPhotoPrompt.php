<?php

namespace App\Filament\Pages;

use App\Support\Activity;
use App\Support\ImagePrompt;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Dish library → AI photo prompt.
 *
 * The JSON spec the app offers under "Add a photo" so users can make a dish
 * image in Gemini that matches every other dish in the app.
 */
class AiPhotoPrompt extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Dish library';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'AI photo prompt';

    protected static ?string $title = 'AI photo prompt';

    protected static ?string $slug = 'ai-photo-prompt';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'What the app copies when a user taps "Copy AI prompt" under Add a photo. Changes reach the app on its next fetch.';
    }

    public function mount(): void
    {
        $this->form->fill(ImagePrompt::get());
    }

    public function form(Schema $schema): Schema
    {
        $placeholder = ImagePrompt::PLACEHOLDER;

        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])->schema([
                    Section::make('Prompt')
                        ->description("Use {$placeholder} wherever the dish name should go — the app fills it in before copying.")
                        ->columnSpan(['xl' => 2])
                        ->schema([
                            Toggle::make('enabled')
                                ->label('Show the AI photo helper in the app')
                                ->default(true),
                            Textarea::make('intro')
                                ->label('Message before the JSON')
                                ->rows(3)
                                ->required()
                                ->maxLength(1000)
                                ->live(onBlur: true),
                            CodeEditor::make('template')
                                ->label('JSON illustration spec')
                                ->language(Language::Json)
                                ->required()
                                ->live(onBlur: true)
                                ->rule(static fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if ($error = ImagePrompt::templateError(is_string($value) ? $value : null)) {
                                        $fail($error);
                                    }
                                })
                                ->helperText('Must be valid JSON. It is re-indented on save.'),
                        ]),
                    Section::make('AI app')
                        ->description('Where "Open Gemini" takes the user.')
                        ->columnSpan(1)
                        ->schema([
                            TextInput::make('ai_name')
                                ->label('Button name')
                                ->required()
                                ->maxLength(30)
                                ->helperText('Shown as "Open …" in the app.'),
                            TextInput::make('ai_url')
                                ->label('Link')
                                ->required()
                                ->url()
                                ->maxLength(255)
                                ->helperText('On phones with the Gemini app, this link opens the app.'),
                            Html::make(fn (Get $get): HtmlString => $this->preview($get))
                                ->columnSpanFull(),
                        ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save prompt')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openAi')
                ->label('Try it in Gemini')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => ImagePrompt::get()['ai_url'], shouldOpenInNewTab: true),
            Action::make('reset')
                ->label('Reset to default')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->modalIconColor('danger')
                ->modalSubmitActionLabel('Reset')
                ->requiresConfirmation()
                ->modalDescription('This replaces the message, JSON spec and link with the built-in defaults.')
                ->action(function (): void {
                    ImagePrompt::reset();
                    $this->form->fill(ImagePrompt::get());
                    Activity::log(auth()->user(), 'admin.image_prompt_reset', 'AI photo prompt reset by '.auth()->user()?->email);
                    Notification::make()->success()->title('Prompt reset to default')->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        ImagePrompt::save($data);
        $this->form->fill(ImagePrompt::get());

        Activity::log(auth()->user(), 'admin.image_prompt_updated', 'AI photo prompt updated by '.auth()->user()?->email);
        Notification::make()->success()->title('Prompt saved')->body('The app picks it up on its next fetch.')->send();
    }

    private function preview(Get $get): HtmlString
    {
        $settings = [
            'intro' => (string) ($get('intro') ?? ''),
            'template' => (string) ($get('template') ?? ''),
        ];
        $text = ImagePrompt::render('Nihari', $settings);

        return new HtmlString(
            '<div class="handi-prompt-preview"><p class="handi-prompt-preview-title">Preview · copied for "Nihari"</p><pre>'
            .e(mb_strimwidth($text, 0, 1200, '…'))
            .'</pre></div>'
        );
    }
}
