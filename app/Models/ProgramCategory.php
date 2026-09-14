<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProgramCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'group',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProgramCategory $category) {
            if (! $category->slug) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function programs(): HasMany
    {
        return $this->hasMany(AssistanceProgram::class);
    }

    public function groupLabel(): string
    {
        return $this->group === 'student' ? 'Student' : 'General';
    }
}
