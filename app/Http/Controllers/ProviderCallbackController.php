<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\ProviderOrderService;
use Illuminate\Http\Request;

/**
 * "Your order changed" notices from DHRU Fusion Pro Reseller providers (feedback_url).
 *
 * The notice is unsigned, so its content is never trusted: it only names an order, and that
 * order's result is then read back from the provider's API with our own token.
 */
class ProviderCallbackController extends Controller
{
    public function __invoke(Request $request, int $provider, ProviderOrderService $orders)
    {
        $orderId = mb_substr((string) $request->input('order_id', $request->input('order_uuid', '')), 0, 100);
        $reference = mb_substr((string) $request->input('reference_id', ''), 0, 50);

        $match = ServiceRequest::with('provider')
            ->where('api_provider_id', $provider)
            ->whereIn('status', ['pending', 'processing'])
            ->where(fn ($q) => $q
                ->when($orderId !== '', fn ($w) => $w->orWhere('remote_order_id', $orderId))
                ->when($reference !== '', fn ($w) => $w->orWhere('reference', $reference)))
            ->when($orderId === '' && $reference === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->first();

        if ($match) {
            $orders->refresh($match);
        }

        // Always 200, so the provider does not keep retrying (and nothing is revealed about our orders).
        return response()->json(['status' => 'ok']);
    }
}
