<?php

namespace App\Filament\Resources\Dishes;

use App\Enums\Cost;
use App\Enums\Effort;
use App\Enums\UsualMeal;
use App\Enums\Weather;
use App\Filament\Resources\Dishes\Pages\CreateDish;
use App\Filament\Resources\Dishes\Pages\EditDish;
use App\Filament\Resources\Dishes\Pages\ListDishes;
use App\Filament\Support\ArtKeys;
use App\Models\Dish;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;
use App\Filament\Support\ImagePreview;

/** The global dish library that users pick their kitchen from. */
class DishResource extends Resource
{
    protected static ?string $model = Dish::class;

    protected static ?string $pluralModelLabel = 'Library dishes';

    protected static ?string $modelLabel = 'Library dish';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cake';

    protected static string|UnitEnum|null $navigationGroup = 'Dish library';

    protected static ?string $navigationLabel = 'Library dishes';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Dish')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->required()->maxLength(150)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('slug')->required()->maxLength(170)->alphaDash()->unique(ignoreRecord: true),
                Select::make('category_id')->relationship('category', 'name')->required()->searchable()->preload(),
                TextInput::make('note')->maxLength(80)->placeholder('Summer favourite')
                    ->helperText('Short tagline shown on the dish (optional).'),
                Textarea::make('description')->rows(2)->maxLength(1000)->columnSpanFull(),
            ]),
            Section::make('Picture')->columns(2)->columnSpanFull()->schema([
                SpatieMediaLibraryFileUpload::make('photo')->label('Photo')->collection('image')->image()->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)
                    ->imagePreviewHeight('220')->panelAspectRatio('4:3')->extraAttributes(['class' => 'handi-dropzone'])
                    // Through the model: the original is capped at 1600 px and thumb/card sizes are made.
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, ?Dish $record): ?string {
                        if (! $record) {
                            return null;
                        }
                        $tmp = tempnam(sys_get_temp_dir(), 'up');
                        file_put_contents($tmp, $file->get());
                        try {
                            return $record->attachPhoto($tmp)->getAttributeValue('uuid');
                        } finally {
                            @unlink($tmp);
                        }
                    })
                    ->helperText('Drop a photo (JPG, PNG or WebP, up to 10 MB). It is resized and thumbnails are made automatically. Without a photo the bundled art below is used.'),
                Select::make('image_key')->label('Or bundled art')->options(ArtKeys::options())
                    ->helperText('Used when there is no photo. Empty = the category default.'),
            ]),
            Section::make('How it is picked')->columns(2)->columnSpanFull()->schema([
                CheckboxList::make('default_weather')->label('Good for weather')->options(Weather::class)->columns(5)->required()
                    ->helperText('“Any” means every day.')->columnSpanFull(),
                Select::make('default_effort')->label('Effort')->options(Effort::class)->required()->default('medium'),
                Select::make('default_cost')->label('Cost')->options(Cost::class)->required()->default('low'),
                Select::make('usual_meal')->label('Usually cooked for')->options(UsualMeal::class)->required()->default('both'),
                Toggle::make('is_active')->label('Active')->default(true)
                    ->helperText('Inactive dishes disappear from the library for new kitchens.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('image')->label('')->circular()->imageSize(44)
                    ->state(fn (Dish $r) => $r->imageUrl('thumb'))
                    ->action(ImagePreview::action(
                        fn (Dish $r) => $r->imageUrl('card'),
                        fn (Dish $r) => $r->name,
                        fn (Dish $r) => \App\Support\Media::artBg($r->image_key ?? $r->category?->image_key),
                    )),
                TextColumn::make('name')->label('Dish')->weight('semibold')->searchable()->sortable()->description(fn (Dish $r) => $r->note),
                TextColumn::make('category.name')->label('Category')->badge()->color('gray')->sortable(),
                TextColumn::make('default_weather')->label('Weather')->badge()->formatStateUsing(fn ($state) => ucfirst($state instanceof \BackedEnum ? $state->value : (string) $state))->color(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : $state) { 'hot' => 'warning', 'cold', 'rainy' => 'info', 'mild' => 'success', default => 'gray' }),
                TextColumn::make('default_effort')->label('Effort')->badge(),
                TextColumn::make('default_cost')->label('Cost')->badge(),
                TextColumn::make('kitchen_dishes_count')->counts('kitchenDishes')->label('In kitchens')->sortable()->alignCenter(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name'),
                SelectFilter::make('default_effort')->label('Effort')->options(Effort::class),
                SelectFilter::make('default_cost')->label('Cost')->options(Cost::class),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->striped()
            ->recordActions([EditAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDishes::route('/'),
            'create' => CreateDish::route('/create'),
            'edit' => EditDish::route('/{record}/edit'),
        ];
    }
}
