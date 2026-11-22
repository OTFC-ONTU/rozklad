<?php

namespace App\Models;

use App\Enums\Shift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGroupShift extends Model
{
    protected $fillable = ['student_group_id', 'effective_from', 'shift'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'shift' => Shift::class,
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }
}
