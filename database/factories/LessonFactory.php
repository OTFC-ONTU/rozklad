<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\ScheduleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Створює лише саму пару; учасників, викладачів і аудиторії додавайте в тесті
 * або зберігайте через ScheduleEditor.
 *
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schedule_version_id' => ScheduleVersion::factory(),
            'weekday' => Weekday::Monday,
            'pair' => 1,
            'discipline_id' => Discipline::factory(),
        ];
    }
}
