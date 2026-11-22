<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentGroup extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'course', 'students_count'];

    protected function casts(): array
    {
        return [
            'course' => 'integer',
            'students_count' => 'integer',
        ];
    }

    public function subgroups(): HasMany
    {
        return $this->hasMany(Subgroup::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(GroupActivity::class)->orderBy('starts_on');
    }
}
