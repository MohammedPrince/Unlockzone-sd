<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\ApiProvider;
use App\Models\CategoryField;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ServiceCategoryRepository;
use App\Repositories\ServiceRepository;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Settings > Export / Import all categories and services, to copy the catalog from one site
 * (e.g. DEV) to another (e.g. PROD).
 *
 * The export is a JSON file: every category (not archived) with its input fields, and every service in it.
 * Database ids are not carried over: on import categories and services are matched by name (without case and
 * extra spaces), category fields by input type + label, and a service's connected API provider by the
 * provider's name or URL on the target site. Import adds what is missing and, when asked, updates what exists.
 * It never deletes anything.
 */
class CatalogTransferService
{
    public const FORMAT = 'dhru-server-catalog';

    public const VERSION = 1;

    public function __construct(
        private ServiceRepository $services,
        private ServiceCategoryRepository $categories,
        private ActivityLogRepository $logs,
    ) {
    }

    /** The whole catalog as an array, ready to be written as JSON. */
    public function export(): array
    {
        $providers = ApiProvider::get(['id', 'name', 'url'])->keyBy('id');

        /** @var EloquentCollection<int, ServiceCategory> $all */
        $all = ServiceCategory::with(['fields', 'services' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $categories = $all
            ->map(function (ServiceCategory $category) use ($providers) {
                $fieldKeys = $category->fields->pluck('id')->flip(); // field id => position in the export

                return [
                    'name' => $category->name,
                    'description' => $category->description,
                    'status' => $category->status,
                    'sort_order' => (int) $category->sort_order,
                    'fields' => $category->fields->map(fn ($f) => [
                        'label' => $f->label,
                        'input_type' => $f->input_type,
                        'placeholder' => $f->placeholder,
                        'is_required' => (bool) $f->is_required,
                        'sort_order' => (int) $f->sort_order,
                    ])->values()->all(),
                    'services' => $category->services->map(function (Service $s) use ($fieldKeys, $providers) {
                        $provider = $s->usesProvider() ? $providers->get($s->api_provider_id) : null;

                        return [
                            'name' => $s->name,
                            'description' => $s->description,
                            'price' => number_format((float) $s->price, 2, '.', ''),
                            'vip_price' => $s->vip_price !== null ? number_format((float) $s->vip_price, 2, '.', '') : null,
                            'status' => $s->status,
                            'delivery_time' => $s->delivery_time,
                            'is_instant' => (bool) $s->is_instant,
                            // Positions in this category's "fields" list.
                            'fields' => collect($s->fieldIds())->filter(fn ($id) => $fieldKeys->has($id))
                                ->map(fn ($id) => $fieldKeys[$id])->values()->all(),
                            'provider' => $provider ? [
                                'name' => $provider->name,
                                'url' => $provider->url,
                                'remote_service_id' => $s->remote_service_id,
                            ] : null,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'site' => config('app.url'),
            'counts' => [
                'categories' => count($categories),
                'services' => array_sum(array_map(fn ($c) => count($c['services']), $categories)),
            ],
            'categories' => $categories,
        ];
    }

    /** Reads and checks an export file. Returns the list of categories. */
    public function parse(string $json): array
    {
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        $data = json_decode($json, true);

        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT || !is_array($data['categories'] ?? null)) {
            throw new WorkflowException('This is not a catalog export file. Use a file made with "Download export file" in Settings.');
        }

        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            throw new WorkflowException('This file was made by a newer version of the site. Update this site first.');
        }

        $categories = [];

        foreach ($data['categories'] as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '' || !is_array($c['services'] ?? []) || !is_array($c['fields'] ?? [])) {
                throw new WorkflowException('The file is damaged: a category has no name or a broken list.');
            }

            // Kept in the file's order (services point at fields by position), so none are dropped here.
            $fields = array_values(array_map(fn ($f) => [
                'label' => mb_substr(trim((string) ($f['label'] ?? '')), 0, 100) ?: 'Field',
                'input_type' => is_string($f['input_type'] ?? null) && isset(CategoryField::TYPES[$f['input_type']]) ? $f['input_type'] : 'text',
                'placeholder' => filled($f['placeholder'] ?? null) ? mb_substr((string) $f['placeholder'], 0, 150) : null,
                'is_required' => (bool) ($f['is_required'] ?? true),
                'sort_order' => max(0, (int) ($f['sort_order'] ?? 0)),
            ], $c['fields'] ?? []));

            $services = [];
            foreach ($c['services'] ?? [] as $s) {
                $sName = trim((string) ($s['name'] ?? ''));
                if ($sName === '' || !is_numeric($s['price'] ?? null)) {
                    throw new WorkflowException('The file is damaged: a service has no name or price.');
                }

                $services[] = [
                    'name' => mb_substr($sName, 0, 150),
                    'description' => filled($s['description'] ?? null) ? HtmlSanitizer::clean((string) $s['description']) : null,
                    'price' => number_format(max(0, (float) $s['price']), 2, '.', ''),
                    'vip_price' => is_numeric($s['vip_price'] ?? null) ? number_format(max(0, (float) $s['vip_price']), 2, '.', '') : null,
                    'status' => ($s['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
                    'delivery_time' => filled($s['delivery_time'] ?? null) ? mb_substr((string) $s['delivery_time'], 0, 60) : null,
                    'is_instant' => (bool) ($s['is_instant'] ?? false),
                    'fields' => array_values(array_filter(array_map('intval', (array) ($s['fields'] ?? [])), fn ($i) => $i >= 0 && $i < count($fields))),
                    'provider' => is_array($s['provider'] ?? null) && filled($s['provider']['remote_service_id'] ?? null) ? [
                        'name' => (string) ($s['provider']['name'] ?? ''),
                        'url' => (string) ($s['provider']['url'] ?? ''),
                        'remote_service_id' => mb_substr(trim((string) $s['provider']['remote_service_id']), 0, 40),
                    ] : null,
                ];
            }

            $categories[] = [
                'name' => mb_substr($name, 0, 150),
                'description' => filled($c['description'] ?? null) ? (string) $c['description'] : null,
                'status' => ($c['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
                'sort_order' => max(0, (int) ($c['sort_order'] ?? 0)),
                'fields' => $fields,
                'services' => $services,
            ];
        }

        if (!$categories) {
            throw new WorkflowException('The file has no categories.');
        }

        return $categories;
    }

    /**
     * Adds the file's categories, fields and services to this site. With $update, ones that already exist
     * (same name) are changed to match the file; without it they are left as they are.
     * Returns counts: categories_created, categories_updated, services_created, services_updated,
     * services_skipped, providers_missing (services saved as manual because their provider is not on this site).
     */
    public function import(array $categories, bool $update, int $adminId): array
    {
        $lock = Cache::lock('service-import', 300);

        try {
            $lock->block(ServiceImportService::$lockWaitSeconds);
        } catch (LockTimeoutException) {
            throw new WorkflowException('Another import is running. Try again in a moment.');
        }

        try {
            return DB::transaction(fn () => $this->apply($categories, $update, $adminId));
        } finally {
            $lock->release();
        }
    }

    private function apply(array $rows, bool $update, int $adminId): array
    {
        $result = array_fill_keys(['categories_created', 'categories_updated', 'services_created', 'services_updated', 'services_skipped', 'providers_missing'], 0);

        /** @var EloquentCollection<string, ServiceCategory> $existingCategories */
        $existingCategories = ServiceCategory::get()->keyBy(fn ($c) => ServiceImportService::key($c->name));
        /** @var EloquentCollection<string, Service> $existingServices */
        $existingServices = Service::get()->keyBy(fn ($s) => ServiceImportService::key($s->name));
        $providers = ApiProvider::get(['id', 'name', 'url']);
        $seen = [];

        foreach ($rows as $row) {
            $category = $existingCategories->get(ServiceImportService::key($row['name']));
            $payload = ['description' => $row['description'], 'status' => $row['status'], 'sort_order' => $row['sort_order']];

            if (!$category) {
                $category = $this->categories->create($payload + [
                    'name' => $row['name'],
                    'slug' => $this->categories->uniqueSlug($row['name']),
                ]);
                $existingCategories->put(ServiceImportService::key($row['name']), $category);
                $result['categories_created']++;
            } elseif ($update) {
                $this->categories->update($category, $payload);
                $result['categories_updated']++;
            }

            $fieldIds = $this->syncFields($category, $row['fields'], $update);

            foreach ($row['services'] as $s) {
                $key = ServiceImportService::key($s['name']);

                if (isset($seen[$key])) {
                    continue; // the same name twice in the file: the first one wins
                }
                $seen[$key] = true;

                $service = $existingServices->get($key);
                if ($service && !$update) {
                    $result['services_skipped']++;
                    continue;
                }

                $providerId = null;
                if ($s['provider']) {
                    $providerId = $this->findProvider($providers, $s['provider'])?->id;
                    if (!$providerId) {
                        $result['providers_missing']++;
                    }
                }

                $data = [
                    'service_category_id' => $category->id,
                    'description' => $s['description'],
                    'price' => $s['price'],
                    'status' => $s['status'],
                    'delivery_time' => $s['delivery_time'],
                    'is_instant' => $s['is_instant'],
                    'configuration' => array_merge($service?->configuration ?? [], [
                        'field_ids' => array_values(array_unique(array_map(fn ($i) => $fieldIds[$i], $s['fields']))),
                        'execution' => $providerId ? 'api' : 'manual',
                    ]),
                    'api_provider_id' => $providerId,
                    'remote_service_id' => $providerId ? $s['provider']['remote_service_id'] : null,
                ];

                if ($service) {
                    $this->services->update($service, $data);
                    $result['services_updated']++;
                } else {
                    $service = $this->services->create($data + [
                        'name' => $s['name'],
                        'slug' => $this->services->uniqueSlug($s['name']),
                    ]);
                    $existingServices->put($key, $service);
                    $result['services_created']++;
                }

                // vip_price is not mass assignable (it is managed on the VIP prices page).
                if ((string) $service->vip_price !== (string) $s['vip_price']) {
                    Service::whereKey($service->id)->update(['vip_price' => $s['vip_price']]);
                }
            }
        }

        $this->logs->log('catalog_imported', "Imported the catalog: {$result['categories_created']} new categories, {$result['services_created']} new services, {$result['services_updated']} updated", null, $result + ['update' => $update], $adminId);

        return $result;
    }

    /**
     * Finds or adds each of the file's fields in the category (matched by input type and label).
     * Returns the category field id for each position in the file's list. Fields are never removed.
     */
    private function syncFields(ServiceCategory $category, array $fields, bool $update): array
    {
        $existing = $category->fields()->get();
        $ids = [];

        foreach ($fields as $i => $f) {
            $match = $existing->first(fn ($e) => $e->input_type === $f['input_type']
                && mb_strtolower(trim($e->label)) === mb_strtolower($f['label']));

            if (!$match) {
                $match = $category->fields()->create($f);
                $existing->push($match);
            } elseif ($update) {
                $match->update(['placeholder' => $f['placeholder'], 'is_required' => $f['is_required'], 'sort_order' => $f['sort_order']]);
            }

            $ids[$i] = $match->id;
        }

        return $ids;
    }

    /** The provider on this site with the same URL (host and path) or, failing that, the same name. */
    private function findProvider($providers, array $wanted): ?ApiProvider
    {
        $norm = fn (string $url) => rtrim(mb_strtolower(preg_replace('#^https?://(www\.)?#i', '', trim($url))), '/');

        return ($wanted['url'] !== '' ? $providers->first(fn ($p) => $norm($p->url) === $norm($wanted['url'])) : null)
            ?? ($wanted['name'] !== '' ? $providers->first(fn ($p) => ServiceImportService::key($p->name) === ServiceImportService::key($wanted['name'])) : null);
    }
}
