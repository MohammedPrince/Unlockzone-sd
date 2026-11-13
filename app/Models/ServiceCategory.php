<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'status', 'sort_order'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CategoryField::class)->orderBy('sort_order')->orderBy('id');
    }
}
