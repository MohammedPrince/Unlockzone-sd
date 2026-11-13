<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWallet extends Model
{
    protected $fillable = ['user_id', 'balance'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
