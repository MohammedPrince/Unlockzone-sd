<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    public const STATUSES = ['pending', 'processing', 'completed', 'failed', 'cancelled'];

    /** Which statuses each status may move to. Completed / failed / cancelled are final. */
    public const TRANSITIONS = [
        'pending' => ['processing', 'completed', 'failed', 'cancelled'],
        'processing' => ['completed', 'failed', 'cancelled'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'user_id', 'service_id', 'reference', 'idempotency_key', 'amount', 'status',
        'request_data', 'response_data', 'error_message', 'processed_by', 'refunded_at', 'completed_at',
        'api_provider_id', 'remote_order_id', 'provider_status', 'provider_message', 'provider_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'response_data' => 'array',
            'refunded_at' => 'datetime',
            'completed_at' => 'datetime',
            'provider_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class)->orderBy('id');
    }

    public function nextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}
