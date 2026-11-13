<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\ServiceCategory;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ServiceCategoryRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServiceCategoryService
{
    public function __construct(
        private ServiceCategoryRepository $categories,
        private ServiceRepository $services,
        private ActivityLogRepository $logs,
    ) {
    }

    public function all(): Collection
    {
        return $this->categories->all();
    }

    public function find(int $id): ServiceCategory
    {
        return $this->categories->findOrFail($id);
    }

    /** Saves the category and its input fields (IMEI, SN, Email, Lock Code...) together. */
    public function save(?ServiceCategory $category, array $data, int $adminId): ServiceCategory
    {
        $category = DB::transaction(function () use ($category, $data) {
            $payload = [
                'name' => $data['name'],
                'slug' => $this->categories->uniqueSlug($data['name'], $category?->id),
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ];

            $category = $category
                ? $this->categories->update($category, $payload)
                : $this->categories->create($payload);

            $removed = $this->categories->syncFields($category, $data['fields'] ?? []);

            // Services must not keep pointing at fields that no longer exist.
            $this->services->forgetFields($category->id, $removed);

            return $category;
        });

        $this->logs->log('category_saved', "Saved category {$category->name}", $category, [], $adminId);

        return $category;
    }

    public function toggle(ServiceCategory $category, int $adminId): ServiceCategory
    {
        $status = $category->status === 'active' ? 'inactive' : 'active';
        $this->categories->update($category, ['status' => $status]);

        $this->logs->log('category_status', "Set category {$category->name} to {$status}", $category, [], $adminId);

        return $category;
    }

    public function delete(ServiceCategory $category, int $adminId): void
    {
        if ($category->services()->exists()) {
            throw new WorkflowException('This category still has services. Move or delete them first, or deactivate the category instead.');
        }

        $name = $category->name;
        $this->categories->delete($category);

        $this->logs->log('category_deleted', "Deleted category {$name}", null, [], $adminId);
    }
}
