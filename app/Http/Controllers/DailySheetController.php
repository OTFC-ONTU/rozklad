<?php

namespace App\Http\Controllers;

use App\Enums\Shift;
use App\Enums\WeekParity;
use App\Models\SlotSetting;
use App\Models\StudentGroup;
use App\Models\Teacher;
use App\Schedule\DailySchedule;
use App\Schedule\DayLesson;
use App\Schedule\DayPlan;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/**
 * Друковані листки на день: облік виходу викладачів і зміни для здобувачів.
 * HTML для друку з браузера (у PDF — через «Зберегти як PDF»).
 */
class DailySheetController extends Controller
{
    public function __construct(private readonly DailySchedule $daily) {}

    public function attendance(string $date, int $shift): View
    {
        [$plan, $shift] = $this->resolve($date, $shift);
        $lessons = $plan->inShift($shift);

        $teachers = Teacher::whereKey($lessons->flatMap->teacherIds()->unique())->orderBy('short_name')->get();
        $rows = $teachers->map(fn (Teacher $t) => [
            'teacher' => $t,
            'pairs' => $lessons->filter(fn (DayLesson $l) => $l->hasTeacher($t->id))->groupBy(fn (DayLesson $l) => $l->pair()),
        ]);

        return view('print.attendance-sheet', [
            ...$this->header($plan, $shift),
            'rows' => $rows,
        ]);
    }

    public function changes(string $date, int $shift): View
    {
        [$plan, $shift] = $this->resolve($date, $shift);
        $lessons = $plan->inShift($shift);

        // Групи, яких торкнулася хоч одна заміна; у рядку — увесь день групи.
        $groupIds = $lessons->filter->isChanged()->flatMap(fn (DayLesson $l) => array_merge(
            $l->groupIds(), $l->original?->participants->pluck('student_group_id')->all() ?? [],
        ))->unique();

        $rows = StudentGroup::whereKey($groupIds)->orderBy('code')->get()->map(fn (StudentGroup $g) => [
            'group' => $g,
            'pairs' => $plan->forGroup($g->id)->groupBy(fn (DayLesson $l) => $l->pair()),
        ]);

        return view('print.changes-sheet', [
            ...$this->header($plan, $shift),
            'rows' => $rows,
        ]);
    }

    /** @return array{0: DayPlan, 1: Shift} */
    private function resolve(string $date, int $shift): array
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        abort_if($parsed === false || $parsed->toDateString() !== $date, 404);

        return [$this->daily->plan($parsed), Shift::tryFrom($shift) ?? abort(404)];
    }

    private function header(DayPlan $plan, Shift $shift): array
    {
        $day = $plan->day;

        return [
            'plan' => $plan,
            'shift' => $shift,
            'pairs' => range(1, SlotSetting::MAX_PAIRS),
            'dayName' => mb_convert_case($day->date->locale('uk')->dayName, MB_CASE_TITLE),
            'dateText' => $day->date->format('d.m.Y'),
            // «по чисельнику» / «по знаменнику»
            'parityText' => match ($day->parity) {
                WeekParity::Numerator => 'чисельнику',
                WeekParity::Denominator => 'знаменнику',
                default => null,
            },
            'followsText' => $day->isRescheduled && $day->scheduleWeekday
                ? 'за розкладом: '.mb_strtolower($day->scheduleWeekday->label())
                : null,
        ];
    }
}
