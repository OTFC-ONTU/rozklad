<?php

namespace App\Filament\Resources\ScheduleVersions;

use App\Enums\ScheduleVersionStatus;
use App\Filament\Pages\ScheduleGrid;
use App\Filament\Resources\ScheduleVersions\Pages\ManageScheduleVersions;
use App\Models\ScheduleVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ScheduleVersionResource extends Resource
{
    protected static ?string $model = ScheduleVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Розклад';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'версія розкладу';

    protected static ?string $pluralModelLabel = 'Версії розкладу';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Назва')->required()->maxLength(255)
                ->placeholder('Розклад ІІ семестру 2026–2027'),
            DatePicker::make('effective_from')->label('Діє з')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('title')->label('Назва')->searchable(),
                TextColumn::make('effective_from')->label('Діє з')->date('d.m.Y')->sortable(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->color(fn (ScheduleVersionStatus $state) => $state === ScheduleVersionStatus::Published ? 'success' : 'gray'),
                TextColumn::make('lessons_count')->label('Пар')->counts('lessons'),
                TextColumn::make('published_at')->label('Опубліковано')->dateTime('d.m.Y H:i')->placeholder('—'),
            ])
            ->recordUrl(fn (ScheduleVersion $record) => ScheduleGrid::getUrl(['version' => $record->id]))
            ->recordActions([
                Action::make('publish')
                    ->label('Опублікувати')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->color('success')
                    ->visible(fn (ScheduleVersion $record) => $record->status === ScheduleVersionStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Розклад стане видимим для здобувачів і викладачів.')
                    ->action(fn (ScheduleVersion $record) => $record->publish()),
                Action::make('duplicate')
                    ->label('Копія')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->modalHeading('Нова версія на основі цієї')
                    ->schema([
                        TextInput::make('title')->label('Назва')->required()->maxLength(255),
                        DatePicker::make('effective_from')->label('Діє з')->required(),
                    ])
                    ->fillForm(fn (ScheduleVersion $record) => ['title' => $record->title, 'effective_from' => $record->effective_from])
                    ->action(function (ScheduleVersion $record, array $data) {
                        $copy = $record->duplicate($data['title'], $data['effective_from']);
                        Notification::make()->success()->title("Створено чернетку з {$copy->lessons()->count()} пар.")->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (ScheduleVersion $record) => $record->status === ScheduleVersionStatus::Draft),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageScheduleVersions::route('/')];
    }
}
