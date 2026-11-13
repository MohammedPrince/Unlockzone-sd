<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Service;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ServiceCategoryRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ServiceManagementService
{
    public function __construct(
        private ServiceRepository $services,
        private ServiceCategoryRepository $categories,
        private ActivityLogRepository $logs,
    ) {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->services->paginate($filters);
    }

    public function find(int $id): Service
    {
        return $this->services->findOrFail($id);
    }

    public function catalog(?string $search = null): Collection
    {
        return $this->services->catalog($search);
    }

    /** Adds or removes a service from the customer's "use frequently" list. Returns true when it is now a favourite. */
    public function toggleFavorite(\App\Models\User $customer, int $serviceId): bool
    {
        $changes = $customer->favoriteServices()->toggle([$this->services->findOrFail($serviceId)->id]);

        return !empty($changes['attached']);
    }

    public function save(?Service $service, array $data, int $adminId): Service
    {
        $category = $this->categories->findOrFail((int) $data['service_category_id']);

        // Only fields that belong to the chosen category can be attached.
        $fieldIds = array_values(array_intersect(
            array_map('intval', $data['field_ids'] ?? []),
            $category->fields->pluck('id')->all()
        ));

        $oldPrice = $service?->price;
        $providerId = filled($data['api_provider_id'] ?? null) && filled($data['remote_service_id'] ?? null) ? (int) $data['api_provider_id'] : null;

        $payload = [
            'service_category_id' => $category->id,
            'name' => $data['name'],
            'slug' => $this->services->uniqueSlug($data['name'], $service?->id),
            'description' => \App\Support\HtmlSanitizer::clean($data['description'] ?? null),
            'price' => $data['price'],
            'status' => $data['status'],
            'delivery_time' => $data['delivery_time'] ?? null,
            'is_instant' => !empty($data['is_instant']),
            'configuration' => array_merge($service?->configuration ?? [], [
                'field_ids' => $fieldIds,
                'execution' => $providerId ? 'api' : 'manual',
            ]),
            'api_provider_id' => $providerId,
            'remote_service_id' => $providerId ? trim((string) $data['remote_service_id']) : null,
        ];

        $service = $service
            ? $this->services->update($service, $payload)
            : $this->services->create($payload);

        $this->logs->log('service_saved', "Saved service {$service->name}", $service, array_filter([
            'price_from' => $oldPrice !== null && (float) $oldPrice !== (float) $service->price ? $oldPrice : null,
            'price_to' => $service->price,
        ]), $adminId);

        return $service;
    }

    public function toggle(Service $service, int $adminId): Service
    {
        $status = $service->status === 'active' ? 'inactive' : 'active';
        $this->services->update($service, ['status' => $status]);

        $this->logs->log('service_status', "Set service {$service->name} to {$status}", $service, [], $adminId);

        return $service;
    }

    public function delete(Service $service, int $adminId): void
    {
        if ($this->services->hasRequests($service)) {
            throw new WorkflowException('This service has request history and cannot be deleted. Deactivate it instead.');
        }

        $name = $service->name;
        $this->services->delete($service);

        $this->logs->log('service_deleted', "Deleted service {$name}", null, [], $adminId);
    }
}
