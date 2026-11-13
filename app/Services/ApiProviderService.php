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
use App\Support\DhruFusionClient;
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
            'url' => rtrim(trim($data['url']), '/'),
            'username' => trim($data['username']),
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
            $info = (new DhruFusionClient($provider))->accountInfo();
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
            $list = (new DhruFusionClient($provider))->serviceList();
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
        return $provider->remoteServices()
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('group_name', 'like', "%{$v}%")->orWhere('remote_id', $v)))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->orderBy('group_name')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Remote ids that already have a local service, so the list can mark them. */
    public function importedIds(ApiProvider $provider): array
    {
        return Service::where('api_provider_id', $provider->id)->whereNotNull('remote_service_id')->pluck('id', 'remote_service_id')->all();
    }

    /**
     * Creates local services from chosen lines of the provider's list.
     * The selling price is the provider's price plus $margin percent. Returns how many were created.
     */
    public function import(ApiProvider $provider, array $remoteIds, int $categoryId, float $margin, string $status, int $adminId): int
    {
        $category = ServiceCategory::with('fields')->findOrFail($categoryId);
        $existing = $this->importedIds($provider);
        $created = 0;

        $rows = $provider->remoteServices()->whereIn('remote_id', array_map('strval', $remoteIds))->get();

        if ($rows->isEmpty()) {
            throw new WorkflowException('Choose at least one service to import.');
        }

        foreach ($rows as $row) {
            if (isset($existing[$row->remote_id])) {
                continue;
            }

            DB::transaction(function () use ($provider, $row, $category, $margin, $status) {
                $this->services->create([
                    'service_category_id' => $category->id,
                    'name' => mb_substr($row->name, 0, 150),
                    'slug' => $this->services->uniqueSlug($row->name),
                    'description' => null,
                    'price' => number_format(round((float) $row->price * (1 + $margin / 100), 2), 2, '.', ''),
                    'status' => $status,
                    'delivery_time' => $row->delivery_time ? mb_substr($row->delivery_time, 0, 60) : null,
                    'configuration' => ['field_ids' => $this->fieldIdsFor($category, $row), 'execution' => 'api'],
                    'api_provider_id' => $provider->id,
                    'remote_service_id' => $row->remote_id,
                ]);
            });

            $created++;
        }

        $this->logs->log('provider_import', "Imported {$created} services from {$provider->name} into {$category->name}", $provider, ['margin' => $margin], $adminId);

        return $created;
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

            if ($name === '') {
                continue;
            }

            $field = $category->fields->first(fn (CategoryField $f) => mb_strtolower($f->label) === mb_strtolower($name))
                ?? $category->fields()->create([
                    'label' => $name,
                    'input_type' => str_contains(mb_strtolower($name), 'mail') ? 'email' : 'text',
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
