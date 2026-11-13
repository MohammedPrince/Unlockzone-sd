<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a provider's price list, copied here by "Sync services". */
class ProviderService extends Model
{
    protected $fillable = ['api_provider_id', 'remote_id', 'group_name', 'type', 'name', 'price', 'delivery_time', 'fields', 'needs_imei'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'needs_imei' => 'boolean'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ApiProvider::class, 'api_provider_id');
    }
}
