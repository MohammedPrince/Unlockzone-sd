<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['visitor_id', 'source', 'referrer_host', 'landing_path', 'user_id'];
}
