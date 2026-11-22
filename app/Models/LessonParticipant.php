<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = ['lesson_id', 'student_group_id', 'subgroup_id'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class);
    }

    /**
     * Чи можуть учасники двох пар бути тими самими людьми.
     * Різні підгрупи одного поділу — ні; ціла група з будь-якою своєю підгрупою — так;
     * підгрупи різних поділів — так (склад перетинається).
     */
    public function overlapsWith(self $other): bool
    {
        if ($this->student_group_id !== $other->student_group_id) {
            return false;
        }

        if ($this->subgroup_id === null || $other->subgroup_id === null) {
            return true;
        }

        if ($this->subgroup_id === $other->subgroup_id) {
            return true;
        }

        return $this->subgroup->division !== $other->subgroup->division;
    }

    public function label(): string
    {
        $code = $this->group->code;

        return $this->subgroup ? "{$code} ({$this->subgroup->name})" : $code;
    }

    public function studentsCount(): ?int
    {
        return $this->subgroup?->students_count ?? $this->group->students_count;
    }
}
