<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Dosen or tendik accompanying a UKM activity or a competition.
 */
class Companion extends Model
{
    public const KINDS = ['dosen' => 'Dosen', 'tendik' => 'Tendik'];

    protected $guarded = [];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
