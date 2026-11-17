<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\ApiClient;
use App\Models\CategoryField;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Repositories\CreditRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Our own DHRU Fusion API: the same protocol DhruFusionClient speaks, answered by this server.
 * Another server signs in with one of our customers' username and the API key we gave them,
 * sees the services shared with it, and orders them with that customer's credit.
 *
 * Actions: accountinfo, imeiservicelist, placeimeiorder, getimeiorder.
 */
class DhruApiServer
{
    public const VERSION = '6.1';

    public const CURRENCY = 'USD';

    public function __construct(
        private ServiceRequestService $requests,
        private ProviderOrderService $providerOrders,
        private ServiceRepository $services,
        private CreditRepository $credits,
    ) {
    }

    /** Answers one API call. Always returns the JSON body; it never throws. */
    public function handle(array $input, ?string $ip): array
    {
        try {
            $client = $this->authenticate((string) ($input['username'] ?? ''), (string) ($input['apiaccesskey'] ?? ''), $ip);
            $params = $this->parameters($input);

            return match (strtolower(trim((string) ($input['action'] ?? '')))) {
                'accountinfo' => $this->accountInfo($client),
                'imeiservicelist', 'serverservicelist', 'getallservices' => $this->serviceList($client),
                'placeimeiorder' => $this->placeOrder($client, $params),
                'getimeiorder' => $this->orderStatus($client, $params),
                default => $this->error('Invalid Action'),
            };
        } catch (WorkflowException $e) {
            return $this->error($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->error('Internal error, please try again later.');
        }
    }

    private function authenticate(string $username, string $key, ?string $ip): ApiClient
    {
        $username = trim($username);

        if ($username === '' || trim($key) === '') {
            throw new WorkflowException('Authentication Failed');
        }

        $client = ApiClient::with('user')->where('api_key_hash', ApiClient::hashKey($key))->first();
        $user = $client?->user;

        if (!$client || !$user || (strcasecmp($user->username, $username) !== 0 && strcasecmp((string) $user->email, $username) !== 0)) {
            throw new WorkflowException('Authentication Failed');
        }

        if (!$client->isActive()) {
            throw new WorkflowException('API access is suspended. Please contact the server administrator.');
        }

        if (!$user->isActive()) {
            throw new WorkflowException('Your account is not active.');
        }

        if (!$client->allowsIp($ip)) {
            throw new WorkflowException("IP address {$ip} is not allowed to use this API key.");
        }

        // Once a minute is enough to show "last used" in the admin list.
        if ($client->last_used_at === null || $client->last_used_at->lt(now()->subMinute()) || $client->last_ip !== $ip) {
            $client->forceFill(['last_used_at' => now(), 'last_ip' => $ip])->saveQuietly();
        }

        return $client;
    }

    /** The PARAMETERS XML (<PARAMETERS><ID>1</ID>…</PARAMETERS>) as [NAME => value]; plain POST fields also work. */
    private function parameters(array $input): array
    {
        $out = [];
        $raw = trim((string) ($input['parameters'] ?? ''));

        if ($raw !== '' && str_starts_with(ltrim($raw, "\xEF\xBB\xBF \t\r\n"), '<')) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
            libxml_use_internal_errors($previous);

            if ($xml !== false) {
                foreach ($xml->children() as $node) {
                    $out[strtoupper($node->getName())] = trim((string) $node);
                }
            }
        }

        foreach (['ID', 'IMEI', 'CUSTOMFIELD', 'QNT', 'SERVICEID'] as $name) {
            if (!isset($out[$name]) && isset($input[$name]) && is_scalar($input[$name])) {
                $out[$name] = trim((string) $input[$name]);
            }
        }

        return $out;
    }

    private function accountInfo(ApiClient $client): array
    {
        $balance = $this->credits->balance($client->user_id);

        return $this->success([
            'message' => 'Your Accout Info',
            'AccoutInfo' => [
                'credit' => number_format((float) $balance, 2, '.', '') . ' ' . self::CURRENCY,
                'creditraw' => number_format((float) $balance, 2, '.', ''),
                'mail' => $client->user->email,
                'currency' => self::CURRENCY,
            ],
        ]);
    }

    /** The services this client may order right now, grouped by category. */
    private function serviceList(ApiClient $client): array
    {
        $list = [];

        foreach ($this->visibleServices($client)->groupBy('service_category_id') as $services) {
            $category = $services->first()->category;
            $group = ['GROUPNAME' => $category->name, 'GROUPTYPE' => 'IMEI', 'SERVICES' => []];

            foreach ($services as $service) {
                $fields = $service->inputFields();
                $imei = $fields->firstWhere('input_type', 'imei');
                $custom = $fields->reject(fn (CategoryField $f) => $imei && $f->is($imei))->map(fn (CategoryField $f) => [
                    'type' => 'serviceimei',
                    'fieldname' => $f->label,
                    'fieldtype' => $f->input_type === 'number' ? 'number' : 'text',
                    'description' => $f->hint(),
                    'fieldoptions' => '',
                    'required' => $f->is_required ? 'on' : '',
                ])->values()->all();

                $group['SERVICES'][$service->id] = [
                    'SERVICEID' => $service->id,
                    'SERVICETYPE' => $imei ? 'IMEI' : 'SERVER',
                    'SERVICENAME' => $service->name,
                    'CREDIT' => number_format((float) $service->price, 2, '.', ''),
                    'TIME' => (string) $service->delivery_time,
                    'INFO' => Str::limit(trim(strip_tags((string) $service->description)), 500, ''),
                    'MINQNT' => $service->quantityField($fields) ? '1' : '',
                    'MAXQNT' => $service->quantityField($fields) ? (string) Service::MAX_QUANTITY : '',
                    'Requires.Custom' => $custom,
                ];
            }

            $list[$category->name] = $group;
        }

        return $this->success(['MESSAGE' => 'IMEI Service List', 'LIST' => $list]);
    }

    private function visibleServices(ApiClient $client): Collection
    {
        $user = $client->user;

        if ($user->services_suspended) {
            return collect();
        }

        $blocked = $user->blockedServices()->pluck('services.id')->all();

        return Service::with('category')
            ->where('status', 'active')
            ->whereHas('category', fn ($q) => $q->where('status', 'active'))
            ->when(!$client->sharesAll(), fn ($q) => $q->whereIn('id', $client->services()->pluck('services.id')))
            ->whereNotIn('id', $blocked ?: [0])
            ->orderBy('service_category_id')
            ->orderBy('name')
            ->get();
    }

    private function placeOrder(ApiClient $client, array $params): array
    {
        $serviceId = (int) ($params['ID'] ?? $params['SERVICEID'] ?? 0);
        $service = $serviceId ? $this->services->findAvailable($serviceId) : null;

        if (!$service || (!$client->sharesAll() && !$client->services()->whereKey($serviceId)->exists())) {
            throw new WorkflowException('Invalid Service ID or the service is not available for your account.');
        }

        $inputs = $this->orderInputs($service, $params);

        try {
            // Each API call is a new order; DHRU clients do not send a key of their own.
            $request = $this->requests->submit($client->user, $service->id, $inputs, 'api-' . Str::uuid(), $client->id);
        } catch (ValidationException $e) {
            throw new WorkflowException(collect($e->errors())->flatten()->first() ?: 'Invalid order details.');
        }

        // A service that is itself linked to a provider is forwarded at once, as for a customer order.
        $this->providerOrders->dispatch($request);

        return $this->success(['MESSAGE' => 'Order received', 'REFERENCEID' => (string) $request->id]);
    }

    /**
     * Maps IMEI, CUSTOMFIELD (base64 JSON of {field name: value}) and QNT onto the service's own fields.
     * Field names are matched without caring about letter case. Returns [field id => value].
     */
    private function orderInputs(Service $service, array $params): array
    {
        $custom = [];
        $decoded = json_decode((string) base64_decode((string) ($params['CUSTOMFIELD'] ?? ''), true), true);

        foreach (is_array($decoded) ? $decoded : [] as $name => $value) {
            if (is_scalar($value)) {
                $custom[mb_strtolower(trim((string) $name))] = trim((string) $value);
            }
        }

        $fields = $service->inputFields();
        $quantityField = $service->quantityField($fields);
        $imei = trim((string) ($params['IMEI'] ?? ''));
        $imeiUsed = false;
        $inputs = [];

        foreach ($fields as $field) {
            $value = $custom[mb_strtolower($field->label)] ?? null;

            if ($field->is($quantityField)) {
                $value = filled($params['QNT'] ?? null) ? $params['QNT'] : ($value ?? '1');
            } elseif (($value === null || $value === '') && !$imeiUsed && $imei !== '' && in_array($field->input_type, ['imei', 'sn'], true)) {
                $value = $imei;
                $imeiUsed = true;
            }

            $inputs[$field->id] = $value;
        }

        // A service with a single box: whatever came as IMEI fills it.
        if (!$imeiUsed && $imei !== '' && $fields->count() === 1 && !filled($inputs[$fields->first()->id] ?? null)) {
            $inputs[$fields->first()->id] = $imei;
        }

        return $inputs;
    }

    private function orderStatus(ApiClient $client, array $params): array
    {
        $request = ServiceRequest::where('user_id', $client->user_id)->find((int) ($params['ID'] ?? 0));

        if (!$request) {
            throw new WorkflowException('Invalid Order ID');
        }

        // A chained provider order: fetch its news first (at most every 20 seconds).
        $this->providerOrders->refreshIfStale($request);
        $request->refresh();

        // 0 new, 1 in process, 3 rejected, 4 success.
        [$status, $code, $comments] = match ($request->status) {
            'completed' => ['4', (string) ($request->response_data['result'] ?? ''), ''],
            'failed', 'cancelled' => ['3', '', (string) $request->error_message],
            'processing' => ['1', '', ''],
            default => ['0', '', ''],
        };

        return $this->success([
            'MESSAGE' => 'Order status',
            'STATUS' => $status,
            // DHRU servers send the code as HTML: line breaks become <br>.
            'CODE' => $this->html($code !== '' ? $code : ($status === '3' ? $comments : '')),
            'COMMENTS' => $comments,
            'IMEI' => (string) (collect($request->request_data ?? [])->firstWhere('type', 'imei')['value'] ?? ''),
        ]);
    }

    private function html(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], '<br>', e($text));
    }

    private function success(array $payload): array
    {
        return ['SUCCESS' => [$payload], 'apiversion' => self::VERSION];
    }

    private function error(string $message): array
    {
        return ['ERROR' => [['MESSAGE' => $message]], 'apiversion' => self::VERSION];
    }
}
