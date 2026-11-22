@if ($weeks !== [])
    <div>
        <div style="font-size: .875rem; font-weight: 500; margin-bottom: .5rem">Тижні семестру</div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr)); gap: .25rem; font-size: .75rem">
            @foreach ($weeks as $w)
                <div style="padding: .25rem .4rem; border-radius: .375rem;
                    background: {{ $w['parity'] === \App\Enums\WeekParity::Numerator ? 'rgb(59 130 246 / .12)' : 'rgb(245 158 11 / .15)' }};
                    {{ $w['override'] ? 'outline: 2px solid rgb(220 38 38);' : '' }}"
                    title="{{ $w['shifted'] ? 'Чергування зсунуте перевизначенням' : '' }}">
                    {{ $w['start']->format('d.m') }}–{{ $w['start']->addDays(4)->format('d.m') }}
                    <strong>{{ $w['parity'] === \App\Enums\WeekParity::Numerator ? 'Ч' : 'З' }}</strong>
                    @if ($w['shifted'])<span style="color: rgb(220 38 38)">↻</span>@endif
                </div>
            @endforeach
        </div>
        <div style="font-size: .75rem; opacity: .7; margin-top: .4rem">
            Ч — чисельник, З — знаменник; червона рамка — перевизначений тиждень; ↻ — тип відрізняється від звичайного чергування.
        </div>
    </div>
@endif
