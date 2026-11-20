<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Service extends Model
{
    // Archived with its deleted category when it has orders; never shown for sale again.
    use SoftDeletes;

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
        return $this->belongsTo(ServiceCategory::class, 'service_category_id')->withTrashed();
    }

    /** The service, or its category, was deleted by the admin: its orders show greyed out. */
    public function isArchived(): bool
    {
        return $this->trashed() || (bool) $this->category?->trashed();
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

    /** Field labels that mean "how many": the order total is the price times this number. */
    public const QUANTITY_LABEL = '/^\s*(qty|qnt|quantity|quantities|count|pcs|pieces|credits?|units?|الكمية|كمية|العدد)\b/iu';

    public const MAX_QUANTITY = 1000;

    public static function isQuantityLabel(string $label): bool
    {
        return (bool) preg_match(self::QUANTITY_LABEL, $label);
    }

    /** The number field (e.g. "QTY") whose value multiplies the price, if the service has one. */
    public function quantityField(?Collection $fields = null): ?CategoryField
    {
        return ($fields ?? $this->inputFields())
            ->first(fn (CategoryField $f) => in_array($f->input_type, ['number', 'text'], true) && self::isQuantityLabel($f->label));
    }

    /** True when a VIP customer pays something other than the site price for this service. */
    public function hasVipPrice(): bool
    {
        return $this->vip_price !== null || (float) setting('vip_price_adjust', 0) != 0;
    }

    /** The VIP price: the fixed VIP price when set, otherwise the site price with the VIP percentage. Never below zero. */
    public function vipPrice(): string
    {
        $price = $this->vip_price !== null
            ? (float) $this->vip_price
            : (float) $this->price * (1 + (float) setting('vip_price_adjust', 0) / 100);

        return number_format(max(0, round($price, 2)), 2, '.', '');
    }

    /** What $customer pays for one unit: the VIP price for a VIP customer, the site price for everyone else. */
    public function priceFor(?User $customer): string
    {
        return $customer?->is_vip ? $this->vipPrice() : number_format((float) $this->price, 2, '.', '');
    }

    /** What an order costs for $quantity units ($customer's price), in the database's decimal format. */
    public function totalFor(int $quantity, ?User $customer = null): string
    {
        return number_format(round((float) $this->priceFor($customer) * max(1, $quantity), 2), 2, '.', '');
    }

    /** A customer can order it only when both the service and its category are active. */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->category && $this->category->status === 'active';
    }
}
