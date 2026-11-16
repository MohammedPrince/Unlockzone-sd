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
        $all = $request->input('scope') === 'all';

        $data = $request->validate([
            'scope' => ['nullable', Rule::in(['selected', 'all'])],
            // "Import all" takes every service matching the page's search instead of the ticked ones.
            'remote_ids' => [$all ? 'nullable' : 'required', 'array', 'max:1000'],
            'remote_ids.*' => ['string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:20'],
            // Empty: each service goes into a category named after the provider's group (created if missing).
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'margin' => ['required', 'numeric', 'min:0', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], ['remote_ids.required' => __('Tick at least one service, or use "Import all".')], ['remote_ids' => __('services'), 'service_category_id' => __('category'), 'margin' => __('profit margin')]);

        $provider = $this->providers->find($id);
        $ids = $all
            ? $this->providers->matchingRemoteIds($provider, array_filter(['q' => $data['q'] ?? null, 'type' => $data['type'] ?? null]), 1000)
            : $data['remote_ids'];

        try {
            $result = $this->providers->import(
                $provider, $ids, isset($data['service_category_id']) ? (int) $data['service_category_id'] : null,
                (float) $data['margin'], $data['status'], $request->user()->id
            );
        } catch (WorkflowException $e) {
            return back()->withErrors(['import' => __($e->getMessage())]);
        }

        $message = __(':count services imported.', ['count' => $result['created']]);
        if ($result['linked']) {
            $message .= ' ' . __(':count existing services with the same name were switched to this API instead of being duplicated.', ['count' => $result['linked']]);
        }
        if ($result['categories']) {
            $message .= ' ' . __(':count new categories created.', ['count' => $result['categories']]);
        }
        if ($result['skipped']) {
            $message .= ' ' . __(':count were already imported.', ['count' => $result['skipped']]);
        }

        return back()->with('success', $message);
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
            'type' => ['nullable', Rule::in(array_keys(\App\Models\ApiProvider::TYPES))],
            'url' => ['required', 'string', 'max:255', 'url:http,https'],
            // The classic DHRU API signs in with username + key; the Reseller API with a token only.
            'username' => ['required_unless:type,dhru_reseller', 'nullable', 'string', 'max:150'],
            // Reseller API tokens can be long.
            'api_key' => [$creating ? 'required' : 'nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [], ['url' => __('server address'), 'api_key' => __('API key / token'), 'type' => __('API type')]);
    }
}
