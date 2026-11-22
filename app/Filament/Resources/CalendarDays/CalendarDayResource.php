<?php

namespace App\Filament\Resources\CalendarDays;

use App\Enums\CalendarDayKind;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Filament\Resources\CalendarDays\Pages\ManageCalendarDays;
use App\Models\CalendarDay;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CalendarDayResource extends Resource
{
    protected static ?string $model = CalendarDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'Календар';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'особливий день';

    protected static ?string $pluralModelLabel = 'Особливі дні';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        $isWorking = fn (Get $get) => in_array($get('kind'), [CalendarDayKind::Working, CalendarDayKind::Working->value], true);

        return $schema->components([
            DatePicker::make('date')->label('Дата')->required()->unique(ignoreRecord: true),
            Select::make('kind')->label('Тип')->options(CalendarDayKind::class)->required()->live(),
            Select::make('follows_weekday')->label('За розкладом дня')->options(Weekday::class)
                ->placeholder('Свого дня тижня')->visible($isWorking),
            Select::make('follows_parity')->label('Тиждень')->placeholder('За календарем')->visible($isWorking)
                ->options([WeekParity::Numerator->value => 'Чисельник', WeekParity::Denominator->value => 'Знаменник']),
            TextInput::make('note')->label('Примітка')->maxLength(255)->placeholder('День Незалежності'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->description('Свята та неробочі дні; робочі дні «за розкладом іншого дня» (наприклад, субота за вівторком).')
            ->columns([
                TextColumn::make('date')->label('Дата')->date('D, d.m.Y')->sortable(),
                TextColumn::make('kind')->label('Тип')->badge()
                    ->color(fn (CalendarDayKind $state) => $state === CalendarDayKind::Holiday ? 'success' : 'warning'),
                TextColumn::make('follows_weekday')->label('За розкладом')->placeholder('—'),
                TextColumn::make('follows_parity')->label('Тиждень')->placeholder('—'),
                TextColumn::make('note')->label('Примітка')->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCalendarDays::route('/')];
    }
}
