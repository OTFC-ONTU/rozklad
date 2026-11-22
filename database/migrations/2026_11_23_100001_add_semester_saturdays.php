<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Розклад складається на Пн–Пт, а субота йде за розкладом одного з цих днів: перша субота
 * семестру — за first_saturday_weekday, далі по колу за календарем. Які суботи навчальні,
 * обирають у семестрі окремо для кожної зміни; перемикачі субот у налаштуваннях змін зайві.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->unsignedTinyInteger('first_saturday_weekday')->default(1);
            // Дати навчальних субот (Y-m-d) для кожної зміни.
            $table->json('first_shift_saturdays')->nullable();
            $table->json('second_shift_saturdays')->nullable();
        });

        Schema::table('shift_settings', function (Blueprint $table) {
            $table->dropColumn(['first_saturday_enabled', 'second_saturday_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('shift_settings', function (Blueprint $table) {
            $table->boolean('first_saturday_enabled')->default(false);
            $table->boolean('second_saturday_enabled')->default(false);
        });

        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn(['first_saturday_weekday', 'first_shift_saturdays', 'second_shift_saturdays']);
        });
    }
};
