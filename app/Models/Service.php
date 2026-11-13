<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Service extends Model
{
    protected $fillable = [
        'service_category_id', 'name', 'slug', 'description', 'price', 'status', 'delivery_time', 'is_instant', 'configuration',
        'api_provider_id', 'remote_service_id',
    ];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'is_instant' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }

    /** True when orders for this service are forwarded to a connected provider. */
    public function usesProvider(): bool
    {
        return $this->api_provider_id !== null && filled($this->remote_service_id);
    }

    /** Shows the "New" tag for the number of days chosen in Settings (0 turns the tag off). */
    public function isNew(): bool
    {
        $days = (int) setting('new_badge_days', 7);

        return $days > 0 && $this->created_at !== null && $this->created_at->gte(now()->subDays($days));
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function fieldIds(): array
    {
        return array_map('intval', $this->configuration['field_ids'] ?? []);
    }

    /** The category fields this service asks the customer to fill in. */
    public function inputFields(): Collection
    {
        $ids = $this->fieldIds();

        if (!$ids) {
            return collect();
        }

        return CategoryField::where('service_category_id', $this->service_category_id)
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** A customer can order it only when both the service and its category are active. */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->category && $this->category->status === 'active';
    }
}
