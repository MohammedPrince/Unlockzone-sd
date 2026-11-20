<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\CategoryField;
use App\Services\ServiceCategoryService;
use Illuminate\Http\Request;

class ServiceCategoryController extends Controller
{
    public function __construct(private ServiceCategoryService $categories)
    {
    }

    public function index()
    {
        return view('admin.categories.index', ['categories' => $this->categories->all()]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => null, 'types' => CategoryField::TYPES, 'usage' => []]);
    }

    public function store(CategoryRequest $request)
    {
        $this->categories->save(null, $request->validated(), $request->user()->id);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(int $id)
    {
        $category = $this->categories->find($id);

        // How many services ask for each field, shown next to its Remove button.
        $usage = [];
        foreach (\App\Models\Service::where('service_category_id', $category->id)->get(['id', 'configuration']) as $service) {
            foreach (array_unique($service->fieldIds()) as $fieldId) {
                $usage[$fieldId] = ($usage[$fieldId] ?? 0) + 1;
            }
        }

        return view('admin.categories.form', ['category' => $category, 'types' => CategoryField::TYPES, 'usage' => $usage]);
    }

    public function update(CategoryRequest $request, int $id)
    {
        $this->categories->save($this->categories->find($id), $request->validated(), $request->user()->id);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function toggle(Request $request, int $id)
    {
        $category = $this->categories->toggle($this->categories->find($id), $request->user()->id);

        return back()->with('success', __(':name is now :status.', ['name' => $category->name, 'status' => __($category->status)]));
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->categories->delete($this->categories->find($id), $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['category' => $e->getMessage()]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }
}
