<?php

namespace App\Services;

use App\Exceptions\ProviderException;
use App\Exceptions\WorkflowException;
use App\Models\ApiProvider;
use App\Models\CategoryField;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Admin side of provider connections: the connection itself, its price list, and importing services. */
class ApiProviderService
{
    public function __construct(
        private ServiceRepository $services,
        private ActivityLogRepository $logs,
    ) {
    }

    public function all(): Collection
    {
        return ApiProvider::withCount(['remoteServices', 'services'])->orderBy('name')->get();
    }

    public function forSelect(): Collection
    {
        return ApiProvider::orderBy('name')->get(['id', 'name', 'status']);
    }

    public function find(int $id): ApiProvider
    {
        return ApiProvider::withCount(['remoteServices', 'services'])->findOrFail($id);
    }

    public function save(?ApiProvider $provider, array $data, int $adminId): ApiProvider
    {
        $provider ??= new ApiProvider(['type' => 'dhru']);
        $provider->fill([
            'name' => $data['name'],
            'type' => $data['type'] ?? $provider->type ?? 'dhru',
            'url' => rtrim(trim($data['url']), '/'),
            // The Reseller API signs in with the token alone.
            'username' => trim((string) ($data['username'] ?? '')),
            'status' => $data['status'],
        ]);

        // An empty key box on the edit form means "keep the saved key".
        if (filled($data['api_key'] ?? null)) {
            $provider->api_key = trim($data['api_key']);
        }

        $provider->save();
        $this->logs->log('provider_saved', "Saved API provider {$provider->name}", $provider, [], $adminId);

        return $provider;
    }

    public function delete(ApiProvider $provider, int $adminId): void
    {
        $name = $provider->name;
        // Linked services stay but go back to manual handling (the foreign key is set to null).
        $provider->delete();
        $this->logs->log('provider_deleted', "Deleted API provider {$name}", null, [], $adminId);
    }

    /** Signs in to the provider and stores its credit balance. Throws ProviderException with the reason. */
    public function test(ApiProvider $provider): array
    {
        try {
            $info = $provider->client()->accountInfo();
        } catch (ProviderException $e) {
            $provider->forceFill(['last_checked_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            throw $e;
        }

        $provider->forceFill([
            'balance' => $info['balance'],
            'currency' => $info['currency'] ? mb_substr((string) $info['currency'], 0, 10) : null,
            'last_checked_at' => now(),
            'last_error' => null,
        ])->save();

        return $info;
    }

    /** Downloads the provider's price list into provider_services. Returns the number of services. */
    public function syncServices(ApiProvider $provider, int $adminId): int
    {
        try {
            $list = $provider->client()->serviceList();
        } catch (ProviderException $e) {
            $provider->forceFill(['last_checked_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            throw $e;
        }

        DB::transaction(function () use ($provider, $list) {
            $seen = [];

            foreach ($list as $row) {
                $seen[] = $row['remote_id'];
                ProviderService::updateOrCreate(
                    ['api_provider_id' => $provider->id, 'remote_id' => $row['remote_id']],
                    $row
                );
            }

            // Services the provider removed from its list.
            $provider->remoteServices()->whereNotIn('remote_id', $seen ?: ['-'])->delete();
        });

        $provider->forceFill(['services_synced_at' => now(), 'last_checked_at' => now(), 'last_error' => null])->save();
        $this->logs->log('provider_synced', "Synced " . count($list) . " services from {$provider->name}", $provider, [], $adminId);

        return count($list);
    }

    public function remoteServices(ApiProvider $provider, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->remoteQuery($provider, $filters)->paginate($perPage)->withQueryString();
    }

    /** The provider's price list with the page's search and type filter. */
    private function remoteQuery(ApiProvider $provider, array $filters)
    {
        return $provider->remoteServices()
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('group_name', 'like', "%{$v}%")->orWhere('remote_id', $v)))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->orderBy('group_name')
            ->orderBy('name');
    }

    /** Remote ids of every service matching the filters (for "import all"). */
    public function matchingRemoteIds(ApiProvider $provider, array $filters, int $limit): array
    {
        return $this->remoteQuery($provider, $filters)->limit($limit)->pluck('remote_id')->all();
    }

    /** Remote ids that already have a local service, so the list can mark them. */
    public function importedIds(ApiProvider $provider): array
    {
        return Service::where('api_provider_id', $provider->id)->whereNotNull('remote_service_id')->pluck('id', 'remote_service_id')->all();
    }

    /**
     * Brings chosen lines of the provider's price list into the shop. Price = provider price + $margin percent.
     *
     * - $categoryId null: each service goes into a category named after the provider's group,
     *   created (active) when this site has none with that name.
     * - A service already linked to that provider line is skipped.
     * - A service with the same name already on the site (typed in by hand, imported from a text file or
     *   from another provider) is not duplicated: it is switched to this API service. It keeps its id,
     *   category, status, description and order history; its price, delivery time and inputs come from the API.
     *
     * Returns ['created' => int, 'linked' => int, 'skipped' => int, 'categories' => int].
     */
    public function import(ApiProvider $provider, array $remoteIds, ?int $categoryId, float $margin, string $status, int $adminId): array
    {
        $target = $categoryId ? ServiceCategory::with('fields')->findOrFail($categoryId) : null;
        $rows = $provider->remoteServices()->whereIn('remote_id', array_map('strval', $remoteIds))->orderBy('group_name')->orderBy('name')->get();

        if ($rows->isEmpty()) {
            throw new WorkflowException('Choose at least one service to import.');
        }

        return DB::transaction(function () use ($provider, $rows, $target, $margin, $status, $adminId) {
            $linked = $this->importedIds($provider);
            // Every service on the site by name (archived ones excluded), to avoid duplicates.
            $byName = Service::get(['id', 'name', 'service_category_id', 'api_provider_id', 'remote_service_id', 'configuration'])
                ->keyBy(fn ($s) => ServiceImportService::key($s->name));
            $categories = [];
            $result = ['created' => 0, 'linked' => 0, 'skipped' => 0, 'categories' => 0];

            foreach ($rows as $row) {
                // Names are stored cut to 150 characters, so compare the cut name (very long provider names).
                $name = mb_substr($row->name, 0, 150);
                $key = ServiceImportService::key($name);
                $price = number_format(round((float) $row->price * (1 + $margin / 100), 2), 2, '.', '');
                $delivery = $row->delivery_time ? mb_substr($row->delivery_time, 0, 60) : null;

                if (isset($linked[$row->remote_id])) {
                    $result['skipped']++;
                    continue;
                }

                if ($same = $byName->get($key)) {
                    // The provider lists the same name twice: the first one was taken in this import.
                    if ((int) $same->api_provider_id === (int) $provider->id && isset($linked[(string) $same->remote_service_id])) {
                        $result['skipped']++;
                        continue;
                    }

                    $category = ServiceCategory::with('fields')->findOrFail($same->service_category_id);
                    $same->update([
                        'price' => $price,
                        'delivery_time' => $delivery,
                        'configuration' => array_merge($same->configuration ?? [], [
                            'field_ids' => $this->fieldIdsFor($category, $row),
                            'execution' => 'api',
                        ]),
                        'api_provider_id' => $provider->id,
                        'remote_service_id' => $row->remote_id,
                    ]);
                    $linked[$row->remote_id] = $same->id;
                    $result['linked']++;
                    continue;
                }

                $category = $target ?? ($categories[ServiceImportService::key($this->groupName($row, $provider))]
                    ??= $this->findOrCreateCategory($this->groupName($row, $provider), $result['categories']));

                $service = $this->services->create([
                    'service_category_id' => $category->id,
                    'name' => $name,
                    'slug' => $this->services->uniqueSlug($name),
                    'description' => null,
                    'price' => $price,
                    'status' => $status,
                    'delivery_time' => $delivery,
                    'configuration' => ['field_ids' => $this->fieldIdsFor($category, $row), 'execution' => 'api'],
                    'api_provider_id' => $provider->id,
                    'remote_service_id' => $row->remote_id,
                ]);
                $linked[$row->remote_id] = $service->id;
                $byName->put($key, $service);
                $result['created']++;
            }

            $this->logs->log('provider_import', "Imported from {$provider->name}: {$result['created']} new, {$result['linked']} existing switched to the API, {$result['categories']} new categories", $provider, [
                'margin' => $margin, 'skipped' => $result['skipped'], 'category' => $target?->name ?? 'provider groups',
            ], $adminId);

            return $result;
        });
    }

    private function groupName(ProviderService $row, ApiProvider $provider): string
    {
        $name = trim((string) $row->group_name);

        return mb_substr($name !== '' ? $name : $provider->name, 0, 150);
    }

    /** The site's category with this name (ignoring case and spaces), or a new active one. */
    private function findOrCreateCategory(string $name, int &$created): ServiceCategory
    {
        $category = ServiceCategory::with('fields')->get()->first(fn ($c) => ServiceImportService::key($c->name) === ServiceImportService::key($name));

        if ($category) {
            return $category;
        }

        $created++;
        $repo = app(\App\Repositories\ServiceCategoryRepository::class);

        return $repo->create([
            'name' => $name,
            'slug' => $repo->uniqueSlug($name),
            'description' => null,
            'status' => 'active',
            'sort_order' => 0,
        ])->load('fields');
    }

    /** Finds (or adds to the category) the input fields a provider service needs. */
    private function fieldIdsFor(ServiceCategory $category, ProviderService $row): array
    {
        $ids = [];
        $category->load('fields');

        if ($row->needs_imei) {
            $field = $category->fields->firstWhere('input_type', 'imei')
                ?? $category->fields()->create(['label' => 'IMEI', 'input_type' => 'imei', 'placeholder' => '15-digit IMEI', 'is_required' => true, 'sort_order' => 0]);
            $ids[] = $field->id;
        }

        foreach ($row->fields ?? [] as $custom) {
            $name = (string) ($custom['name'] ?? '');

            // The provider's IMEI / serial field is the IMEI box above, not an extra one.
            if ($name === '' || (!empty($custom['imei']) && $row->needs_imei)) {
                continue;
            }

            $field = $category->fields->first(fn (CategoryField $f) => mb_strtolower($f->label) === mb_strtolower($name))
                ?? $category->fields()->create([
                    'label' => $name,
                    'input_type' => match (true) {
                        str_contains(mb_strtolower($name), 'mail') => 'email',
                        Service::isQuantityLabel($name) => 'number', // price x quantity on the order form
                        default => 'text',
                    },
                    'placeholder' => null,
                    'is_required' => (bool) ($custom['required'] ?? true),
                    'sort_order' => $category->fields->count() + count($ids),
                ]);
            $ids[] = $field->id;
            $category->load('fields');
        }

        return array_values(array_unique($ids));
    }
}
