<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardRecipient extends Model
{
    protected $guarded = [];

    public function award(): BelongsTo
    {
        return $this->belongsTo(AwardResult::class, 'award_result_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
