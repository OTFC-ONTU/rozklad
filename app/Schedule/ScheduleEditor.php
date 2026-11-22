<?php

namespace App\Schedule;

use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * Збереження пар тижневого шаблону з перевіркою конфліктів.
 * Жорсткий конфлікт можна обійти лише з причиною — вона зберігається в парі.
 */
class ScheduleEditor
{
    public function __construct(private readonly ConflictChecker $checker) {}

    /**
     * @throws ScheduleConflictException якщо є жорсткі конфлікти і причину не вказано
     */
    public function save(LessonDraft $draft, ?string $overrideReason = null): Lesson
    {
        $report = $this->checker->check($draft);

        if ($report->hasHard() && blank($overrideReason)) {
            throw new ScheduleConflictException($report);
        }

        return DB::transaction(function () use ($draft, $report, $overrideReason) {
            $lesson = $draft->lessonId ? Lesson::findOrFail($draft->lessonId) : new Lesson;

            $lesson->fill([
                'schedule_version_id' => $draft->versionId,
                'weekday' => $draft->weekday,
                'pair' => $draft->pair,
                'week' => $draft->week,
                'discipline_id' => $draft->disciplineId,
                'title' => $draft->title,
                'is_fixed_block' => $draft->isFixedBlock,
                'format' => $draft->format,
                'online_url' => $draft->onlineUrl,
                'is_pinned' => $draft->isPinned,
                'override_reason' => $report->hasHard() ? $overrideReason : null,
            ])->save();

            $lesson->participants()->delete();
            $lesson->participants()->createMany(array_map(
                fn (array $p) => ['student_group_id' => $p[0], 'subgroup_id' => $p[1]],
                $draft->participants,
            ));
            $lesson->teachers()->sync($draft->teacherIds);
            $lesson->rooms()->sync($draft->roomIds);

            return $lesson->load(['participants', 'teachers', 'rooms']);
        });
    }
}
