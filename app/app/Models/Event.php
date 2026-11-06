<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /**
     * The person who answers for the event: Ketua of its organization, an admin jurusan or admin kemahasiswaan.
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function isEditableProposal(): bool
    {
        return in_array($this->status, ['draft', 'revision'], true);
    }
}
