@php
    use App\Enums\WeekParity;
    use App\Filament\Resources\ScheduleVersions\ScheduleVersionResource;
    use App\Filament\Resources\StudentGroups\StudentGroupResource;
    use App\Models\SlotSetting;

    $version = $this->currentVersion;
    $groups = $this->groups;
    $cells = $this->cells;
    $weekdays = $this->weekdays;
    $pairs = range(1, SlotSetting::MAX_PAIRS);
@endphp

<x-filament-panels::page>
    <style>
        .sg-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
        .sg-toolbar label { display: block; font-size: .75rem; opacity: .7; margin-bottom: .25rem; }
        .sg-scroll { overflow: auto; max-height: calc(100vh - 14rem); border: 1px solid rgb(0 0 0 / .1); border-radius: .75rem; background: #fff; }
        .dark .sg-scroll { border-color: rgb(255 255 255 / .1); background: rgb(24 24 27); }
        .sg-table { border-collapse: separate; border-spacing: 0; font-size: .75rem; line-height: 1.2; }
        .sg-table th, .sg-table td { border-right: 1px solid rgb(0 0 0 / .08); border-bottom: 1px solid rgb(0 0 0 / .08); padding: 0; vertical-align: top; }
        .dark .sg-table th, .dark .sg-table td { border-color: rgb(255 255 255 / .08); }
        .sg-table thead th { position: sticky; top: 0; z-index: 2; background: rgb(244 244 245); padding: .5rem; font-weight: 600; font-size: .8rem; }
        .dark .sg-table thead th { background: rgb(39 39 42); }
        .sg-day, .sg-pair { position: sticky; z-index: 1; background: rgb(250 250 250); text-align: center; vertical-align: middle !important; }
        .dark .sg-day, .dark .sg-pair { background: rgb(33 33 36); }
        .sg-day { left: 0; width: 2.25rem; writing-mode: vertical-rl; transform: rotate(180deg); font-weight: 600; padding: .25rem !important; }
        .sg-pair { left: 2.25rem; width: 1.75rem; font-weight: 600; }
        .sg-table thead th.sg-corner { left: 0; z-index: 3; }
        .sg-daystart td, .sg-daystart th { border-top: 2px solid rgb(0 0 0 / .25); }
        .dark .sg-daystart td, .dark .sg-daystart th { border-top-color: rgb(255 255 255 / .25); }
        .sg-cell { width: 11rem; min-width: 11rem; height: 4.5rem; position: relative; }
        .sg-cell-inner { display: flex; flex-direction: column; height: 100%; min-height: 4.5rem; }
        .sg-row { display: flex; flex: 1; }
        .sg-half { flex: 1; display: flex; border-top: 1px dashed rgb(0 0 0 / .2); }
        .dark .sg-half { border-top-color: rgb(255 255 255 / .2); }
        .sg-half:first-child { border-top: 0; }
        .sg-lesson { flex: 1; min-width: 0; text-align: left; padding: .25rem .35rem; cursor: pointer; border-left: 3px solid transparent; }
        .sg-lesson + .sg-lesson { border-left-color: rgb(0 0 0 / .1); }
        .sg-lesson:hover { background: rgb(245 158 11 / .12); }
        .sg-lesson.sg-fixed { background: rgb(0 0 0 / .04); font-style: italic; }
        .dark .sg-lesson.sg-fixed { background: rgb(255 255 255 / .05); }
        .sg-lesson.sg-override { border-left-color: rgb(220 38 38); }
        .sg-title { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sg-meta { opacity: .75; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sg-tag { display: inline-block; font-size: .65rem; padding: 0 .25rem; border-radius: .25rem; background: rgb(245 158 11 / .2); margin-right: .2rem; }
        .sg-add { flex: 1; min-height: 1.5rem; color: transparent; font-size: 1rem; cursor: pointer; }
        .sg-add:hover { color: rgb(245 158 11); background: rgb(245 158 11 / .08); }
        .sg-add-mini { position: absolute; right: .15rem; bottom: .1rem; width: 1rem; height: 1rem; line-height: 1rem; font-size: .8rem; color: rgb(0 0 0 / .3); border-radius: .25rem; }
        .dark .sg-add-mini { color: rgb(255 255 255 / .3); }
        .sg-add-mini:hover { color: rgb(245 158 11); background: rgb(245 158 11 / .15); }
        .sg-legend { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .75rem; opacity: .75; }
    </style>

    <div class="sg-toolbar">
        <div style="min-width: 22rem">
            <label for="sg-version">Версія розкладу</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="sg-version" wire:model.live="version">
                    @foreach ($this->versionOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div style="min-width: 8rem">
            <label for="sg-course">Курс</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="sg-course" wire:model.live="course">
                    <option value="">Усі</option>
                    @foreach ([1, 2, 3, 4] as $c)
                        <option value="{{ $c }}">{{ $c }} курс</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <x-filament::badge color="info" icon="heroicon-o-calendar" style="align-self: center">Сьогодні: {{ $this->today }}</x-filament::badge>
        <div class="sg-legend">
            <span>Пунктир — чисельник / знаменник</span>
            <span><span style="border-left: 3px solid rgb(220 38 38); padding-left: .25rem">збережено попри конфлікт</span></span>
            <span>⚑ закріплено</span>
        </div>
    </div>

    @if (! $version)
        <x-filament::empty-state heading="Ще немає жодної версії розкладу" icon="heroicon-o-document-duplicate">
            <x-slot name="footer">
                <x-filament::button tag="a" :href="ScheduleVersionResource::getUrl()">Створити версію</x-filament::button>
            </x-slot>
        </x-filament::empty-state>
    @elseif ($groups->isEmpty())
        <x-filament::empty-state heading="Немає груп" description="Додайте групи в довіднику." icon="heroicon-o-user-group">
            <x-slot name="footer">
                <x-filament::button tag="a" :href="StudentGroupResource::getUrl()">До груп</x-filament::button>
            </x-slot>
        </x-filament::empty-state>
    @else
        @unless ($version->isDraft())
            <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle"
                heading="Версія опублікована — зміни одразу видно всім. Для нового «діє з» зробіть копію у «Версіях розкладу»." />
        @endunless

        <div class="sg-scroll">
            <table class="sg-table">
                <thead>
                    <tr>
                        <th class="sg-corner sg-day" style="writing-mode: horizontal-tb; transform: none" colspan="2"></th>
                        @foreach ($groups as $group)
                            <th>{{ $group->code }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weekdays as $day)
                        @foreach ($pairs as $pair)
                            <tr @class(['sg-daystart' => $pair === 1])>
                                @if ($pair === 1)
                                    <th class="sg-day" rowspan="{{ count($pairs) }}">{{ $day->label() }}</th>
                                @endif
                                <th class="sg-pair">{{ $pair }}</th>

                                @foreach ($groups as $group)
                                    @php
                                        $lessons = collect($cells[$group->id][$day->value][$pair] ?? []);
                                        $every = $lessons->filter(fn ($l) => $l->week === WeekParity::Every);
                                        $num = $lessons->filter(fn ($l) => $l->week === WeekParity::Numerator);
                                        $den = $lessons->filter(fn ($l) => $l->week === WeekParity::Denominator);
                                        $split = $num->isNotEmpty() || $den->isNotEmpty();
                                        $args = ['group' => $group->id, 'weekday' => $day->value, 'pair' => $pair];
                                    @endphp
                                    <td class="sg-cell" wire:key="c-{{ $group->id }}-{{ $day->value }}-{{ $pair }}">
                                        <div class="sg-cell-inner">
                                            @if ($lessons->isEmpty())
                                                <button type="button" class="sg-add" title="Додати пару"
                                                    wire:click="mountAction('createLesson', @js($args))">+</button>
                                            @else
                                                @if ($every->isNotEmpty())
                                                    <div class="sg-row">
                                                        @foreach ($every as $lesson)
                                                            @include('filament.pages.partials.lesson-card', ['lesson' => $lesson, 'group' => $group])
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if ($split)
                                                    @foreach ([[WeekParity::Numerator, $num], [WeekParity::Denominator, $den]] as [$parity, $halfLessons])
                                                        <div class="sg-half" title="{{ $parity->label() }}">
                                                            @forelse ($halfLessons as $lesson)
                                                                @include('filament.pages.partials.lesson-card', ['lesson' => $lesson, 'group' => $group])
                                                            @empty
                                                                <button type="button" class="sg-add" title="Додати пару ({{ mb_strtolower($parity->label()) }})"
                                                                    wire:click="mountAction('createLesson', @js([...$args, 'week' => $parity->value]))">+</button>
                                                            @endforelse
                                                        </div>
                                                    @endforeach
                                                @endif
                                                <button type="button" class="sg-add-mini" title="Додати ще одну пару (підгрупа, інший тиждень)"
                                                    wire:click="mountAction('createLesson', @js($args))">+</button>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
