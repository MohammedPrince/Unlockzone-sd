<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRequestStatusRequest;
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

    private function filters(Request $request): array
    {
        return $request->only(['q', 'status', 'from', 'to', 'user_id', 'service_id']);
    }

    public function index(Request $request)
    {
        return view('admin.requests.index', [
            'requests' => $this->requests->paginate($this->filters($request)),
            'counts' => $this->requests->statusCounts(),
            'filters' => $this->filters($request),
            'statuses' => ServiceRequest::STATUSES,
        ]);
    }

    public function export(Request $request)
    {
        return $this->csv('service-requests', $this->requests->exportRows($this->filters($request)));
    }

    public function show(int $id)
    {
        $request = $this->requests->find($id);
        // Opening the page asks the provider for news (at most every 20 seconds).
        $this->providerOrders->refreshIfStale($request);

        return view('admin.requests.show', ['req' => $this->requests->find($id)]);
    }

    /** Sends an open request to the service's provider (first try, or again after an error). */
    public function sendToProvider(int $id)
    {
        $request = $this->requests->find($id);

        if ($this->providerOrders->dispatch($request, true)) {
            return back()->with('success', __('The order was sent to the provider.'));
        }

        return back()->withErrors(['provider' => $request->provider_message
            ? __('The provider answered: :message', ['message' => $request->provider_message])
            : __('This request cannot be sent to a provider.')]);
    }

    /** Asks the provider for the order's status right now. */
    public function checkProvider(int $id)
    {
        $this->providerOrders->refresh($this->requests->find($id));

        return back()->with('success', __('Provider status checked.'));
    }

    public function update(UpdateRequestStatusRequest $request, int $id)
    {
        $data = $request->validated();

        try {
            $updated = $this->requests->updateStatus($id, $data['status'], $data['result'] ?? null, $data['error'] ?? null, $request->user());
        } catch (WorkflowException $e) {
            return back()->withErrors(['status' => __($e->getMessage())])->withInput();
        }

        $message = __('Request marked as :status.', ['status' => __($updated->status)]) . ($updated->refunded_at && in_array($updated->status, ['failed', 'cancelled'], true)
            ? ' ' . __(':amount refunded to the customer.', ['amount' => money($updated->amount)]) : '');

        return redirect()->route('admin.requests.show', $id)->with('success', $message);
    }
}
