<?php

namespace App\Schedule;

use App\Models\ScheduleChange;
use Illuminate\Support\Facades\DB;

/**
 * Заміни на дату: скасування, заміна викладача / аудиторії / дисципліни, перенесення,
 * додаткова пара. Шаблон не змінюється; перевірки конфліктів ті самі, що для шаблону.
 */
class DayEditor
{
    public function __construct(private readonly ConflictChecker $checker) {}

    /** Скасувати пару на дату. Додаткову пару просто видаляємо. */
    public function cancel(DayPlan $plan, DayLesson $lesson, ?string $reason = null): void
    {
        if ($lesson->original === null) {
            $lesson->change->delete();

            return;
        }

        DB::transaction(function () use ($plan, $lesson, $reason) {
            $change = $this->changeFor($plan, $lesson);
            $change->fill([
                'is_cancelled' => true,
                'pair' => $lesson->original->pair,
                'discipline_id' => $lesson->original->discipline_id,
                'title' => $lesson->original->title,
                'format' => $lesson->original->format,
                'online_url' => null,
                'reason' => filled($reason) ? $reason : null,
                'override_reason' => null,
                'user_id' => auth()->id(),
            ])->save();

            $change->participants()->delete();
            $change->teachers()->sync([]);
            $change->rooms()->sync([]);
        });
    }

    /** Повернути пару як за шаблоном. */
    public function restore(DayLesson $lesson): void
    {
        if ($lesson->original !== null) {
            $lesson->change?->delete();
        }
    }

    /**
     * Зберегти заміну пари шаблону ($editing з original), змінити додаткову пару або додати нову ($editing = null).
     * Якщо стан збігся з шаблоном, заміну прибираємо і повертаємо null.
     *
     * @throws ScheduleConflictException якщо є жорсткі конфлікти і причину обходу не вказано
     */
    public function save(DayPlan $plan, LessonDraft $draft, ?DayLesson $editing = null, ?string $reason = null, ?string $overrideReason = null): ?ScheduleChange
    {
        $report = $this->checker->checkOnDate($draft, $plan, $editing);

        if ($report->hasHard() && blank($overrideReason)) {
            throw new ScheduleConflictException($report);
        }

        if ($editing?->original !== null && $this->matchesTemplate($draft, $editing)) {
            $this->restore($editing);

            return null;
        }

        return DB::transaction(function () use ($plan, $draft, $editing, $reason, $report, $overrideReason) {
            $change = $editing ? $this->changeFor($plan, $editing) : new ScheduleChange(['date' => $plan->day->date->toDateString()]);

            $change->fill([
                'is_cancelled' => false,
                'pair' => $draft->pair,
                'discipline_id' => $draft->disciplineId,
                'title' => $draft->title,
                'format' => $draft->format,
                'online_url' => $draft->onlineUrl,
                'reason' => filled($reason) ? $reason : null,
                'override_reason' => $report->hasHard() ? $overrideReason : null,
                'user_id' => auth()->id(),
            ])->save();

            $change->participants()->delete();
            $change->participants()->createMany(array_map(
                fn (array $p) => ['student_group_id' => $p[0], 'subgroup_id' => $p[1]],
                $draft->participants,
            ));
            $change->teachers()->sync($draft->teacherIds);
            $change->rooms()->sync($draft->roomIds);

            return $change->load(['participants', 'teachers', 'rooms']);
        });
    }

    private function changeFor(DayPlan $plan, DayLesson $lesson): ScheduleChange
    {
        return $lesson->change
            ?? new ScheduleChange(['date' => $plan->day->date->toDateString(), 'lesson_id' => $lesson->original->id]);
    }

    private function matchesTemplate(LessonDraft $draft, DayLesson $editing): bool
    {
        $template = LessonDraft::fromLesson($editing->original);
        $sorted = function (array $items) {
            sort($items);

            return $items;
        };
        $members = fn (LessonDraft $d) => $sorted(array_map(fn (array $p) => "{$p[0]}:{$p[1]}", $d->participants));

        return $draft->pair === $template->pair
            && $draft->disciplineId === $template->disciplineId
            && $draft->title === $template->title
            && $draft->format === $template->format
            && $draft->onlineUrl === $template->onlineUrl
            && $sorted($draft->teacherIds) === $sorted($template->teacherIds)
            && $sorted($draft->roomIds) === $sorted($template->roomIds)
            && $members($draft) === $members($template);
    }
}
