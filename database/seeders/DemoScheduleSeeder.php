<?php

namespace Database\Seeders;

use App\Enums\CalendarDayKind;
use App\Enums\GroupActivityKind;
use App\Enums\RoomType;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\AcademicYear;
use App\Models\Building;
use App\Models\CalendarDay;
use App\Models\Discipline;
use App\Models\GroupActivity;
use App\Models\PracticeType;
use App\Models\Room;
use App\Models\ScheduleVersion;
use App\Models\StudentGroup;
use App\Models\Teacher;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleEditor;
use Illuminate\Database\Seeder;

/**
 * Демо-дані для локальної розробки (за зразком паперового розкладу 4 курсу).
 * НЕ викликається з DatabaseSeeder: `php artisan db:seed --class=DemoScheduleSeeder`.
 */
class DemoScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $main = Building::firstOrCreate(['number' => '1'], ['name' => 'Головний корпус']);
        $second = Building::firstOrCreate(['number' => '2'], ['name' => 'Другий корпус']);

        $r322 = Room::firstOrCreate(['building_id' => $second->id, 'number' => '322'], ['capacity' => 30]);
        $r101 = Room::firstOrCreate(['building_id' => $main->id, 'number' => '101'], ['capacity' => 60]);
        $r214 = Room::firstOrCreate(['building_id' => $main->id, 'number' => '214'], ['capacity' => 16, 'type' => RoomType::ComputerLab]);
        $r215 = Room::firstOrCreate(['building_id' => $main->id, 'number' => '215'], ['capacity' => 16, 'type' => RoomType::ComputerLab]);
        $gym = Room::firstOrCreate(['building_id' => $main->id, 'number' => '001'], ['type' => RoomType::Gym, 'capacity' => 90, 'max_groups' => 3]);

        $t = fn (string $full, string $short) => Teacher::firstOrCreate(['short_name' => $short], ['full_name' => $full]);
        $kryvchenko = $t('Кривченко Юлія Валеріївна', 'Кривченко Ю.В.');
        $stohul = $t('Стогул Вячеслав Миколайович', 'Стогул В.М.');
        $shuvalova = $t('Шувалова Ірина Олександрівна', 'Шувалова І.О.');
        $sport = $t('Петренко Олег Іванович', 'Петренко О.І.');
        $sport2 = $t('Коваль Марина Петрівна', 'Коваль М.П.');

        $d = fn (string $name, string $short, ?RoomType $type = null) => Discipline::firstOrCreate(['name' => $name], ['short_name' => $short, 'room_type' => $type]);
        $os = $d('Операційні системи', 'Опер. системи');
        $oop = $d('Об\'єктно-орієнтоване програмування', 'ООП', RoomType::ComputerLab);
        $en = $d('Іноземна мова (за професійним спрямуванням)', 'Ін. мова');
        $pe = $d('Фізичне виховання', 'Фізвиховання', RoomType::Gym);

        $g10 = StudentGroup::firstOrCreate(['code' => '4КГ-10'], ['course' => 4, 'students_count' => 28]);
        $g12 = StudentGroup::firstOrCreate(['code' => '4КГ-12'], ['course' => 4, 'students_count' => 24]);
        $kb06 = StudentGroup::firstOrCreate(['code' => '2КБ-06'], ['course' => 2, 'students_count' => 32]);
        $sub1 = $kb06->subgroups()->firstOrCreate(['division' => 'Інформатика', 'name' => '1'], ['students_count' => 16]);
        $sub2 = $kb06->subgroups()->firstOrCreate(['division' => 'Інформатика', 'name' => '2'], ['students_count' => 16]);

        $this->seedCalendar([$g10, $g12], $kb06);

        $version = ScheduleVersion::firstOrCreate(['title' => 'Демо: І семестр 2026–2027'], ['effective_from' => '2026-09-23']);

        if ($version->lessons()->exists()) {
            return;
        }

        $editor = app(ScheduleEditor::class);
        $add = fn (Weekday $day, int $pair, array $extra) => $editor->save(new LessonDraft(
            ...['versionId' => $version->id, 'weekday' => $day, 'pair' => $pair, ...$extra],
        ));

        // Потік: дві групи, одна пара.
        $add(Weekday::Monday, 4, ['disciplineId' => $os->id, 'teacherIds' => [$kryvchenko->id], 'roomIds' => [$r101->id],
            'participants' => [[$g10->id, null], [$g12->id, null]]]);

        // Підгрупи в одній клітинці.
        $add(Weekday::Monday, 1, ['disciplineId' => $oop->id, 'teacherIds' => [$stohul->id], 'roomIds' => [$r214->id], 'participants' => [[$kb06->id, $sub1->id]]]);
        $add(Weekday::Monday, 1, ['disciplineId' => $oop->id, 'teacherIds' => [$shuvalova->id], 'roomIds' => [$r215->id], 'participants' => [[$kb06->id, $sub2->id]]]);

        // Чисельник / знаменник.
        $add(Weekday::Tuesday, 2, ['disciplineId' => $os->id, 'week' => WeekParity::Numerator, 'teacherIds' => [$kryvchenko->id], 'roomIds' => [$r322->id], 'participants' => [[$g10->id, null]]]);
        $add(Weekday::Tuesday, 2, ['disciplineId' => $en->id, 'week' => WeekParity::Denominator, 'teacherIds' => [$shuvalova->id], 'roomIds' => [$r322->id], 'participants' => [[$g10->id, null]]]);

        // Спортзал на кілька груп.
        $add(Weekday::Thursday, 3, ['disciplineId' => $pe->id, 'teacherIds' => [$sport->id], 'roomIds' => [$gym->id], 'participants' => [[$g10->id, null]]]);
        $add(Weekday::Thursday, 3, ['disciplineId' => $pe->id, 'teacherIds' => [$sport2->id], 'roomIds' => [$gym->id], 'participants' => [[$g12->id, null]]]);

        // Класна година — фіксований блок у всіх груп.
        foreach ([$g10, $g12, $kb06] as $group) {
            $add(Weekday::Wednesday, 4, ['title' => 'Класна година', 'isFixedBlock' => true, 'participants' => [[$group->id, null]]]);
        }
    }

    /** @param  list<StudentGroup>  $fourthCourse */
    private function seedCalendar(array $fourthCourse, StudentGroup $kb06): void
    {
        $year = AcademicYear::firstOrCreate(['title' => '2026–2027'], ['starts_on' => '2026-09-01', 'ends_on' => '2027-06-30']);
        $year->semesters()->firstOrCreate(['number' => 1], ['starts_on' => '2026-09-01', 'ends_on' => '2026-12-31', 'first_week_parity' => WeekParity::Numerator]);
        $year->semesters()->firstOrCreate(['number' => 2], ['starts_on' => '2027-01-25', 'ends_on' => '2027-06-30', 'first_week_parity' => WeekParity::Numerator]);

        CalendarDay::firstOrCreate(['date' => '2026-10-01'], ['kind' => CalendarDayKind::Holiday, 'note' => 'День захисників і захисниць']);
        CalendarDay::firstOrCreate(['date' => '2026-11-21'], [
            'kind' => CalendarDayKind::Working, 'follows_weekday' => Weekday::Tuesday,
            'follows_parity' => WeekParity::Denominator, 'note' => 'Відпрацювання',
        ]);

        if (GroupActivity::exists()) {
            return;
        }

        $practice = PracticeType::firstOrCreate(['name' => 'Навчальна ознайомча за фахом'], ['short_name' => 'НОП', 'color' => '#60a5fa']);
        $kb06->activities()->create(['kind' => GroupActivityKind::Practice, 'practice_type_id' => $practice->id, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-25']);

        foreach ($fourthCourse as $group) {
            $group->activities()->create(['kind' => GroupActivityKind::CreditWeek, 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-18']);
            $group->activities()->create(['kind' => GroupActivityKind::Session, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-31']);
        }
    }
}
