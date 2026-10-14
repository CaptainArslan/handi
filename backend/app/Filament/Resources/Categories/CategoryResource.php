<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Support\ArtKeys;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;
use App\Filament\Support\ImagePreview;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'Dish library';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('key')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                ->helperText('Used by the app (e.g. "vegetables"). Avoid changing it after launch.'),
            Select::make('image_key')->label('Default art')->options(ArtKeys::options())
                ->helperText('Shown for dishes in this category that have no photo.'),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('art')->label('')->circular()->imageSize(40)->state(fn ($record) => \App\Support\Media::artUrl($record->image_key))
                    ->action(ImagePreview::action(fn ($record) => \App\Support\Media::artUrl($record->image_key), fn ($record) => $record->name, fn ($record) => \App\Support\Media::artBg($record->image_key))),
                TextColumn::make('name')->label('Category')->weight('semibold')->searchable()->sortable(),
                TextColumn::make('key')->color('gray'),
                TextColumn::make('image_key')->label('Art')->badge()->color('gray'),
                TextColumn::make('dishes_count')->counts('dishes')->label('Dishes')->badge()->color('primary')->alignCenter()->sortable(),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
