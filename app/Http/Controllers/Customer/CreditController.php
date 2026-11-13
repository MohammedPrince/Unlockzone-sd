<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Repositories\CreditRepository;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(private CreditRepository $credits)
    {
    }

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        // Only the signed-in customer's own ledger, whatever the query string says.
        $filters = ['user_id' => $userId] + $request->only(['type', 'from', 'to']);

        return view('customer.credits', [
            'transactions' => $this->credits->paginate($filters, 20),
            'balance' => $this->credits->balance($userId),
            'totals' => $this->credits->totals(['user_id' => $userId]),
            'filters' => $filters,
            'types' => CreditTransaction::TYPES,
        ]);
    }

    /** "Export to Excel": the customer's own statement as a CSV file that Excel opens directly. */
    public function export(Request $request)
    {
        $type = $request->query('type');
        $filters = ['user_id' => $request->user()->id] + (in_array($type, CreditTransaction::TYPES, true) ? ['type' => $type] : []);

        $rows = (function () use ($filters) {
            yield [__('Date'), __('Description'), __('Type'), __('Amount'), __('Balance'), __('Reference')];

            foreach ($this->credits->cursor($filters) as $t) {
                yield [
                    $t->created_at->format('Y-m-d H:i'),
                    match ($t->transaction_type) {
                        'deduction' => __('New Order Placed'),
                        'recharge' => __('+ Add Funds'),
                        'refund' => __('Order Refunded'),
                        default => $t->description ?: __('Balance Adjustment'),
                    },
                    $t->amount >= 0 ? 'CR' : 'DR',
                    $t->amount,
                    $t->balance_after,
                    $t->reference,
                ];
            }
        })();

        return $this->csv('statement', $rows);
    }
}
