<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ProviderException;
use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Services\ApiProviderService;
use App\Services\ProviderOrderService;
use App\Services\ServiceCategoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Connections to other service providers' servers (DHRU Fusion API). */
class ApiProviderController extends Controller
{
    public function __construct(
        private ApiProviderService $providers,
        private ProviderOrderService $orders,
        private ServiceCategoryService $categories,
    ) {
    }

    public function index()
    {
        return view('admin.providers.index', ['providers' => $this->providers->all()]);
    }

    public function create()
    {
        return view('admin.providers.form', ['provider' => null]);
    }

    public function store(Request $request)
    {
        $provider = $this->providers->save(null, $this->validated($request, true), $request->user()->id);

        return $this->testAndRedirect($provider->id, __('Provider saved.'));
    }

    public function edit(int $id)
    {
        return view('admin.providers.form', ['provider' => $this->providers->find($id)]);
    }

    public function update(Request $request, int $id)
    {
        $this->providers->save($this->providers->find($id), $this->validated($request, false), $request->user()->id);

        return $this->testAndRedirect($id, __('Provider saved.'));
    }

    public function destroy(Request $request, int $id)
    {
        $this->providers->delete($this->providers->find($id), $request->user()->id);

        return redirect()->route('admin.providers.index')->with('success', __('Provider deleted. Its services are now handled manually.'));
    }

    /** Signs in to the provider and shows the balance, or the provider's error. */
    public function test(int $id)
    {
        return $this->testAndRedirect($id, null, true);
    }

    public function sync(Request $request, int $id)
    {
        try {
            $count = $this->providers->syncServices($this->providers->find($id), $request->user()->id);
        } catch (ProviderException $e) {
            return back()->withErrors(['provider' => __('The provider answered: :message', ['message' => $e->getMessage()])]);
        }

        return redirect()->route('admin.providers.services', $id)->with('success', __(':count services loaded from the provider.', ['count' => $count]));
    }

    /** The provider's price list, with tick boxes to import lines as local services. */
    public function services(Request $request, int $id)
    {
        $provider = $this->providers->find($id);
        $filters = $request->only(['q', 'type']);

        return view('admin.providers.services', [
            'provider' => $provider,
            'remote' => $this->providers->remoteServices($provider, $filters),
            'imported' => $this->providers->importedIds($provider),
            'categories' => $this->categories->all(),
            'filters' => $filters,
        ]);
    }

    public function import(Request $request, int $id)
    {
        $data = $request->validate([
            'remote_ids' => ['required', 'array', 'min:1', 'max:200'],
            'remote_ids.*' => ['string', 'max:40'],
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'margin' => ['required', 'numeric', 'min:0', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [], ['remote_ids' => __('services'), 'service_category_id' => __('category'), 'margin' => __('profit margin')]);

        try {
            $created = $this->providers->import(
                $this->providers->find($id), $data['remote_ids'], (int) $data['service_category_id'],
                (float) $data['margin'], $data['status'], $request->user()->id
            );
        } catch (WorkflowException $e) {
            return back()->withErrors(['import' => __($e->getMessage())]);
        }

        return back()->with('success', __(':count services imported.', ['count' => $created]));
    }

    /** Asks the providers about every open order right now (the scheduler does the same every minute). */
    public function syncOrders()
    {
        $checked = $this->orders->syncOpen(100);

        return back()->with('success', __(':count open provider orders checked.', ['count' => $checked]));
    }

    private function testAndRedirect(int $id, ?string $saved, bool $back = false)
    {
        $target = $back ? back() : redirect()->route('admin.providers.index');

        try {
            $info = $this->providers->test($this->providers->find($id));
        } catch (ProviderException $e) {
            if ($saved) {
                $target = $target->with('success', $saved);
            }

            return $target->withErrors(['provider' => __('Connection failed: :message', ['message' => $e->getMessage()])]);
        }

        return $target->with('success', trim(($saved ? $saved . ' ' : '') . __('Connected. Balance: :balance', [
            'balance' => amount($info['balance']) . ' ' . ($info['currency'] ?? ''),
        ])));
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:255', 'url:http,https'],
            'username' => ['required', 'string', 'max:150'],
            'api_key' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [], ['url' => __('server address'), 'api_key' => __('API key')]);
    }
}
