<?php

namespace App\Support;

use App\Exceptions\ProviderException;
use App\Models\ApiProvider;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Client for the DHRU Fusion API, the standard that GSM service servers speak.
 *
 * Every call is a form POST to {server}/api/index.php with the username, the API access key,
 * an action name and the parameters as a small XML document. The answer is JSON with either
 * a SUCCESS or an ERROR list.
 */
class DhruFusionClient implements ProviderClient
{
    public function __construct(private ApiProvider $provider)
    {
    }

    /** ['balance' => float, 'currency' => ?string, 'email' => ?string] */
    public function accountInfo(): array
    {
        $data = $this->call('accountinfo');
        $info = $data['AccoutInfo'] ?? $data['AccountInfo'] ?? $data['ACCOUNTINFO'] ?? [];

        return [
            'balance' => $this->number($info['creditraw'] ?? $info['credit'] ?? $info['CREDIT'] ?? 0),
            'currency' => $info['currency'] ?? $info['CURRENCY'] ?? null,
            'email' => $info['mail'] ?? $info['MAIL'] ?? null,
        ];
    }

    /**
     * The provider's price list, flattened:
     * [{remote_id, group_name, type, name, price, delivery_time, needs_imei, fields: [{name, type, required}]}]
     */
    public function serviceList(): array
    {
        $data = $this->call('imeiservicelist');
        $out = [];

        foreach ((array) ($data['LIST'] ?? []) as $groupKey => $group) {
            if (!is_array($group)) {
                continue;
            }

            $groupName = (string) ($group['GROUPNAME'] ?? $groupKey);
            $groupType = strtoupper((string) ($group['GROUPTYPE'] ?? 'IMEI'));

            foreach ((array) ($group['SERVICES'] ?? []) as $serviceKey => $service) {
                if (!is_array($service)) {
                    continue;
                }

                $id = (string) ($service['SERVICEID'] ?? $serviceKey);
                $type = strtoupper((string) ($service['SERVICETYPE'] ?? $groupType));
                $fields = $this->customFields($service);

                // An IMEI service can rename its IMEI box (CUSTOM.customname), e.g. "Lock Code" or "Email": the
                // customer types that, and it is still sent as the IMEI. Kept as the first field, flagged "imei".
                if ($type === 'IMEI' && ($boxName = $this->imeiBoxName($service)) !== null) {
                    $fields = array_values(array_filter($fields, fn ($f) => mb_strtolower($f['name']) !== mb_strtolower($boxName)));
                    array_unshift($fields, ['name' => $boxName, 'type' => 'text', 'required' => true, 'imei' => true]);
                }

                $out[] = [
                    'remote_id' => $id,
                    'group_name' => mb_substr($groupName, 0, 191),
                    'type' => mb_substr($type, 0, 20),
                    'name' => mb_substr(trim(strip_tags((string) ($service['SERVICENAME'] ?? "Service {$id}"))), 0, 255),
                    'price' => $this->number($service['CREDIT'] ?? 0),
                    'delivery_time' => mb_substr(trim(strip_tags((string) ($service['TIME'] ?? ''))), 0, 100) ?: null,
                    'needs_imei' => $type === 'IMEI',
                    'fields' => $fields,
                ];
            }
        }

        return $out;
    }

    /**
     * Places an order. $imei may be empty for server services; $custom is [field name => value].
     * Returns the provider's order id.
     */
    public function placeOrder(string $remoteServiceId, ?string $imei, array $custom = [], bool $serverService = false, int $quantity = 1, ?string $reference = null, array $remoteFields = []): string
    {
        $params = ['ID' => $remoteServiceId];

        if (filled($imei)) {
            $params['IMEI'] = $imei;
        }

        if ($custom) {
            $params['CUSTOMFIELD'] = base64_encode(json_encode($custom, JSON_UNESCAPED_UNICODE));
        }

        // QNT: how many units (server services always send it; others only when more than one).
        if ($serverService || $quantity > 1) {
            $params['QNT'] = max(1, $quantity);
        }

        $data = $this->call('placeimeiorder', $params);
        $reference = (string) ($data['REFERENCEID'] ?? $data['referenceid'] ?? '');

        if ($reference === '') {
            throw new ProviderException('The provider accepted the order but returned no order id.');
        }

        return $reference;
    }

    /** ['state' => pending|done|rejected, 'result' => string, 'raw_status' => string] */
    public function orderStatus(string $remoteOrderId): array
    {
        $data = $this->call('getimeiorder', ['ID' => $remoteOrderId]);
        $status = (string) ($data['STATUS'] ?? '');
        $result = trim((string) ($data['CODE'] ?? ''));

        if ($result === '') {
            $result = trim((string) ($data['COMMENTS'] ?? ''));
        }

        // 0 new, 1 in process, 2 (some servers) in process, 3 rejected, 4 available / success.
        $state = match ($status) {
            '4' => self::ORDER_DONE,
            '3' => self::ORDER_REJECTED,
            default => self::ORDER_PENDING,
        };

        return ['state' => $state, 'result' => $this->plain($result), 'raw_status' => $status];
    }

    /** Sends one action and returns the first SUCCESS entry, or throws ProviderException. */
    public function call(string $action, array $params = []): array
    {
        try {
            $response = Http::asForm()
                ->timeout(40)
                ->connectTimeout(15)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($this->endpoint(), [
                    'username' => $this->provider->username,
                    'apiaccesskey' => $this->provider->api_key,
                    'action' => $action,
                    'requestformat' => 'JSON',
                    'parameters' => $this->xml($params),
                ]);
        } catch (ConnectionException $e) {
            throw new ProviderException('Could not reach the provider: ' . $e->getMessage(), unreachable: true);
        }

        if ($response->serverError()) {
            throw new ProviderException('The provider answered with HTTP ' . $response->status() . '.', unreachable: true);
        }

        // Some servers print a byte-order mark or warnings before the JSON.
        $body = (string) $response->body();
        $start = strpos($body, '{');
        $json = $start === false ? null : json_decode(substr($body, $start), true);

        if (!is_array($json)) {
            // A Cloudflare "Just a moment..." bot check answers instead of the API. Often only one form of the address
            // is protected (for example without www), so the other form works.
            if ($response->header('cf-mitigated') !== '' || str_contains($body, 'Just a moment...') || str_contains($body, 'challenge-platform')) {
                throw new ProviderException(
                    "The provider's Cloudflare protection blocked the request (\"Just a moment...\" page), so no API answer came back. "
                    . 'Try the address with www (or without it), or ask the provider to allow API calls to /api/index.php.'
                );
            }

            throw new ProviderException('The provider did not return a valid API answer (HTTP ' . $response->status() . '). Check the server address.');
        }

        if (!empty($json['ERROR'])) {
            $error = is_array($json['ERROR']) ? (reset($json['ERROR']) ?: []) : ['MESSAGE' => (string) $json['ERROR']];
            $message = trim((string) ($error['MESSAGE'] ?? 'Unknown error'));
            $detail = trim($this->plain((string) ($error['FULL_DESCRIPTION'] ?? '')));

            throw new ProviderException($detail !== '' && $detail !== $message ? "{$message} ({$detail})" : $message);
        }

        $success = $json['SUCCESS'] ?? null;

        if (!is_array($success)) {
            throw new ProviderException('The provider returned an answer this system does not understand.');
        }

        $first = reset($success);

        return is_array($first) ? $first : $success;
    }

    private function endpoint(): string
    {
        $url = rtrim(trim($this->provider->url), '/');

        return str_ends_with(strtolower($url), '.php') ? $url : $url . '/api/index.php';
    }

    /** <PARAMETERS><ID>1</ID><IMEI>...</IMEI></PARAMETERS> */
    private function xml(array $params): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->appendChild($doc->createElement('PARAMETERS'));

        foreach ($params as $name => $value) {
            $node = $doc->createElement(strtoupper((string) $name));
            $node->appendChild($doc->createTextNode((string) $value));
            $root->appendChild($node);
        }

        return $doc->saveXML();
    }

    /** Custom fields a service asks for, whatever shape this server version uses. */
    /**
     * The name the provider gives its IMEI box, from CUSTOM {allow: 1, customname: "Lock Code", …}; null when
     * the box is a plain IMEI (not renamed, or renamed to IMEI / SN, which is the same thing).
     */
    private function imeiBoxName(array $service): ?string
    {
        $custom = $service['CUSTOM'] ?? $service['custom'] ?? null;
        if (!is_array($custom) || array_is_list($custom)) {
            return null;
        }

        $allow = strtolower((string) ($custom['allow'] ?? $custom['ALLOW'] ?? ''));
        $name = trim(strip_tags((string) ($custom['customname'] ?? $custom['CUSTOMNAME'] ?? '')));
        if (!in_array($allow, ['1', 'on', 'yes', 'true'], true) || $name === ''
            || preg_match('/^(imei|imei\s*\/\s*sn|sn|serial(\s*number)?)$/i', $name)) {
            return null;
        }

        return mb_substr($name, 0, 100);
    }

    private function customFields(array $service): array
    {
        $raw = $service['Requires.Custom'] ?? $service['CUSTOM'] ?? $service['custom'] ?? [];

        if (!is_array($raw)) {
            return [];
        }

        $fields = [];

        foreach ($raw as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['fieldname'] ?? $field['FIELDNAME'] ?? $field['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $required = $field['required'] ?? $field['REQUIRED'] ?? '';

            $fields[] = [
                'name' => mb_substr($name, 0, 100),
                'type' => strtolower((string) ($field['fieldtype'] ?? $field['FIELDTYPE'] ?? 'text')),
                'required' => in_array(strtolower((string) $required), ['1', 'on', 'yes', 'true'], true),
            ];
        }

        return $fields;
    }

    private function number(mixed $value): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    private function plain(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
