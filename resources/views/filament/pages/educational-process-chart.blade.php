@php
    use App\Filament\Resources\AcademicYears\AcademicYearResource;

    $weeks = $this->weeks;
    $groups = $this->groups;
    $cells = $this->cells;
@endphp

<x-filament-panels::page>
    <style>
        .ep-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
        .ep-toolbar label { display: block; font-size: .75rem; opacity: .7; margin-bottom: .25rem; }
        .ep-scroll { overflow: auto; max-height: calc(100vh - 16rem); border: 1px solid rgb(0 0 0 / .1); border-radius: .75rem; background: #fff; }
        .dark .ep-scroll { border-color: rgb(255 255 255 / .1); background: rgb(24 24 27); }
        .ep-table { border-collapse: separate; border-spacing: 0; font-size: .7rem; }
        .ep-table th, .ep-table td { border-right: 1px solid rgb(0 0 0 / .08); border-bottom: 1px solid rgb(0 0 0 / .08); text-align: center; }
        .dark .ep-table th, .dark .ep-table td { border-color: rgb(255 255 255 / .08); }
        .ep-table thead th { position: sticky; top: 0; z-index: 2; background: rgb(244 244 245); padding: .2rem; font-weight: 500; line-height: 1.1; }
        .dark .ep-table thead th { background: rgb(39 39 42); }
        .ep-group { position: sticky; left: 0; z-index: 1; background: rgb(250 250 250); padding: .25rem .5rem; font-weight: 600; text-align: left !important; white-space: nowrap; }
        .dark .ep-group { background: rgb(33 33 36); }
        .ep-table thead th.ep-group { z-index: 3; }
        .ep-cell { width: 1.9rem; min-width: 1.9rem; height: 1.6rem; color: #111; font-weight: 600; }
        .ep-week-off { opacity: .55; }
        .ep-legend { display: flex; flex-wrap: wrap; gap: .75rem; font-size: .75rem; }
        .ep-swatch { display: inline-block; width: .9rem; height: .9rem; border-radius: .2rem; vertical-align: middle; margin-right: .25rem; border: 1px solid rgb(0 0 0 / .15); }
    </style>

    <div class="ep-toolbar">
        <div style="min-width: 12rem">
            <label for="ep-year">Навчальний рік</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="ep-year" wire:model.live="year">
                    @foreach ($this->yearOptions as $id => $title)
                        <option value="{{ $id }}">{{ $title }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div style="min-width: 8rem">
            <label for="ep-course">Курс</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="ep-course" wire:model.live="course">
                    <option value="">Усі</option>
                    @foreach ([1, 2, 3, 4] as $c)
                        <option value="{{ $c }}">{{ $c }} курс</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div class="ep-legend">
            <span><span class="ep-swatch" style="background: #fff"></span>Теоретичне навчання</span>
            @foreach ($this->legend() as $item)
                <span><span class="ep-swatch" style="background: {{ $item['color'] }}"></span>{{ $item['short'] ? $item['short'].' — ' : '' }}{{ $item['label'] }}</span>
            @endforeach
        </div>
    </div>

    @if (! $this->academicYear)
        <x-filament::empty-state heading="Немає навчальних років" icon="heroicon-o-calendar">
            <x-slot name="footer">
                <x-filament::button tag="a" :href="AcademicYearResource::getUrl()">Додати навчальний рік</x-filament::button>
            </x-slot>
        </x-filament::empty-state>
    @else
        <div class="ep-scroll">
            <table class="ep-table">
                <thead>
                    <tr>
                        <th class="ep-group">Група</th>
                        @foreach ($weeks as $week)
                            <th @class(['ep-week-off' => ! $week['isStudy']]) title="{{ $week['start']->format('d.m.Y') }}–{{ $week['start']->addDays(4)->format('d.m.Y') }}">
                                {{ $week['start']->format('d') }}<br>{{ $week['start']->format('m') }}<br>{{ $week['parity'] ?? '' }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr wire:key="ep-{{ $group->id }}">
                            <th class="ep-group">{{ $group->code }}</th>
                            @foreach ($weeks as $week)
                                @php $cell = $cells[$group->id][$week['start']->toDateString()] ?? null; @endphp
                                <td class="ep-cell" @if ($cell) style="background: {{ $cell['color'] }}" title="{{ $cell['title'] }}" @endif>
                                    {{ $cell['short'] ?? '' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="font-size: .75rem; opacity: .7">
            Тиждень — понеділок; Ч/З — чисельник/знаменник. У клітинці — діяльність, що займає більшість днів тижня; наведіть, щоб побачити всі періоди.
        </p>
    @endif
</x-filament-panels::page>
