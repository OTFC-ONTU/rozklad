@php
    use App\Enums\DayLessonStatus;
    use App\Enums\LessonFormat;
    use App\Enums\Shift;

    $plan = $this->plan;
    $day = $plan->day;
    $teachers = $this->teachers;
    $cells = $this->cells;
    $pairs = $this->pairs();
    $idle = $this->idleGroups;
@endphp

<x-filament-panels::page>
    <style>
        .dc-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
        .dc-toolbar label { display: block; font-size: .75rem; opacity: .7; margin-bottom: .25rem; }
        .dc-table { width: 100%; border-collapse: collapse; font-size: .8rem; line-height: 1.25; background: #fff; border-radius: .75rem; overflow: hidden; }
        .dark .dc-table { background: rgb(24 24 27); }
        .dc-table th, .dc-table td { border: 1px solid rgb(0 0 0 / .08); padding: 0; vertical-align: top; }
        .dark .dc-table th, .dark .dc-table td { border-color: rgb(255 255 255 / .08); }
        .dc-table thead th { background: rgb(244 244 245); padding: .5rem; font-weight: 600; }
        .dark .dc-table thead th { background: rgb(39 39 42); }
        .dc-teacher { padding: .5rem !important; font-weight: 600; white-space: nowrap; width: 12rem; }
        .dc-cell { min-width: 10rem; height: 3.5rem; }
        .dc-cell-inner { display: flex; flex-direction: column; height: 100%; min-height: 3.5rem; }
        .dc-lesson { position: relative; display: block; width: 100%; text-align: left; padding: .3rem 1.6rem .3rem .45rem; cursor: pointer; border-left: 3px solid transparent; }
        .dc-lesson + .dc-lesson { border-top: 1px dashed rgb(0 0 0 / .15); }
        .dc-lesson:hover { background: rgb(245 158 11 / .1); }
        .dc-lesson.is-changed, .dc-lesson.is-added { background: rgb(245 158 11 / .15); border-left-color: rgb(245 158 11); }
        .dc-lesson.is-cancelled { background: rgb(220 38 38 / .07); border-left-color: rgb(220 38 38); }
        .dc-lesson.is-cancelled .dc-main { text-decoration: line-through; opacity: .6; }
        .dc-main { font-weight: 600; }
        .dc-meta { opacity: .7; font-size: .72rem; }
        .dc-tag { display: inline-block; font-size: .65rem; padding: 0 .3rem; border-radius: .25rem; margin-top: .1rem; }
        .dc-tag.warn { background: rgb(245 158 11 / .25); }
        .dc-tag.danger { background: rgb(220 38 38 / .15); color: rgb(185 28 28); }
        .dark .dc-tag.danger { color: rgb(252 165 165); }
        .dc-x { position: absolute; top: .2rem; right: .2rem; width: 1.2rem; height: 1.2rem; line-height: 1.1rem; text-align: center; border-radius: .25rem; color: rgb(0 0 0 / .35); font-size: .9rem; }
        .dark .dc-x { color: rgb(255 255 255 / .35); }
        .dc-x:hover { color: rgb(220 38 38); background: rgb(220 38 38 / .12); }
        .dc-add { flex: 1; min-height: 1.5rem; color: transparent; font-size: 1rem; cursor: pointer; }
        .dc-add:hover { color: rgb(245 158 11); background: rgb(245 158 11 / .08); }
        .dc-legend { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .75rem; opacity: .75; }
        .dc-idle { display: grid; gap: .5rem; }
        .dc-idle-row { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; font-size: .85rem; }
    </style>

    <div class="dc-toolbar">
        <div>
            <label for="dc-date">Дата</label>
            <div style="display: flex; gap: .25rem; align-items: center">
                <x-filament::icon-button icon="heroicon-o-chevron-left" label="Попередній день" wire:click="changeDate(-1)" />
                <x-filament::input.wrapper>
                    <x-filament::input id="dc-date" type="date" wire:model.live="date" />
                </x-filament::input.wrapper>
                <x-filament::icon-button icon="heroicon-o-chevron-right" label="Наступний день" wire:click="changeDate(1)" />
            </div>
        </div>
        <div style="min-width: 8rem">
            <label for="dc-shift">Зміна</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="dc-shift" wire:model.live="shift">
                    @foreach (Shift::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <x-filament::badge :color="$day->isStudyDay ? 'info' : 'gray'" icon="heroicon-o-calendar" style="align-self: center">
            {{ $day->describe() }}
        </x-filament::badge>
        <div class="dc-legend">
            <span><span class="dc-tag danger">скасовано</span> — закреслено</span>
            <span><span class="dc-tag warn">заміна</span> — змінено на цей день</span>
            <span>✕ — скасувати пару</span>
        </div>
    </div>

    @if ($plan->staleChanges->isNotEmpty())
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle"
            heading="{{ $plan->staleChanges->count() }} замін(и) на цей день стосуються пар, яких уже немає в чинній версії розкладу. Перевірте день і за потреби внесіть заміни заново." />
    @endif

    @if (! $day->isStudyDay)
        <x-filament::empty-state heading="Цього дня занять немає" :description="$day->note" icon="heroicon-o-sun" />
    @elseif ($plan->version === null)
        <x-filament::empty-state heading="На цю дату немає опублікованої версії розкладу" icon="heroicon-o-document-duplicate" />
    @elseif ($teachers->isEmpty())
        <x-filament::empty-state heading="У цій зміні пар немає" icon="heroicon-o-user-group" />
    @else
        <div style="overflow-x: auto">
            <table class="dc-table">
                <thead>
                    <tr>
                        <th style="text-align: left">Викладач</th>
                        @foreach ($pairs as $pair)
                            <th>{{ $pair }} пара</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teachers as $teacher)
                        <tr wire:key="t-{{ $teacher->id }}">
                            <td class="dc-teacher">{{ $teacher->short_name }}</td>
                            @foreach ($pairs as $pair)
                                <td class="dc-cell">
                                    <div class="dc-cell-inner">
                                        @foreach ($cells[$teacher->id][$pair] ?? [] as $item)
                                            @php
                                                $l = $item->lesson;
                                                $parts = $item->changedParts();
                                            @endphp
                                            <div @class([
                                                    'dc-lesson',
                                                    'is-changed' => $item->status === DayLessonStatus::Changed,
                                                    'is-added' => $item->status === DayLessonStatus::Added,
                                                    'is-cancelled' => $item->isCancelled(),
                                                ])
                                                wire:key="l-{{ $teacher->id }}-{{ $item->key() }}"
                                                role="button" tabindex="0"
                                                title="{{ $item->reason() }}"
                                                wire:click="mountAction('editLesson', @js(['key' => $item->key()]))">
                                                <div class="dc-main">{{ $l->participants->map->label()->implode(', ') }}</div>
                                                <div class="dc-meta">
                                                    @if ($l->format === LessonFormat::Remote) дистанційно · @endif
                                                    {{ $l->displayTitle() }}@if ($l->rooms->isNotEmpty()), {{ $l->rooms->map->label()->implode(', ') }}@endif
                                                </div>
                                                @if ($item->isCancelled())
                                                    <span class="dc-tag danger">скасовано{{ $item->reason() ? ': '.$item->reason() : '' }}</span>
                                                @elseif ($item->status === DayLessonStatus::Added)
                                                    <span class="dc-tag warn">додаткова пара</span>
                                                @elseif ($parts)
                                                    <span class="dc-tag warn">заміна: {{ collect($parts)->map(fn ($p) => match ($p) {
                                                        'pair' => 'перенесено з '.$item->original->pair.' пари',
                                                        'discipline' => 'дисципліна',
                                                        'teachers' => 'викладач (був '.$item->original->teachers->pluck('short_name')->implode(', ').')',
                                                        'rooms' => 'аудиторія',
                                                        'groups' => 'групи',
                                                        'format' => 'формат',
                                                    })->implode(', ') }}</span>
                                                @endif
                                                @unless ($item->isCancelled())
                                                    <button type="button" class="dc-x" title="Скасувати пару"
                                                        wire:click.stop="mountAction('cancelLesson', @js(['key' => $item->key()]))">✕</button>
                                                @else
                                                    @if ($item->original)
                                                        <button type="button" class="dc-x" title="Повернути як за розкладом"
                                                            wire:click.stop="mountAction('restoreLesson', @js(['key' => $item->key()]))">↺</button>
                                                    @endif
                                                @endunless
                                            </div>
                                        @endforeach
                                        <button type="button" class="dc-add" title="Дати {{ $teacher->short_name }} пару"
                                            wire:click="mountAction('addLesson', @js(['teacher' => $teacher->id, 'pair' => $pair]))">+</button>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($idle->isNotEmpty())
            <x-filament::section heading="Групи без пари" description="Пару скасовано, натомість нічого не поставлено. Клік по викладачу — дати йому цю пару." icon="heroicon-o-light-bulb">
                <div class="dc-idle">
                    @foreach ($idle as $i)
                        <div class="dc-idle-row" wire:key="idle-{{ $i['group']->id }}-{{ $i['pair'] }}">
                            <strong>{{ $i['group']->code }}, {{ $i['pair'] }} пара</strong>
                            <span style="opacity: .6">(було: {{ $i['cancelled']->lesson->displayTitle() }})</span>
                            <span>→</span>
                            @forelse ($i['candidates'] as $c)
                                @foreach ($c['disciplines'] as $discipline)
                                    <x-filament::button size="xs" color="gray"
                                        wire:click="mountAction('addLesson', @js(['teacher' => $c['teacher']->id, 'group' => $i['group']->id, 'pair' => $i['pair'], 'discipline' => $discipline->id]))">
                                        {{ $c['teacher']->short_name }} · {{ $discipline->short_name }}
                                    </x-filament::button>
                                @endforeach
                            @empty
                                <span style="opacity: .6">вільних викладачів групи немає</span>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
