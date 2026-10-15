<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Club extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * BEM & MPM first, then HIMAJU, then UKM; insertion order within each category.
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw("case category when 'lembaga' then 1 when 'himaju' then 2 else 3 end")->orderBy('id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isUkm(): bool
    {
        return $this->category === 'ukm';
    }

    public function categoryLabel(): string
    {
        return config('brudel.club_categories')[$this->category] ?? 'Unit Kegiatan Mahasiswa';
    }

    public function categoryShort(): string
    {
        return match ($this->category) {
            'lembaga' => 'Lembaga',
            'himaju' => 'HIMAJU',
            default => 'UKM',
        };
    }

    /**
     * Color class: the field for UKM, otherwise the category.
     */
    public function tone(): string
    {
        return $this->field ? Str::slug($this->field) : (string) $this->category;
    }

    public function recruitments(): HasMany
    {
        return $this->hasMany(Recruitment::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(Assignment::class)->where('role', 'pengurus');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function clubActivities(): HasMany
    {
        return $this->hasMany(ClubActivity::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(AwardResult::class)->where('status', 'verified');
    }
}
