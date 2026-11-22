<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Змін завжди дві, а зміну групи визначає курс: замість зміни групи («за замовчуванням»
 * і «на період») і налаштувань слотів окремо для кожної зміни — один запис «з дати»
 * з курсами 2 зміни, перемикачем 2 зміни і слотами обох змін.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settings', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->unique();
            $table->json('second_shift_courses');
            $table->boolean('second_shift_enabled')->default(true);
            $table->boolean('first_saturday_enabled')->default(false);
            $table->boolean('first_fourth_pair_enabled')->default(true);
            $table->boolean('second_saturday_enabled')->default(false);
            $table->boolean('second_fourth_pair_enabled')->default(true);
            $table->timestamps();
        });

        $this->copyExistingSettings();

        Schema::dropIfExists('student_group_shifts');
        Schema::dropIfExists('slot_settings');
        Schema::table('student_groups', fn (Blueprint $table) => $table->dropColumn('shift'));
    }

    /**
     * Курси 2 зміни — ті, де більшість груп за замовчуванням у 2 зміні. Записи слотів
     * переносяться на кожну їхню дату з чинними на неї значеннями обох змін.
     */
    private function copyExistingSettings(): void
    {
        $secondCourses = DB::table('student_groups')
            ->selectRaw('course, SUM(CASE WHEN shift = 2 THEN 1 ELSE 0 END) AS in_second, COUNT(*) AS total')
            ->groupBy('course')
            ->get()
            ->filter(fn ($row) => $row->in_second * 2 > $row->total)
            ->pluck('course')
            ->map(fn ($c) => (int) $c)
            ->sort()
            ->values()
            ->all();

        $slots = DB::table('slot_settings')->orderBy('effective_from')->get();
        $dates = $slots->pluck('effective_from')->unique()->values();

        if ($dates->isEmpty() && $secondCourses === []) {
            return;
        }

        if ($dates->isEmpty()) {
            $dates = collect(['2000-01-01']);
        }

        $now = now();

        foreach ($dates as $date) {
            $row = [
                'effective_from' => $date,
                'second_shift_courses' => json_encode($secondCourses),
                'second_shift_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ([1 => 'first', 2 => 'second'] as $shift => $prefix) {
                $current = $slots->where('shift', $shift)->filter(fn ($s) => $s->effective_from <= $date)->last();
                $row["{$prefix}_saturday_enabled"] = (bool) ($current->saturday_enabled ?? false);
                $row["{$prefix}_fourth_pair_enabled"] = (bool) ($current->fourth_pair_enabled ?? true);
            }

            DB::table('shift_settings')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            $table->unsignedTinyInteger('shift')->default(1)->after('students_count');
        });

        Schema::create('student_group_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->unsignedTinyInteger('shift');
            $table->timestamps();

            $table->unique(['student_group_id', 'effective_from']);
        });

        Schema::create('slot_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('shift');
            $table->date('effective_from');
            $table->boolean('saturday_enabled')->default(false);
            $table->boolean('fourth_pair_enabled')->default(true);
            $table->timestamps();

            $table->unique(['shift', 'effective_from']);
        });

        Schema::dropIfExists('shift_settings');
    }
};
