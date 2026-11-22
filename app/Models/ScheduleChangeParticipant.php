<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleChangeParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = ['schedule_change_id', 'student_group_id', 'subgroup_id'];

    public function change(): BelongsTo
    {
        return $this->belongsTo(ScheduleChange::class, 'schedule_change_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(Subgroup::class);
    }
}
