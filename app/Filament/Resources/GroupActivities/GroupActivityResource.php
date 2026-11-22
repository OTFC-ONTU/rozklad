<?php

namespace App\Filament\Resources\GroupActivities;

use App\Enums\GroupActivityKind;
use App\Filament\Resources\GroupActivities\Pages\ManageGroupActivities;
use App\Models\GroupActivity;
use App\Models\StudentGroup;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class GroupActivityResource extends Resource
{
    protected static ?string $model = GroupActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Календар';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'період графіка';

    protected static ?string $pluralModelLabel = 'Періоди графіка';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        $isPractice = fn (Get $get) => in_array($get('kind'), [GroupActivityKind::Practice, GroupActivityKind::Practice->value], true);

        return $schema->columns(2)->components([
            Select::make('student_group_ids')->label('Групи')->multiple()->required()->searchable()
                ->options(fn () => StudentGroup::orderBy('code')->pluck('code', 'id'))
                ->visibleOn('create')->columnSpanFull(),
            Select::make('student_group_id')->label('Група')->relationship('group', 'code')->required()
                ->hiddenOn('create'),
            Select::make('kind')->label('Вид діяльності')->required()->live()
                ->options(collect(GroupActivityKind::cases())->reject(fn ($k) => $k === GroupActivityKind::Theory)
                    ->mapWithKeys(fn ($k) => [$k->value => $k->label()])),
            Select::make('practice_type_id')->label('Вид практики')->relationship('practiceType', 'name')
                ->visible($isPractice)->required($isPractice),
            DatePicker::make('starts_on')->label('З')->required(),
            DatePicker::make('ends_on')->label('По')->required()->afterOrEqual('starts_on')
                ->rule(fn (Get $get, ?GroupActivity $record) => self::noOverlapRule($get, $record)),
            TextInput::make('note')->label('Примітка')->maxLength(255)->columnSpanFull(),
        ]);
    }

    /** Періоди однієї групи не можуть перетинатися: на дату в групи один стан. */
    private static function noOverlapRule(Get $get, ?GroupActivity $record): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get, $record) {
            $groupIds = $record ? [$record->student_group_id] : ($get('student_group_ids') ?? []);

            if (! $get('starts_on') || ! $value || $groupIds === []) {
                return;
            }

            $codes = GroupActivity::query()
                ->with('group')
                ->whereIn('student_group_id', $groupIds)
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                ->whereDate('starts_on', '<=', $value)
                ->whereDate('ends_on', '>=', $get('starts_on'))
                ->get()
                ->map(fn (GroupActivity $a) => "{$a->group->code} ({$a->label()}, {$a->starts_on->format('d.m')}–{$a->ends_on->format('d.m')})");

            if ($codes->isNotEmpty()) {
                $fail('Перетинається з наявними періодами: '.$codes->implode('; ').'.');
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['group', 'practiceType']))
            ->defaultSort('starts_on')
            ->description('Без запису група в межах семестру на теоретичному навчанні, поза семестрами — на канікулах.')
            ->columns([
                TextColumn::make('group.code')->label('Група')->searchable()->sortable(),
                TextColumn::make('kind')->label('Вид')->badge(),
                TextColumn::make('practiceType.name')->label('Практика')->placeholder('—'),
                TextColumn::make('starts_on')->label('З')->date('d.m.Y')->sortable(),
                TextColumn::make('ends_on')->label('По')->date('d.m.Y'),
                TextColumn::make('note')->label('Примітка')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('student_group_id')->label('Група')->relationship('group', 'code')->multiple(),
                SelectFilter::make('kind')->label('Вид')->options(GroupActivityKind::class),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGroupActivities::route('/')];
    }
}
