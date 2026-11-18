<?php

namespace App\Support;

/**
 * What the rest of the app needs from a provider's API, whatever protocol it speaks.
 * ApiProvider::client() picks the implementation from the provider's type.
 */
interface ProviderClient
{
    public const ORDER_PENDING = 'pending';
    public const ORDER_DONE = 'done';
    public const ORDER_REJECTED = 'rejected';

    /** ['balance' => float, 'currency' => ?string, 'email' => ?string] */
    public function accountInfo(): array;

    /** [{remote_id, group_name, type, name, price, delivery_time, needs_imei, fields: [{name, type, required, imei?}]}] */
    public function serviceList(): array;

    /**
     * Places one paid order and returns the provider's order id.
     * $custom is [provider field name => value]; $reference is our request reference (unique per order).
     */
    public function placeOrder(string $remoteServiceId, ?string $imei, array $custom = [], bool $serverService = false, int $quantity = 1, ?string $reference = null, array $remoteFields = []): string;

    /** ['state' => pending|done|rejected, 'result' => string, 'raw_status' => string] */
    public function orderStatus(string $remoteOrderId): array;
}
