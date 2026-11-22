<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('title', 32)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->date('starts_on');
            $table->date('ends_on');
            // Тип тижня, на який припадає початок семестру; далі тижні чергуються.
            $table->string('first_week_parity', 16)->default('numerator');
            $table->timestamps();

            $table->unique(['academic_year_id', 'number']);
        });

        // Ручне перевизначення: з цього тижня чергування починається заново із заданого типу.
        Schema::create('week_parity_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->string('parity', 16);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['semester_id', 'week_start']);
        });

        // Свята, неробочі дні та дні «за розкладом іншого дня».
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('kind', 16);
            $table->unsignedTinyInteger('follows_weekday')->nullable();
            $table->string('follows_parity', 16)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('practice_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 16);
            $table->string('color', 16)->default('#60a5fa');
            $table->timestamps();
        });

        // Графік освітнього процесу: що робить група в період. Без запису в семестрі — теорія.
        Schema::create('group_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('practice_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['student_group_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_activities');
        Schema::dropIfExists('practice_types');
        Schema::dropIfExists('calendar_days');
        Schema::dropIfExists('week_parity_overrides');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
    }
};
