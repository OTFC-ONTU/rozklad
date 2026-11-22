<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Довідники, без яких не можна скласти розклад.
 * Циклові комісії, спеціальності та види практик з'являться разом зі своїми розділами.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('number', 16)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('number', 16);
            $table->string('type', 32)->default('classroom');
            $table->unsignedSmallInteger('capacity')->nullable();
            // Скільки груп може займатися одночасно (спортзал — кілька).
            $table->unsignedTinyInteger('max_groups')->default(1);
            $table->timestamps();

            $table->unique(['building_id', 'number']);
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('short_name');
            $table->timestamps();
        });

        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name');
            // Потрібний тип аудиторії; null — будь-яка.
            $table->string('room_type', 32)->nullable();
            $table->timestamps();
        });

        // «groups» — зарезервоване слово в MySQL 8, тому student_groups.
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->unsignedTinyInteger('course');
            $table->unsignedSmallInteger('students_count')->nullable();
            // Зміна за замовчуванням; зміни на період — у student_group_shifts.
            $table->unsignedTinyInteger('shift')->default(1);
            $table->timestamps();
        });

        Schema::create('student_group_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->unsignedTinyInteger('shift');
            $table->timestamps();

            $table->unique(['student_group_id', 'effective_from']);
        });

        Schema::create('subgroups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            // Поділ групи («Англійська», «Практика»…). Підгрупи одного поділу не перетинаються
            // за складом і можуть займатися одночасно; підгрупи різних поділів — ні.
            $table->string('division', 64);
            $table->string('name', 64);
            $table->unsignedSmallInteger('students_count')->nullable();
            $table->timestamps();

            $table->unique(['student_group_id', 'division', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subgroups');
        Schema::dropIfExists('student_group_shifts');
        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('disciplines');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('buildings');
    }
};
