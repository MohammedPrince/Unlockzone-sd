<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A deleted category that still has orders is archived (soft deleted): hidden everywhere except order history. */
class ServiceCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'status', 'sort_order'];

    /** "IMEI Services" -> "IMEI Orders", "Server" -> "Server Orders": the Order history menu entry. */
    public function ordersLabel(): string
    {
        $base = trim(preg_replace('/\s*\bservices?\s*$/iu', '', $this->name));

        return __(':category Orders', ['category' => $base !== '' ? $base : $this->name]);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CategoryField::class)->orderBy('sort_order')->orderBy('id');
    }
}
