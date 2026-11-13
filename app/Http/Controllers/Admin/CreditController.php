<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreditOperationRequest;
use App\Models\CreditTransaction;
use App\Repositories\CreditRepository;
use App\Services\CreditService;
use App\Services\CustomerService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(
        private CreditService $credits,
        private CreditRepository $ledger,
        private CustomerService $customers,
        private ReportService $reports,
    ) {
    }

    private function filters(Request $request): array
    {
        return $request->only(['q', 'type', 'from', 'to', 'user_id']);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return view('admin.credits.index', [
            'transactions' => $this->ledger->paginate($filters),
            'totals' => $this->ledger->totals($filters),
            'walletTotal' => $this->ledger->walletTotal(),
            'filters' => $filters,
            'types' => CreditTransaction::TYPES,
        ]);
    }

    public function export(Request $request)
    {
        return $this->csv('credit-ledger', $this->reports->ledgerExportRows($this->filters($request)));
    }

    public function create(Request $request)
    {
        return view('admin.credits.form', [
            'customers' => $this->customers->allForSelect(),
            'selected' => $request->integer('customer') ?: null,
        ]);
    }

    public function store(CreditOperationRequest $request)
    {
        $data = $request->validated();
        $customer = $this->customers->find((int) $data['user_id']);

        try {
            $entry = $data['operation'] === 'recharge'
                ? $this->credits->recharge($customer, $data['amount'], $data['reference'] ?? null, $data['note'] ?? null, $request->user()->id)
                : $this->credits->adjust($customer, $data['amount'], $data['note'], $data['reference'] ?? null, $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['amount' => __($e->getMessage())])->withInput();
        }

        return redirect()->route('admin.customers.show', $customer->id)
            ->with('success', __(':operation of :amount recorded. New balance: :balance.', [
                'operation' => __(ucfirst($data['operation'])), 'amount' => money($entry->amount), 'balance' => money($entry->balance_after),
            ]));
    }
}
