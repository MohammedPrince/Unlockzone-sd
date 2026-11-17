<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A fixed price for one service, for one API client. */
class ApiClientPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['api_client_id', 'service_id', 'price'];
}
