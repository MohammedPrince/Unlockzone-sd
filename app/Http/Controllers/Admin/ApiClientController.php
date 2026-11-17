<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Services\ApiClientService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Other servers that use our API (we are their provider). Shown under Admin > API providers. */
class ApiClientController extends Controller
{
    public function __construct(private ApiClientService $clients)
    {
    }

    public function create()
    {
        return $this->form(null);
    }

    public function store(Request $request)
    {
        try {
            [$client, $key] = $this->clients->save(null, $this->validated($request, true), $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['client' => __($e->getMessage())])->withInput();
        }

        return redirect()->route('admin.providers.index')
            ->with('success', __('API access created for :name.', ['name' => $client->user->username]))
            ->with('new_api_key', ['client' => $client->id, 'username' => $client->user->username, 'key' => $key]);
    }

    public function edit(int $id)
    {
        return $this->form($this->clients->find($id));
    }

    public function update(Request $request, int $id)
    {
        try {
            $this->clients->save($this->clients->find($id), $this->validated($request, false), $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['client' => __($e->getMessage())])->withInput();
        }

        return redirect()->route('admin.providers.index')->with('success', __('API access saved.'));
    }

    /** Suspend or resume one client's access. */
    public function status(Request $request, int $id)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]])['status'];
        $client = $this->clients->find($id);
        $this->clients->setStatus($client, $status, $request->user()->id);

        return back()->with('success', $status === 'active'
            ? __('API access of :name resumed.', ['name' => $client->user->username])
            : __('API access of :name suspended. Its open orders still finish.', ['name' => $client->user->username]));
    }

    public function regenerate(Request $request, int $id)
    {
        $client = $this->clients->find($id);
        $key = $this->clients->regenerateKey($client, $request->user()->id);

        return redirect()->route('admin.providers.index')
            ->with('success', __('New API key made for :name. The old key no longer works.', ['name' => $client->user->username]))
            ->with('new_api_key', ['client' => $client->id, 'username' => $client->user->username, 'key' => $key]);
    }

    public function destroy(Request $request, int $id)
    {
        $client = $this->clients->find($id);
        $name = $client->user->username;
        $this->clients->delete($client, $request->user()->id);

        return redirect()->route('admin.providers.index')->with('success', __('API access of :name removed. The customer account and its orders stay.', ['name' => $name]));
    }

    private function form($client)
    {
        return view('admin.providers.client-form', [
            'client' => $client,
            'customers' => $this->clients->customersForSelect($client),
            'categories' => $this->clients->serviceTree(),
        ]);
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'user_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('users', 'id')->where('role', 'customer')],
            'access' => ['required', Rule::in(['all', 'selected'])],
            'service_ids' => ['nullable', 'array', 'required_if:access,selected'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'allowed_ips' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['service_ids.required_if' => __('Tick at least one service, or share all services.')], [
            'user_id' => __('customer'), 'service_ids' => __('services'), 'allowed_ips' => __('allowed IP addresses'),
        ]);

        // An unticked list is sent as nothing at all; it still means "none".
        $data['service_ids'] = $data['service_ids'] ?? [];

        return $data;
    }
}
