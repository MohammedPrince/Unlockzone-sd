<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Repositories\CreditRepository;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ServiceRequestService $requests,
        private CreditRepository $credits,
    ) {
    }

    public function index(Request $request)
    {
        $userId = $request->user()->id;

        return view('customer.dashboard', [
            'balance' => $this->credits->balance($userId),
            'totals' => $this->credits->totals(['user_id' => $userId]),
            'counts' => $this->requests->statusCounts($userId),
            'locked' => $this->requests->lockedAmount($userId),
            'requests' => $this->requests->paginate(['user_id' => $userId], 6),
        ]);
    }
}
