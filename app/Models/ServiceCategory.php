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

    /**
     * The categories whose orders "Order history" lists under this one: itself plus any archived
     * category with the same menu label, so a removed "IMEI Services" folds into the live "IMEI Orders".
     *
     * @return int[]
     */
    public static function historyIds(int $id): array
    {
        $category = static::withTrashed()->find($id, ['id', 'name', 'deleted_at']);
        if (!$category || $category->trashed()) {
            return [$id];
        }

        $label = $category->ordersLabel();

        return static::onlyTrashed()->get(['id', 'name', 'deleted_at'])
            ->filter(fn ($c) => $c->ordersLabel() === $label)
            ->pluck('id')->prepend($id)->unique()->values()->all();
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
