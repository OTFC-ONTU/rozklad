<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Заміна на дату. Шаблон від неї не змінюється.
        // lesson_id заповнено — пару шаблону змінено або скасовано; null — додаткова пара.
        Schema::create('schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_cancelled')->default(false);
            $table->unsignedTinyInteger('pair');
            $table->foreignId('discipline_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title')->nullable();
            $table->string('format', 16)->default('in_person');
            $table->string('online_url')->nullable();
            // Чому замінено (лікарняний, відрядження…).
            $table->text('reason')->nullable();
            // Причина збереження попри жорсткі конфлікти.
            $table->text('override_reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['date', 'lesson_id']);
            $table->index('date');
        });

        Schema::create('schedule_change_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_change_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('subgroup_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('schedule_change_teacher', function (Blueprint $table) {
            $table->foreignId('schedule_change_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->primary(['schedule_change_id', 'teacher_id']);
        });

        Schema::create('room_schedule_change', function (Blueprint $table) {
            $table->foreignId('schedule_change_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->primary(['schedule_change_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_schedule_change');
        Schema::dropIfExists('schedule_change_teacher');
        Schema::dropIfExists('schedule_change_participants');
        Schema::dropIfExists('schedule_changes');
    }
};
