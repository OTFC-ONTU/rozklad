<?php

namespace App\Schedule;

use App\Enums\DayLessonStatus;
use App\Models\Lesson;
use App\Models\ScheduleChange;

/**
 * Пара на конкретну дату з урахуванням замін.
 *
 * $lesson — що фактично відбувається: пара шаблону або незбережена модель, зібрана із заміни.
 * Для скасованої пари це пара за шаблоном (щоб показати, що саме скасовано).
 */
final readonly class DayLesson
{
    public function __construct(
        public Lesson $lesson,
        public DayLessonStatus $status,
        public ?Lesson $original = null,
        public ?ScheduleChange $change = null,
    ) {}

    /** Стабільний ключ для дій сторінки: пара шаблону або додаткова пара. */
    public function key(): string
    {
        return $this->original ? "l{$this->original->id}" : "c{$this->change->id}";
    }

    public function pair(): int
    {
        return $this->lesson->pair;
    }

    public function isCancelled(): bool
    {
        return $this->status === DayLessonStatus::Cancelled;
    }

    public function isChanged(): bool
    {
        return $this->status !== DayLessonStatus::Regular;
    }

    /** @return list<int> */
    public function groupIds(): array
    {
        return $this->lesson->participants->pluck('student_group_id')->unique()->values()->all();
    }

    /** @return list<int> */
    public function teacherIds(): array
    {
        return $this->lesson->teachers->modelKeys();
    }

    public function hasTeacher(int $teacherId): bool
    {
        return $this->lesson->teachers->contains('id', $teacherId);
    }

    public function hasGroup(int $groupId): bool
    {
        return $this->lesson->participants->contains('student_group_id', $groupId);
    }

    /**
     * Що змінилося порівняно з шаблоном — для позначок у листках.
     *
     * @return list<'pair'|'discipline'|'teachers'|'rooms'|'groups'|'format'>
     */
    public function changedParts(): array
    {
        if ($this->status !== DayLessonStatus::Changed) {
            return [];
        }

        $a = $this->original;
        $b = $this->lesson;
        $ids = fn ($items) => $items->modelKeys() === [] ? [] : collect($items->modelKeys())->sort()->values()->all();
        $members = fn (Lesson $l) => $l->participants->map(fn ($p) => "{$p->student_group_id}:{$p->subgroup_id}")->sort()->values()->all();

        return array_keys(array_filter([
            'pair' => $a->pair !== $b->pair,
            'discipline' => $a->discipline_id !== $b->discipline_id || $a->title !== $b->title,
            'teachers' => $ids($a->teachers) !== $ids($b->teachers),
            'rooms' => $ids($a->rooms) !== $ids($b->rooms),
            'groups' => $members($a) !== $members($b),
            'format' => $a->format !== $b->format,
        ]));
    }

    public function reason(): ?string
    {
        return $this->change?->reason;
    }
}
