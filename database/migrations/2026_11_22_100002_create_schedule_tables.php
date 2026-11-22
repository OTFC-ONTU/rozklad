<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Перший запуск на MariaDB упав на задовгому імені індексу (65 > 64 символів), а DDL там
        // не відкочується: лишилися порожні таблиці без запису в migrations. Прибираємо їх, щоб
        // міграція пройшла заново. Записана міграція повторно не запускається, тож даних це не зачепить.
        $this->down();

        // Версія тижневого шаблону «діє з».
        Schema::create('schedule_versions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('effective_from');
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_from']);
        });

        // Одна пара шаблону. Підгрупи в одній клітинці — окремі пари.
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('pair');
            $table->string('week', 16)->default('every');
            $table->foreignId('discipline_id')->nullable()->constrained()->restrictOnDelete();
            // Назва фіксованого блоку («Класна година»), коли дисципліни немає.
            $table->string('title')->nullable();
            $table->boolean('is_fixed_block')->default(false);
            $table->string('format', 16)->default('in_person');
            $table->string('online_url')->nullable();
            // Закріплену пару генератор не чіпає.
            $table->boolean('is_pinned')->default(false);
            // Причина збереження попри жорсткі конфлікти.
            $table->text('override_reason')->nullable();
            $table->timestamps();

            $table->index(['schedule_version_id', 'weekday', 'pair']);
        });

        // Хто слухає пару: група цілком або її підгрупа. Кілька груп — потік.
        Schema::create('lesson_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('subgroup_id')->nullable()->constrained()->restrictOnDelete();

            $table->unique(['lesson_id', 'student_group_id', 'subgroup_id'], 'lesson_participants_unique');
        });

        Schema::create('lesson_teacher', function (Blueprint $table) {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->primary(['lesson_id', 'teacher_id']);
        });

        Schema::create('lesson_room', function (Blueprint $table) {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->primary(['lesson_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_room');
        Schema::dropIfExists('lesson_teacher');
        Schema::dropIfExists('lesson_participants');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('schedule_versions');
    }
};
