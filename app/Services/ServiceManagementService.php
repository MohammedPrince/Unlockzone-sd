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

    /**
     * Moves the services into another category. Each service keeps asking for the same inputs: its fields are
     * matched in the new category by input type and label, and copied there when the category has no such field.
     * Returns how many services were moved (ones already in that category are left alone).
     */
    public function moveToCategory(array $serviceIds, int $categoryId, int $adminId): int
    {
        $target = $this->categories->findOrFail($categoryId);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($serviceIds, $target, $adminId) {
            $services = Service::whereIn('id', array_map('intval', $serviceIds))
                ->where('service_category_id', '!=', $target->id)
                ->lockForUpdate()
                ->get();

            $targetFields = $target->fields()->get();
            $moved = [];

            foreach ($services as $service) {
                $fieldIds = $service->inputFields()->map(function ($field) use ($target, &$targetFields) {
                    $match = $targetFields->first(fn ($f) => $f->input_type === $field->input_type
                        && mb_strtolower(trim($f->label)) === mb_strtolower(trim($field->label)));

                    if (!$match) {
                        $match = $target->fields()->create([
                            'label' => $field->label,
                            'input_type' => $field->input_type,
                            'placeholder' => $field->placeholder,
                            'is_required' => $field->is_required,
                            'sort_order' => $targetFields->count(),
                        ]);
                        $targetFields->push($match);
                    }

                    return $match->id;
                })->unique()->values()->all();

                $this->services->update($service, [
                    'service_category_id' => $target->id,
                    'configuration' => array_merge($service->configuration ?? [], ['field_ids' => $fieldIds]),
                ]);
                $moved[] = $service->name;
            }

            if ($moved) {
                $this->logs->log('services_moved', 'Moved ' . count($moved) . " services to category {$target->name}", $target, [
                    'services' => array_slice($moved, 0, 50),
                ], $adminId);
            }

            return count($moved);
        });
    }

    /** Returns true when the service was archived (it had finished orders) rather than removed. */
    public function delete(Service $service, int $adminId): bool
    {
        // Refused while a customer order is unfinished. With finished orders the service is archived
        // (customers still see those orders, greyed out); without orders it is removed for good.
        $open = $service->requests()->whereIn('status', ['pending', 'processing'])->count();
        if ($open) {
            throw new WorkflowException(__('This service has :count unfinished customer orders. Complete or cancel them first, then delete it.', ['count' => $open]));
        }

        $name = $service->name;
        $archived = $this->services->hasRequests($service);

        if ($archived) {
            $service->delete(); // archive
        } else {
            $this->services->delete($service);
        }

        $this->logs->log('service_deleted', ($archived ? 'Archived' : 'Deleted') . " service {$name}", $archived ? $service : null, [], $adminId);

        return $archived;
    }
}
