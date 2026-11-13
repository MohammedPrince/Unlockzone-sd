<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SubmitServiceRequest;
use App\Models\ServiceRequest;
use App\Services\ProviderOrderService;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;

class ServiceRequestController extends Controller
{
    public function __construct(
        private ServiceRequestService $requests,
        private ProviderOrderService $providerOrders,
    ) {
    }

    public function index(Request $request)
    {
        // user_id is forced to the signed-in customer; it is never read from the query string.
        $filters = $request->only(['q', 'status', 'category']) + ['user_id' => $request->user()->id];
        $filters['user_id'] = $request->user()->id;

        return view('customer.requests.index', [
            'requests' => $this->requests->paginate($filters, 15),
            'counts' => $this->requests->statusCounts($request->user()->id),
            'filters' => $filters,
            'statuses' => ServiceRequest::STATUSES,
        ]);
    }

    /** CSV of the customer's own orders, with the same search and status filter as the list. */
    public function export(Request $request)
    {
        $filters = array_filter($request->only(['q', 'status', 'category']), fn ($v) => is_string($v) && $v !== '');

        return $this->csv('my-orders', $this->requests->customerExportRows($request->user()->id, $filters));
    }

    public function store(SubmitServiceRequest $request, int $id)
    {
        try {
            $created = $this->requests->submit(
                $request->user(),
                $id,
                $request->input('fields', []),
                $request->input('idempotency_key')
            );
        } catch (WorkflowException $e) {
            return back()->withErrors(['order' => __($e->getMessage())])->withInput();
        }

        // Services connected to a provider are forwarded straight away; the rest wait for an administrator.
        if ($created->wasRecentlyCreated) {
            $this->providerOrders->dispatch($created);
        }

        return redirect()->route('customer.requests.show', $created->id)
            ->with('success', __('Request submitted. :amount was deducted from your balance.', ['amount' => money($created->amount)]));
    }

    public function show(Request $request, int $id)
    {
        // Ownership is checked first; then the provider is asked for news (at most every 20 seconds).
        $this->providerOrders->refreshIfStale($this->requests->findOwned($request->user(), $id));

        return view('customer.requests.show', ['req' => $this->requests->findOwned($request->user(), $id)]);
    }
}
