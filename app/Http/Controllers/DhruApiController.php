<?php

namespace App\Http\Controllers;

use App\Services\DhruApiServer;
use Illuminate\Http\Request;

/**
 * POST {this site}/api/index.php — the DHRU Fusion API other servers use to order our services.
 * Runs without session, cookies or CSRF (see routes/api.php).
 */
class DhruApiController extends Controller
{
    public function __invoke(Request $request, DhruApiServer $server)
    {
        $body = $server->handle($request->all(), $request->ip());

        return response()->json($body, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
