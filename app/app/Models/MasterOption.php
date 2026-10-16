<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One choice of a master data list (bidang, jenis kegiatan, lokasi, jabatan, …). Extra attributes per list live in meta.
 */
class MasterOption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'active' => 'boolean', 'position' => 'integer'];
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
