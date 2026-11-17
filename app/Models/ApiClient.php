<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Another server that uses this server's API, signing in as one of our customers. */
class ApiClient extends Model
{
    // The key hash is never fillable: it is set only by newKey().
    protected $fillable = ['user_id', 'access', 'status', 'allowed_ips', 'note'];

    protected $hidden = ['api_key_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The services this client may use when access is "selected". */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'api_client_services');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public static function hashKey(string $key): string
    {
        return hash('sha256', trim($key));
    }

    /** Makes a new key (DHRU style: 8 groups of 3), stores its hash and returns the key itself. */
    public function newKey(): string
    {
        $key = implode('-', str_split(strtoupper(Str::random(24)), 3));
        $this->api_key_hash = self::hashKey($key);
        $this->api_key_hint = substr($key, -7);

        return $key;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function sharesAll(): bool
    {
        return $this->access === 'all';
    }

    /** The allow-list as an array; empty means every address may connect. */
    public function ipList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $this->allowed_ips))));
    }

    public function allowsIp(?string $ip): bool
    {
        $list = $this->ipList();

        return !$list || in_array((string) $ip, $list, true);
    }
}
