<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Models\City;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Dish library';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(80),
            Select::make('province_id')->relationship('province', 'name')->required()->preload(),
            TextInput::make('lat')->label('Latitude')->numeric()->required()->helperText('Used for the weather.'),
            TextInput::make('lng')->label('Longitude')->numeric()->required(),
            Toggle::make('is_popular')->label('Show in “Popular”'),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('City')->weight('semibold')->icon('heroicon-m-map-pin')->iconColor('gray')->searchable()->sortable(),
                TextColumn::make('province.name')->label('Province')->badge()->color('gray')->sortable(),
                TextColumn::make('popular')->label('Popular')->badge()->state(fn ($record) => $record->is_popular ? 'Popular' : null)->color('warning')->icon('heroicon-m-star'),
                TextColumn::make('kitchens_count')->counts('kitchens')->label('Kitchens')->alignCenter()->sortable(),
                TextColumn::make('lat')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lng')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('province')->relationship('province', 'name')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
