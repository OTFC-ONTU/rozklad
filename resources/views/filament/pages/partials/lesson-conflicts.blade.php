@php /** @var \App\Schedule\ConflictReport|null $report */ @endphp
@if ($report === null)
    <p style="font-size: .875rem; opacity: .6">Виберіть слот, групу і дисципліну — перевірка конфліктів з'явиться тут.</p>
@elseif ($report->isEmpty())
    <x-filament::callout color="success" icon="heroicon-o-check-circle" heading="Конфліктів немає" />
@else
    @if ($report->hasHard())
        <x-filament::callout color="danger" icon="heroicon-o-x-circle" heading="Конфлікти">
            <x-slot name="description">
                <ul style="list-style: disc; padding-left: 1.25rem">
                    @foreach ($report->hardConflicts() as $conflict)
                        <li>{{ $conflict->message }}</li>
                    @endforeach
                </ul>
            </x-slot>
        </x-filament::callout>
    @endif
    @if ($report->warnings()->isNotEmpty())
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" heading="Попередження">
            <x-slot name="description">
                <ul style="list-style: disc; padding-left: 1.25rem">
                    @foreach ($report->warnings() as $conflict)
                        <li>{{ $conflict->message }}</li>
                    @endforeach
                </ul>
            </x-slot>
        </x-filament::callout>
    @endif
@endif
