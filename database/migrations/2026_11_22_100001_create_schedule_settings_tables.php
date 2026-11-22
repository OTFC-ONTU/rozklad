<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Налаштування змін, що діють з дати: курси 2 зміни (решта — у 1),
        // чи працює 2 зміна і які слоти доступні кожній зміні.
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

        // Матриця «день × пара × тиждень» обмежень викладача.
        Schema::create('teacher_constraints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            // null — увесь день.
            $table->unsignedTinyInteger('pair')->nullable();
            $table->string('week', 16)->default('every');
            $table->string('level', 16);
            // Обидві дати null — постійне обмеження; інакше тимчасове.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_constraints');
        Schema::dropIfExists('shift_settings');
    }
};
