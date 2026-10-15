<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CertificateVersion::class)->orderByDesc('version');
    }

    public function current(): ?CertificateVersion
    {
        return $this->versions()->where('version', $this->current_version)->first();
    }
}
