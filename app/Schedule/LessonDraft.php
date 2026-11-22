<?php

namespace App\Schedule;

use App\Enums\LessonFormat;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\Lesson;

/**
 * Пара в тому вигляді, в якому її вводить диспетчер, до збереження.
 * Перевірка конфліктів працює саме з нею, щоб не писати в БД заздалегідь.
 */
final readonly class LessonDraft
{
    /**
     * @param  list<int>  $teacherIds
     * @param  list<int>  $roomIds
     * @param  list<array{0: int, 1: int|null}>  $participants  пари [id групи, id підгрупи або null]
     */
    public function __construct(
        public int $versionId,
        public Weekday $weekday,
        public int $pair,
        public WeekParity $week = WeekParity::Every,
        public ?int $disciplineId = null,
        public array $teacherIds = [],
        public array $roomIds = [],
        public array $participants = [],
        public ?string $title = null,
        public bool $isFixedBlock = false,
        public LessonFormat $format = LessonFormat::InPerson,
        public ?string $onlineUrl = null,
        public bool $isPinned = false,
        // id наявної пари, якщо це редагування: вона не конфліктує сама з собою.
        public ?int $lessonId = null,
    ) {}

    public static function fromLesson(Lesson $lesson): self
    {
        $lesson->loadMissing(['participants', 'teachers', 'rooms']);

        return new self(
            versionId: $lesson->schedule_version_id,
            weekday: $lesson->weekday,
            pair: $lesson->pair,
            week: $lesson->week,
            disciplineId: $lesson->discipline_id,
            teacherIds: $lesson->teachers->modelKeys(),
            roomIds: $lesson->rooms->modelKeys(),
            participants: $lesson->participants
                ->map(fn ($p) => [$p->student_group_id, $p->subgroup_id])
                ->all(),
            title: $lesson->title,
            isFixedBlock: $lesson->is_fixed_block,
            format: $lesson->format,
            onlineUrl: $lesson->online_url,
            isPinned: $lesson->is_pinned,
            lessonId: $lesson->id,
        );
    }

    public function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
