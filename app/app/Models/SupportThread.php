<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation from a user to kemahasiswaan direktorat: a problem report or a feature request.
 */
class SupportThread extends Model
{
    public const CATEGORIES = ['kendala' => 'Laporkan kendala', 'fitur' => 'Usulan fitur'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Read markers hold the id of the last message seen, so a reply in the same second still counts as new.
     */
    public function unreadFor(bool $admin): bool
    {
        $read = $admin ? $this->admin_read_id : $this->user_read_id;

        return $read === null || $this->last_message_id > $read;
    }
}
