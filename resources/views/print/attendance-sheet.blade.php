@extends('print.layout')

@section('title', "Листок обліку {$dateText}")

@section('content')
    <div class="approve">
        ЗАТВЕРДЖУЮ<br>
        Заступник директора з НМР<br>
        ____________________<br>
        «____» ____________ {{ $plan->day->date->year }} р.
    </div>

    <h1>
        Листок обліку виходу викладачів<br>
        {{ $dayName }} {{ $dateText }}@if ($followsText) ({{ $followsText }})@endif@if ($parityText), {{ $parityText === 'чисельнику' ? 'чисельник' : 'знаменник' }}@endif, {{ mb_strtolower($shift->label()) }}
    </h1>

    @if ($rows->isEmpty())
        <p class="empty">{{ $plan->day->isStudyDay ? 'Пар у цій зміні немає.' : 'Цього дня занять немає.' }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th class="num">№</th>
                    <th>Викладач</th>
                    @foreach ($pairs as $pair)
                        <th style="width: 17mm">{{ $pair }}</th>
                    @endforeach
                    <th style="width: 25mm">Підпис</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        <td>{{ $row['teacher']->short_name }}</td>
                        @foreach ($pairs as $pair)
                            <td style="text-align: center">
                                @foreach ($row['pairs'][$pair] ?? [] as $item)
                                    <div @class(['cancelled' => $item->isCancelled(), 'changed' => ! $item->isCancelled() && $item->isChanged()])>
                                        {{ $item->lesson->participants->map(fn ($p) => $p->subgroup ? $p->group->code.'/'.$p->subgroup->name : $p->group->code)->implode(', ') }}
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="note">Закреслено — пару скасовано; <span class="changed">курсивом</span> — заміна або додаткова пара.</p>
    @endif
@endsection
