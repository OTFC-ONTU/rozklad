<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Налаштування слотів зміни, що діють з дати.
        Schema::create('slot_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('shift');
            $table->date('effective_from');
            $table->boolean('saturday_enabled')->default(false);
            $table->boolean('fourth_pair_enabled')->default(true);
            $table->timestamps();

            $table->unique(['shift', 'effective_from']);
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
        Schema::dropIfExists('slot_settings');
    }
};
