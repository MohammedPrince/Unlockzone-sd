<?php

namespace App\Repositories;

use App\Models\CategoryField;
use App\Models\ServiceCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ServiceCategoryRepository
{
    public function all(): Collection
    {
        return ServiceCategory::with('fields')->withCount('services')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function findOrFail(int $id): ServiceCategory
    {
        return ServiceCategory::with('fields')->withCount('services')->findOrFail($id);
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 1;

        while (ServiceCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function create(array $data): ServiceCategory
    {
        return ServiceCategory::create($data);
    }

    public function update(ServiceCategory $category, array $data): ServiceCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(ServiceCategory $category): void
    {
        $category->delete();
    }

    /**
     * Replaces the category's field list with the submitted rows.
     * Rows carrying an id are updated in place so services keep pointing at them.
     * Returns the ids that were removed.
     */
    public function syncFields(ServiceCategory $category, array $rows): array
    {
        $keep = [];

        foreach (array_values($rows) as $index => $row) {
            $payload = [
                'label' => $row['label'],
                'input_type' => $row['input_type'],
                'placeholder' => $row['placeholder'] ?? null,
                'is_required' => !empty($row['is_required']),
                'sort_order' => $index,
            ];

            $field = !empty($row['id'])
                ? $category->fields()->whereKey($row['id'])->first()
                : null;

            if ($field) {
                $field->update($payload);
            } else {
                $field = $category->fields()->create($payload);
            }

            $keep[] = $field->id;
        }

        $removed = CategoryField::where('service_category_id', $category->id)->whereNotIn('id', $keep)->pluck('id')->all();
        CategoryField::whereIn('id', $removed)->delete();

        return $removed;
    }
}
