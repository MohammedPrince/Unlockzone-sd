<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\ApiClient;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Admin side of sharing our services: who may use our API, with which services, and suspending or removing them. */
class ApiClientService
{
    public function __construct(private ActivityLogRepository $logs)
    {
    }

    public function all(): Collection
    {
        return ApiClient::with('user:id,name,username,status')
            ->withCount(['services', 'requests', 'prices'])
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ApiClient
    {
        return ApiClient::with(['user', 'services:id', 'prices'])->findOrFail($id);
    }

    /** Customers that do not have API access yet (plus the one being edited). */
    public function customersForSelect(?ApiClient $current = null): Collection
    {
        return User::where('role', User::ROLE_CUSTOMER)
            ->where(fn ($q) => $q->whereDoesntHave('apiClient')->when($current, fn ($w) => $w->orWhere('id', $current->user_id)))
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'status']);
    }

    /** Every category with its services (archived ones excluded), for the "selected services" tick list. */
    public function serviceTree(): Collection
    {
        return ServiceCategory::with(['services' => fn ($q) => $q->orderBy('name')->select(['id', 'service_category_id', 'name', 'price', 'status'])])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->filter(fn ($c) => $c->services->isNotEmpty())
            ->values();
    }

    /** Creates or updates a client. Returns [client, new key or null]: a key is only made for a new client. */
    public function save(?ApiClient $client, array $data, int $adminId): array
    {
        return DB::transaction(function () use ($client, $data, $adminId) {
            $creating = $client === null;
            $client ??= new ApiClient();

            if ($creating) {
                $customer = User::where('role', User::ROLE_CUSTOMER)->findOrFail((int) $data['user_id']);

                if (ApiClient::where('user_id', $customer->id)->exists()) {
                    throw new WorkflowException('This customer already has API access.');
                }

                $client->user_id = $customer->id;
            }

            $client->fill([
                'access' => $data['access'],
                'price_adjust' => round((float) ($data['price_adjust'] ?? 0), 2),
                'status' => $data['status'],
                'allowed_ips' => $this->cleanIps($data['allowed_ips'] ?? null),
                'note' => $data['note'] ?? null,
            ]);

            $key = $creating ? $client->newKey() : null;
            $client->save();

            // The tick list is kept even when "all services" is chosen, so switching back restores it.
            if (array_key_exists('service_ids', $data)) {
                $client->services()->sync(array_map('intval', $data['service_ids'] ?? []));
            }

            // Fixed prices: an empty box means "use the percentage", so only filled boxes are kept.
            if (array_key_exists('prices', $data)) {
                $client->prices()->delete();
                $rows = collect($data['prices'] ?? [])
                    ->filter(fn ($price) => $price !== null && $price !== '')
                    ->map(fn ($price, $serviceId) => [
                        'api_client_id' => $client->id,
                        'service_id' => (int) $serviceId,
                        'price' => number_format(round((float) $price, 2), 2, '.', ''),
                    ])->values()->all();
                if ($rows) {
                    \App\Models\ApiClientPrice::insert($rows);
                }
            }

            $client->load('user');
            $this->logs->log($creating ? 'api_client_created' : 'api_client_saved', ($creating ? 'Gave API access to ' : 'Saved API access of ') . $client->user->username, $client, [
                'access' => $client->access, 'services' => $client->services()->count(),
                'price_adjust' => $client->price_adjust, 'fixed_prices' => $client->prices()->count(),
            ], $adminId);

            return [$client, $key];
        });
    }

    public function setStatus(ApiClient $client, string $status, int $adminId): void
    {
        $client->update(['status' => $status]);
        $this->logs->log('api_client_' . $status, ucfirst($status === 'active' ? 'resumed' : 'suspended') . " API access of {$client->user->username}", $client, [], $adminId);
    }

    /** A new key replaces the old one at once; the old key stops working. */
    public function regenerateKey(ApiClient $client, int $adminId): string
    {
        $key = $client->newKey();
        $client->save();
        $this->logs->log('api_client_key', "New API key for {$client->user->username}", $client, [], $adminId);

        return $key;
    }

    /** Removes the API access only. The customer account, its credit and its orders stay. */
    public function delete(ApiClient $client, int $adminId): void
    {
        $username = $client->user->username;
        $client->delete();
        $this->logs->log('api_client_deleted', "Removed API access of {$username}", null, [], $adminId);
    }

    private function cleanIps(?string $raw): ?string
    {
        $ips = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $raw)))));

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw new WorkflowException(__(':ip is not a valid IP address.', ['ip' => $ip]));
            }
        }

        return $ips ? implode("\n", $ips) : null;
    }
}
