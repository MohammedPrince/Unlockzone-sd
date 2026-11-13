<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One message in the scrolling bar at the top of the customer site. */
class Announcement extends Model
{
    public const COLORS = ['default', 'blue', 'green', 'orange', 'violet', 'red'];

    protected $fillable = ['text', 'text_ar', 'icon', 'color', 'link', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The Arabic wording when the site is in Arabic and one was written, otherwise the main text. */
    public function localizedText(): string
    {
        return app()->getLocale() === 'ar' && filled($this->text_ar) ? $this->text_ar : $this->text;
    }
}
