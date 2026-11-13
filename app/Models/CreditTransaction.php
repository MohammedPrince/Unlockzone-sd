<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CreditTransaction extends Model
{
    public const TYPES = ['recharge', 'deduction', 'refund', 'adjustment'];
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'transaction_type', 'amount', 'balance_before', 'balance_after',
        'reference', 'description', 'service_request_id', 'performed_by',
    ];

    protected static function booted(): void
    {
        // The ledger is append-only: corrections are new rows (refund / adjustment).
        static::updating(fn () => throw new LogicException('Ledger entries cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Ledger entries cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
