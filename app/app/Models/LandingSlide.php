<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One slide of the front-page scrolling banner: a short muted video or an image with a caption.
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

    public function mediaUrl(): string
    {
        return '/slide/'.$this->id.'?v='.$this->updated_at?->timestamp;
    }
}
