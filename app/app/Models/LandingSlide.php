<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One slide of the front-page scrolling banner: a short muted video, an image with a caption, or a text slide
 * made from a competition or a winner's award (source lomba/penghargaan, no media).
 */
class LandingSlide extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeShown(Builder $query): void
    {
        $query->where('active', true)->orderBy('position')->orderBy('id');
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function hasMedia(): bool
    {
        return $this->media_path !== null;
    }

    public function mediaUrl(): string
    {
        return '/slide/'.$this->id.'?v='.$this->updated_at?->timestamp;
    }
}
