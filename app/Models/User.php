<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CUSTOMER = 'customer';
    public const STATUSES = ['active', 'pending', 'suspended'];

    // role and status are deliberately not fillable: they are set explicitly, never from a form array.
    protected $fillable = [
        'first_name', 'last_name', 'name', 'username', 'email', 'phone', 'password',
        'address1', 'address2', 'country', 'state', 'city', 'zip_code',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'notifications_seen_at' => 'datetime',
            'services_suspended' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /** Services this customer is suspended from. */
    public function blockedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_blocks')->withTimestamps();
    }

    /** Services the customer marked "Want to use frequently". */
    public function favoriteServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_favorites')->withTimestamps();
    }

    /** False when the customer is suspended from every service or from this one. */
    public function canOrder(int $serviceId): bool
    {
        return !$this->services_suspended && !$this->blockedServices()->whereKey($serviceId)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->first_name, 0, 1) . mb_substr((string) ($this->last_name ?: $this->username), 0, 1));
    }
}
