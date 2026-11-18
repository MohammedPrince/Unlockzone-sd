<?php

use App\Http\Controllers\DhruApiController;
use Illuminate\Support\Facades\Route;

/*
 * Our own DHRU Fusion API for other servers (Admin > API providers > API clients).
 * Prefixed with /api by bootstrap/app.php, so the address is {site}/api/index.php,
 * which is what DHRU Fusion clients add to the server address by themselves.
 * POST only, so API keys never end up in web server access logs.
 */
Route::post('index.php', DhruApiController::class)->middleware('throttle:120,1')->name('dhru-api');
Route::post('/', DhruApiController::class)->middleware('throttle:120,1');
