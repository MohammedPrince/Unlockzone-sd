<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Another service provider's server that orders can be forwarded to through its API. */
class ApiProvider extends Model
{
    public const TYPES = [
        'dhru' => 'DHRU Fusion API',
        'dhru_reseller' => 'DHRU Fusion Pro Reseller API',
    ];

    /** The API client for this provider's protocol. */
    public function client(): \App\Support\ProviderClient
    {
        return $this->type === 'dhru_reseller'
            ? new \App\Support\DhruResellerClient($this)
            : new \App\Support\DhruFusionClient($this);
    }

    public function needsUsername(): bool
    {
        return $this->type !== 'dhru_reseller';
    }

    // api_key is deliberately not fillable: it is set explicitly and never echoed back to a form.
    protected $fillable = ['name', 'type', 'url', 'username', 'status'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'last_checked_at' => 'datetime',
            'services_synced_at' => 'datetime',
        ];
    }

    public function remoteServices(): HasMany
    {
        return $this->hasMany(ProviderService::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
