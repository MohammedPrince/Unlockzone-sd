<?php

namespace App\Repositories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ServiceRepository
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Service::with(['category', 'provider'])
            ->withCount('requests')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('service_category_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('service_category_id')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): Service
    {
        return Service::with(['category.fields', 'provider'])->findOrFail($id);
    }

    /** A service a customer may order right now, or null. Never returns inactive items. */
    public function findAvailable(int $id): ?Service
    {
        return Service::with('category')
            ->where('status', 'active')
            ->whereHas('category', fn ($q) => $q->where('status', 'active'))
            ->find($id);
    }

    /** Active categories with their active services, for the customer catalog. */
    public function catalog(?string $search = null): Collection
    {
        return ServiceCategory::where('status', 'active')
            ->with(['services' => function ($q) use ($search) {
                $q->where('status', 'active')
                    ->when($search, fn ($w, $v) => $w->where('name', 'like', "%{$v}%"))
                    ->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => $category->services->isNotEmpty())
            ->values();
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $i = 1;

        while (Service::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function create(array $data): Service
    {
        return Service::create($data);
    }

    public function update(Service $service, array $data): Service
    {
        $service->update($data);

        return $service;
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }

    public function hasRequests(Service $service): bool
    {
        return $service->requests()->exists();
    }

    /** Services created in the last $days days that customers can order, newest first (for the ticker bar). */
    public function recentlyAdded(int $days, int $limit = 8): Collection
    {
        if ($days <= 0) {
            return collect();
        }

        return Service::where('status', 'active')
            ->whereHas('category', fn ($q) => $q->where('status', 'active'))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'price', 'created_at']);
    }

    /** Drops removed category fields from every service that still referenced them. */
    public function forgetFields(int $categoryId, array $fieldIds): void
    {
        if (!$fieldIds) {
            return;
        }

        foreach (Service::where('service_category_id', $categoryId)->get() as $service) {
            $config = $service->configuration ?? [];
            $config['field_ids'] = array_values(array_diff($service->fieldIds(), $fieldIds));
            $service->update(['configuration' => $config]);
        }
    }

    public function counts(): array
    {
        return [
            'all' => Service::count(),
            'active' => Service::where('status', 'active')->count(),
        ];
    }
}
