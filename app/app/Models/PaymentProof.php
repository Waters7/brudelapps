<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['transferred_on' => 'date', 'verified_at' => 'datetime', 'amount' => 'integer'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }
}
