<?php

namespace App\Support;

use App\Exceptions\ProviderException;
use App\Models\ApiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Client for the DHRU Fusion Pro Reseller API (https://github.com/dhru-com/reseller-api).
 *
 * REST + JSON under {workspace}/api/reseller/v1 with "Authorization: Bearer <token>":
 *   GET  /account                     balance of the token's wallet
 *   GET  /products                    price list (data.products, keyed by product_id)
 *   POST /order                       [{product_id, fields: [{<inputs>, reference_id, Quantity}]}]
 *   GET  /order?order_uuid=...        one order's status: new|pre-checking|accepted|in-process|pending|verify|success|rejected
 *
 * A call only succeeds when the HTTP code is 2xx AND the body says status "success" with a 2xx code
 * (some validation errors come back as HTTP 201 with status "error").
 * The documentation does not fix every response field name, so the readers below accept the usual variants.
 */
class DhruResellerClient implements ProviderClient
{
    public function __construct(private ApiProvider $provider)
    {
    }

    public function accountInfo(): array
    {
        $data = $this->call('GET', '/account');

        return [
            'balance' => $this->number($this->find($data, ['balance', 'credit', 'credits', 'wallet_balance', 'available_balance', 'amount']) ?? 0),
            'currency' => $this->stringOrNull($this->find($data, ['currency_code', 'currency', 'wallet_currency'])),
            'email' => $this->stringOrNull($this->find($data, ['email', 'mail'])),
        ];
    }

    public function serviceList(): array
    {
        $data = $this->call('GET', '/products');
        $products = $data['products'] ?? $data;
        $categories = $this->categoryNames($data['categories'] ?? []);
        $out = [];

        foreach ((array) $products as $key => $product) {
            if (!is_array($product)) {
                continue;
            }

            // Some answers nest the products inside their category.
            if (!isset($product['product_id']) && !isset($product['name']) && !isset($product['product_name']) && isset($product['products'])) {
                $group = (string) ($product['category_name'] ?? $product['name'] ?? $key);
                foreach ((array) $product['products'] as $innerKey => $inner) {
                    if (is_array($inner)) {
                        $out[] = $this->product($inner, $innerKey, $group);
                    }
                }
                continue;
            }

            $categoryId = $product['category_id'] ?? null;
            $group = $product['category_name'] ?? $product['category'] ?? $product['group_name'] ?? ($categoryId !== null ? ($categories[(string) $categoryId] ?? null) : null);
            $out[] = $this->product($product, $key, is_array($group) ? ($group['name'] ?? null) : $group);
        }

        return $out;
    }

    public function placeOrder(string $remoteServiceId, ?string $imei, array $custom = [], bool $serverService = false, int $quantity = 1, ?string $reference = null, array $remoteFields = []): string
    {
        $fields = $custom;

        if (filled($imei)) {
            // Send the IMEI / serial under the provider's own field name when the price list told us it.
            $imeiName = collect($remoteFields)->first(fn ($f) => !empty($f['imei']))['name'] ?? 'IMEI';
            $fields[$imeiName] = $imei;
        }

        // reference_id: 1-50 letters, digits or hyphens, unique per order (lets the provider spot a resend).
        $fields['reference_id'] = mb_substr(preg_replace('/[^A-Za-z0-9\-]/', '-', (string) ($reference ?: uniqid('SR-'))), 0, 50);
        $fields['Quantity'] = max(1, $quantity);

        // Our site is told when the order finishes if it is reachable over HTTPS; otherwise we poll.
        $callback = $this->callbackUrl();
        if ($callback) {
            $fields['feedback_url'] = $callback;
        }

        $productId = ctype_digit($remoteServiceId) ? (int) $remoteServiceId : $remoteServiceId;
        $key = is_int($productId) ? 'product_id' : 'product_uuid';

        $data = $this->call('POST', '/order', [[$key => $productId, 'fields' => [$fields]]], $callback ? [] : ['X-Dhru-Feedback-Mode' => 'polling'], 120);

        $item = $data[0][0] ?? $data[0] ?? $data;
        $orderId = is_array($item) ? (string) ($item['order_uuid'] ?? $item['order_id'] ?? '') : '';

        if ($orderId === '') {
            throw new ProviderException('The provider accepted the order but returned no order id. Check its order list before sending again.');
        }

        return $orderId;
    }

    public function orderStatus(string $remoteOrderId): array
    {
        try {
            $data = $this->call('GET', '/order', ['order_uuid' => $remoteOrderId]);
        } catch (ProviderException $e) {
            // 503 ORDER_RESULT_UNAVAILABLE means "not known right now", never a rejection.
            if (str_contains($e->getMessage(), 'ORDER_RESULT_UNAVAILABLE')) {
                return ['state' => self::ORDER_PENDING, 'result' => '', 'raw_status' => 'unavailable'];
            }
            throw $e;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $result = $this->plain((string) ($data['replay'] ?? $data['reply'] ?? $data['code'] ?? $data['comments'] ?? ''));

        $state = match ($status) {
            'success', 'completed', 'done' => self::ORDER_DONE,
            'rejected', 'cancelled', 'canceled', 'failed' => self::ORDER_REJECTED,
            default => self::ORDER_PENDING,
        };

        return ['state' => $state, 'result' => $result, 'raw_status' => $status];
    }

    /** Sends one request and returns the envelope's "data", or throws ProviderException. */
    public function call(string $method, string $path, array $body = [], array $headers = [], int $timeout = 40): mixed
    {
        try {
            $request = Http::withToken((string) $this->provider->api_key)
                ->acceptJson()
                ->withHeaders($headers)
                ->timeout($timeout)
                ->connectTimeout(15);

            $response = $method === 'GET'
                ? $request->get($this->base() . $path, $body)
                : $request->asJson()->post($this->base() . $path, $body);
        } catch (ConnectionException $e) {
            throw new ProviderException('Could not reach the provider: ' . $e->getMessage(), unreachable: true);
        }

        $json = $response->json();
        $reason = is_array($json) ? trim(implode(' ', array_filter([
            (string) ($json['message'] ?? ''),
            isset($json['reason_code']) ? '(' . $json['reason_code'] . ')' : '',
        ]))) : '';

        if (in_array($response->status(), [401, 402], true)) {
            throw new ProviderException('The provider refused the API token (HTTP ' . $response->status() . '). Check the token, its expiry and the allowed IP addresses.' . ($reason ? " {$reason}" : ''));
        }

        if ($response->status() === 429) {
            throw new ProviderException('The provider is rate limiting requests. Try again in a minute.', unreachable: true);
        }

        if ($response->serverError()) {
            throw new ProviderException('The provider answered with HTTP ' . $response->status() . '.' . ($reason ? " {$reason}" : ''), unreachable: true);
        }

        if (!is_array($json)) {
            // Usually a normal website (e.g. www.dhru.com itself) instead of a supplier's Fusion Pro workspace.
            $looksLikePage = str_contains(strtolower(substr((string) $response->body(), 0, 500)), '<html');
            throw new ProviderException(sprintf(
                'No API answer at %s (HTTP %d%s). Use the address of your supplier\'s DHRU Fusion Pro workspace (the site where you have your reseller account and created the API token), not www.dhru.com.',
                $this->base() . $path, $response->status(), $looksLikePage ? ', a web page was returned' : ''
            ));
        }

        $code = (int) ($json['code'] ?? $response->status());
        if (!$response->successful() || strtolower((string) ($json['status'] ?? '')) !== 'success' || $code < 200 || $code > 299) {
            throw new ProviderException($reason !== '' ? $reason : 'The provider returned an error (HTTP ' . $response->status() . ').');
        }

        return $json['data'] ?? [];
    }

    /** https://workspace.example → https://workspace.example/api/reseller/v1 */
    private function base(): string
    {
        $url = rtrim(trim((string) $this->provider->url), '/');

        return str_contains($url, '/api/reseller/') ? $url : $url . '/api/reseller/v1';
    }

    private function callbackUrl(): ?string
    {
        $url = route('providers.callback', ['provider' => $this->provider->id]);

        return str_starts_with($url, 'https://') ? $url : null;
    }

    private function product(array $p, int|string $key, ?string $group): array
    {
        $id = (string) ($p['product_id'] ?? $p['id'] ?? $key);
        $fields = $this->fields($p['fields'] ?? $p['required_fields'] ?? []);
        $needsImei = collect($fields)->contains(fn ($f) => !empty($f['imei']));
        $type = strtoupper((string) ($p['type'] ?? $p['product_type'] ?? ($needsImei ? 'IMEI' : 'SERVER')));
        $price = $p['price'] ?? $p['amount'] ?? $p['credit'] ?? $p['cost'] ?? 0;

        return [
            'remote_id' => mb_substr($id, 0, 40),
            'group_name' => $group !== null && $group !== '' ? mb_substr((string) $group, 0, 191) : null,
            'type' => mb_substr($type, 0, 20),
            'name' => mb_substr(trim(strip_tags((string) ($p['product_name'] ?? $p['name'] ?? $p['title'] ?? "Product {$id}"))), 0, 255),
            'price' => $this->number(is_array($price) ? ($price['amount'] ?? $price['value'] ?? reset($price)) : $price),
            'delivery_time' => mb_substr(trim(strip_tags((string) ($p['delivery_time'] ?? $p['time'] ?? $p['process_time'] ?? $p['duration'] ?? ''))), 0, 100) ?: null,
            'needs_imei' => $needsImei,
            'fields' => $fields,
        ];
    }

    /** The product's input schema, as a list or as {name: definition}. */
    private function fields(mixed $raw): array
    {
        $out = [];

        foreach ((array) $raw as $key => $field) {
            $def = is_array($field) ? $field : [];
            $name = trim((string) ($def['name'] ?? $def['field_name'] ?? $def['fieldname'] ?? $def['label'] ?? $def['key'] ?? (is_string($key) ? $key : (is_string($field) ? $field : ''))));

            // These are filled in by the system, not by the customer.
            if ($name === '' || in_array(strtolower($name), ['reference_id', 'feedback_url', 'quantity'], true)) {
                continue;
            }

            $required = $def['required'] ?? $def['is_required'] ?? $def['mandatory'] ?? true;

            $out[] = [
                'name' => mb_substr($name, 0, 100),
                'type' => strtolower((string) ($def['type'] ?? $def['field_type'] ?? 'text')),
                'required' => filter_var($required, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $required,
                // The IMEI / serial number goes into the customer's IMEI field, not a separate box.
                'imei' => (bool) preg_match('/^(imei|imei\s*\/\s*sn|sn|serial(\s*number)?)$/i', $name),
            ];
        }

        return $out;
    }

    private function categoryNames(mixed $categories): array
    {
        $names = [];

        foreach ((array) $categories as $key => $category) {
            if (is_array($category)) {
                $names[(string) ($category['category_id'] ?? $category['id'] ?? $key)] = (string) ($category['category_name'] ?? $category['name'] ?? '');
            } elseif (is_string($category)) {
                $names[(string) $key] = $category;
            }
        }

        return $names;
    }

    /** First value found under one of $keys, searching nested arrays (the account answer's shape is not documented). */
    private function find(mixed $data, array $keys, int $depth = 0): mixed
    {
        if (!is_array($data) || $depth > 4) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && !is_array($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value) && ($found = $this->find($value, $keys, $depth + 1)) !== null) {
                return $found;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function number(mixed $value): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    private function plain(string $text): string
    {
        // Status reads return plain text (only callbacks use base64, and their body is never trusted or used).
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
