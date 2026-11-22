<?php

namespace App\Filament\Resources\PracticeTypes;

use App\Filament\Resources\PracticeTypes\Pages\ManagePracticeTypes;
use App\Models\PracticeType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PracticeTypeResource extends Resource
{
    protected static ?string $model = PracticeType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'вид практики';

    protected static ?string $pluralModelLabel = 'Види практик';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Назва')->required()->maxLength(255)->placeholder('Навчальна ознайомча за фахом'),
            TextInput::make('short_name')->label('Позначка в графіку')->required()->maxLength(16)->placeholder('НОП'),
            ColorPicker::make('color')->label('Колір у графіку')->required()->default('#60a5fa'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('Назва')->searchable(),
                TextColumn::make('short_name')->label('Позначка'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePracticeTypes::route('/')];
    }
}
