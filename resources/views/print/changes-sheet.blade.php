@php use App\Enums\LessonFormat; @endphp
@extends('print.layout')

@section('title', "Зміни в розкладі {$dateText}")

@section('content')
    <h1>
        Зміни в розкладі занять на {{ mb_strtolower($dayName) }}, {{ $dateText }}@if ($followsText) ({{ $followsText }})@endif<br>
        @if ($parityText) по {{ $parityText }}, @endif{{ mb_strtolower($shift->label()) }}
    </h1>

    @if ($rows->isEmpty())
        <p class="empty">Змін немає.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 24mm">Група</th>
                    @foreach ($pairs as $pair)
                        <th>{{ $pair }} пара</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td style="font-weight: bold; text-align: center; vertical-align: middle">{{ $row['group']->code }}</td>
                        @foreach ($pairs as $pair)
                            @php
                                $items = collect($row['pairs'][$pair] ?? []);
                                $active = $items->reject->isCancelled();
                            @endphp
                            <td style="text-align: center">
                                @if ($active->isEmpty() && $items->isNotEmpty())
                                    —
                                @endif
                                @foreach ($active as $item)
                                    @php $l = $item->lesson; @endphp
                                    <div @class(['changed' => $item->isChanged()])>
                                        @if ($l->format === LessonFormat::Remote)
                                            <div class="small">дистанційно</div>
                                        @endif
                                        {{ $l->displayTitle() }}
                                        @if ($l->participants->contains(fn ($p) => $p->student_group_id === $row['group']->id && $p->subgroup))
                                            ({{ $l->participants->firstWhere('student_group_id', $row['group']->id)->subgroup->name }} підгр.)
                                        @endif
                                        <div class="small">
                                            {{ $l->teachers->pluck('short_name')->implode(', ') }}@if ($l->rooms->isNotEmpty()), {{ $l->rooms->map->label()->implode(', ') }}@endif
                                        </div>
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="note">Риска — пару скасовано; <span class="changed">виділено</span> — змінено.</p>
    @endif
@endsection
