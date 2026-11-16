<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveServiceRequest;
use App\Services\ApiProviderService;
use App\Services\ServiceCategoryService;
use App\Services\ServiceManagementService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceManagementService $services,
        private ServiceCategoryService $categories,
        private ApiProviderService $providers,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'category', 'status']);

        return view('admin.services.index', [
            'services' => $this->services->paginate($filters),
            'categories' => $this->categories->all(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.services.form', [
            'service' => null,
            'categories' => $this->categories->all(),
            'preselect' => $request->integer('category') ?: null,
            'providers' => $this->providers->forSelect(),
        ]);
    }

    public function store(SaveServiceRequest $request)
    {
        $this->services->save(null, $request->validated(), $request->user()->id);

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(int $id)
    {
        return view('admin.services.form', [
            'service' => $this->services->find($id),
            'categories' => $this->categories->all(),
            'preselect' => null,
            'providers' => $this->providers->forSelect(),
        ]);
    }

    public function update(SaveServiceRequest $request, int $id)
    {
        $this->services->save($this->services->find($id), $request->validated(), $request->user()->id);

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function toggle(Request $request, int $id)
    {
        $service = $this->services->toggle($this->services->find($id), $request->user()->id);

        return back()->with('success', __(':name is now :status.', ['name' => $service->name, 'status' => __($service->status)]));
    }

    /** Moves the ticked services into the chosen category. */
    public function move(Request $request)
    {
        $input = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'exists:services,id'],
            'target_category_id' => ['required', 'integer', 'exists:service_categories,id'],
        ], ['ids.required' => __('Tick at least one service to move.')], ['target_category_id' => __('category')]);

        $moved = $this->services->moveToCategory($input['ids'], (int) $input['target_category_id'], $request->user()->id);
        $category = $this->categories->all()->firstWhere('id', (int) $input['target_category_id']);

        return back()->with('success', __(':count services moved to :category.', ['count' => $moved, 'category' => $category?->name]));
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->services->delete($this->services->find($id), $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['service' => $e->getMessage()]);
        }

        return redirect()->route('admin.services.index')->with('success', 'Service deleted.');
    }
}
