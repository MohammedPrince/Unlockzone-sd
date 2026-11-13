<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Exceptions\WorkflowException;
use App\Models\ProviderService;
use App\Models\ServiceRequest;
use App\Repositories\ActivityLogRepository;
use App\Support\DhruFusionClient;
use Throwable;

/**
 * Forwards paid requests to a connected provider and brings the answers back.
 *
 * Money rules stay in ServiceRequestService: this class only decides when to call
 * "completed" or "failed" there, so refunds still happen once and in one place.
 */
class ProviderOrderService
{
    public function __construct(
        private ServiceRequestService $requests,
        private ActivityLogRepository $logs,
    ) {
    }

    /**
     * Sends a freshly paid request to its provider. Never throws: if the provider refuses or
     * cannot be reached the request stays pending with a note, and an administrator decides.
     */
    public function dispatch(ServiceRequest $request, bool $force = false): bool
    {
        $request->loadMissing('service.provider');
        $service = $request->service;

        if (!$service || !$service->usesProvider() || !$service->provider?->isActive() || !$request->isOpen() || $request->remote_order_id) {
            return false;
        }

        // Claim the request first so two clicks can never place two paid orders at the provider.
        $claimed = ServiceRequest::whereKey($request->id)
            ->whereNull('remote_order_id')
            ->when(!$force, fn ($q) => $q->where(fn ($w) => $w->whereNull('provider_status')->orWhere('provider_status', '!=', 'sending')))
            ->update(['provider_status' => 'sending', 'api_provider_id' => $service->api_provider_id]);

        if (!$claimed) {
            return false;
        }

        [$imei, $custom] = $this->orderInputs($request, $service->api_provider_id, $service->remote_service_id);
        $remote = ProviderService::where('api_provider_id', $service->api_provider_id)->where('remote_id', $service->remote_service_id)->first();

        try {
            $orderId = (new DhruFusionClient($service->provider))
                ->placeOrder($service->remote_service_id, $imei, $custom, $remote?->type === 'SERVER');
        } catch (ProviderException $e) {
            $note = $e->unreachable
                ? $e->getMessage() . ' — check the provider\'s order list before sending again.'
                : $e->getMessage();

            ServiceRequest::whereKey($request->id)->update(['provider_status' => 'error', 'provider_message' => mb_substr($note, 0, 1000)]);
            $this->logs->log('provider_order_error', "Provider did not accept {$request->reference}: " . mb_substr($e->getMessage(), 0, 150), $request);
            $request->refresh();

            return false;
        } catch (Throwable $e) {
            report($e);
            ServiceRequest::whereKey($request->id)->update(['provider_status' => 'error', 'provider_message' => 'Unexpected error while sending the order.']);
            $request->refresh();

            return false;
        }

        ServiceRequest::whereKey($request->id)->update([
            'remote_order_id' => $orderId,
            'provider_status' => 'sent',
            'provider_message' => null,
            'provider_checked_at' => now(),
        ]);

        // Pending requests can be cancelled by the customer; one that is at the provider cannot.
        if ($request->fresh()->status === 'pending') {
            try {
                $this->requests->updateStatus($request->id, 'processing', null, null, null);
            } catch (WorkflowException) {
                // Someone changed the status meanwhile; nothing more to do.
            }
        }

        $this->logs->log('provider_order_sent', "Sent {$request->reference} to {$service->provider->name} (order {$orderId})", $request);
        $request->refresh();

        return true;
    }

    /** Asks the provider how one order is doing and completes / fails the request when it is final. */
    public function refresh(ServiceRequest $request): void
    {
        $request->loadMissing('provider');

        if (!$request->remote_order_id || !$request->isOpen() || !$request->provider) {
            return;
        }

        try {
            $state = (new DhruFusionClient($request->provider))->orderStatus($request->remote_order_id);
        } catch (ProviderException $e) {
            ServiceRequest::whereKey($request->id)->update(['provider_checked_at' => now(), 'provider_message' => mb_substr($e->getMessage(), 0, 1000)]);

            return;
        }

        ServiceRequest::whereKey($request->id)->update(['provider_checked_at' => now(), 'provider_message' => null]);

        try {
            if ($state['state'] === DhruFusionClient::ORDER_DONE) {
                $this->requests->updateStatus($request->id, 'completed', $state['result'] !== '' ? $state['result'] : 'Done.', null, null);
                ServiceRequest::whereKey($request->id)->update(['provider_status' => 'done']);
            } elseif ($state['state'] === DhruFusionClient::ORDER_REJECTED) {
                $this->requests->updateStatus($request->id, 'failed', null, $state['result'] !== '' ? $state['result'] : null, null);
                ServiceRequest::whereKey($request->id)->update(['provider_status' => 'done']);
            }
        } catch (WorkflowException) {
            // Already closed by an administrator.
        }

        $request->refresh();
    }

    /** Same as refresh(), but at most once every $seconds per request (used when a page is opened). */
    public function refreshIfStale(ServiceRequest $request, int $seconds = 20): void
    {
        if ($request->remote_order_id && $request->isOpen()
            && ($request->provider_checked_at === null || $request->provider_checked_at->lt(now()->subSeconds($seconds)))) {
            $this->refresh($request);
        }
    }

    /** Checks the open provider orders, oldest check first. Returns how many were looked at. */
    public function syncOpen(int $limit = 50): int
    {
        $open = ServiceRequest::with('provider')
            ->whereNotNull('remote_order_id')
            ->whereIn('status', ['pending', 'processing'])
            ->orderByRaw('provider_checked_at IS NOT NULL, provider_checked_at ASC')
            ->limit($limit)
            ->get();

        foreach ($open as $request) {
            $this->refresh($request);
        }

        return $open->count();
    }

    /**
     * Splits what the customer typed into the IMEI / serial and the provider's custom fields.
     * A custom field is matched to the provider's field name without caring about letter case.
     */
    private function orderInputs(ServiceRequest $request, int $providerId, string $remoteId): array
    {
        $remote = ProviderService::where('api_provider_id', $providerId)->where('remote_id', $remoteId)->first();
        $names = collect($remote?->fields ?? [])->pluck('name')->mapWithKeys(fn ($n) => [mb_strtolower($n) => $n]);

        $imei = null;
        $custom = [];

        foreach ($request->request_data ?? [] as $item) {
            $value = (string) ($item['value'] ?? '');

            if ($value === '') {
                continue;
            }

            if ($imei === null && in_array($item['type'] ?? '', ['imei', 'sn'], true)) {
                $imei = $value;
                continue;
            }

            $label = (string) ($item['label'] ?? '');
            $custom[$names[mb_strtolower($label)] ?? $label] = $value;
        }

        return [$imei, $custom];
    }
}
