<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers)
    {
    }

    private function filters(Request $request): array
    {
        return $request->only(['q', 'status', 'sort']);
    }

    public function index(Request $request)
    {
        return view('admin.customers.index', [
            'customers' => $this->customers->paginate($this->filters($request)),
            'counts' => $this->customers->statusCounts(),
            'filters' => $this->filters($request),
        ]);
    }

    public function export(Request $request)
    {
        return $this->csv('customers', $this->customers->exportRows($this->filters($request)));
    }

    public function create()
    {
        return view('admin.customers.form', ['customer' => null, 'countries' => config('countries')]);
    }

    public function store(CustomerRequest $request)
    {
        $customer = $this->customers->createByAdmin($request->validated(), $request->user()->id);

        return redirect()->route('admin.customers.show', $customer->id)->with('success', 'Customer created.');
    }

    public function show(int $id)
    {
        return view('admin.customers.show', $this->customers->profile($this->customers->find($id)));
    }

    public function edit(int $id)
    {
        return view('admin.customers.form', ['customer' => $this->customers->find($id), 'countries' => config('countries')]);
    }

    public function update(CustomerRequest $request, int $id)
    {
        $this->customers->update($this->customers->find($id), $request->validated(), $request->user()->id);

        return redirect()->route('admin.customers.show', $id)->with('success', 'Customer updated.');
    }

    public function status(Request $request, int $id)
    {
        $data = $request->validate(['status' => ['required', 'string']]);

        try {
            $this->customers->changeStatus($this->customers->find($id), $data['status'], $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Account status changed to :status.', ['status' => __(ucfirst($data['status']))]));
    }

    /** Suspend the customer from every service, or allow services again. The account keeps signing in. */
    public function suspendServices(Request $request, int $id)
    {
        $suspended = $request->boolean('suspended');
        $this->customers->suspendAllServices($this->customers->find($id), $suspended, $request->user()->id);

        return back()->with('success', $suspended ? __('The customer is suspended from all services.') : __('The customer can order services again.'));
    }

    /** Suspend the customer from one service. */
    public function blockService(Request $request, int $id)
    {
        $data = $request->validate(['service_id' => ['required', 'integer', 'exists:services,id']], [], ['service_id' => __('service')]);
        $this->customers->blockService($this->customers->find($id), (int) $data['service_id'], true, $request->user()->id);

        return back()->with('success', __('The customer is suspended from this service.'));
    }

    public function unblockService(Request $request, int $id, int $serviceId)
    {
        $this->customers->blockService($this->customers->find($id), $serviceId, false, $request->user()->id);

        return back()->with('success', __('The customer can order this service again.'));
    }
}
