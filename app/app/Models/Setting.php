<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * Read a campus setting, falling back to the documented default assumption.
     */
    public static function value(string $key): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? config('brudel.defaults.'.$key);
    }
}
