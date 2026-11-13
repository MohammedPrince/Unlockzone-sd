<?php

namespace App\Repositories;

use App\Models\CreditTransaction;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only aggregate queries for the dashboard and the reports page. */
class ReportRepository
{
    /** Table prefix (tbl_). Raw SQL fragments are not prefixed automatically, so they add it by hand. */
    private string $p;

    public function __construct()
    {
        $this->p = DB::getTablePrefix();
    }

    /** Ledger sums per type inside the period. Deductions come back as a positive "sales" figure. */
    public function creditTotals(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = CreditTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) as total, COUNT(*) as entries')
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        $sum = fn (string $type) => (float) ($rows[$type]->total ?? 0);

        $sales = abs($sum('deduction'));
        $refunds = $sum('refund');

        return [
            'recharges' => $sum('recharge'),
            'sales' => $sales,
            'refunds' => $refunds,
            'adjustments' => $sum('adjustment'),
            'net_revenue' => $sales - $refunds,
            'entries' => (int) $rows->sum('entries'),
        ];
    }

    public function requestCounts(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = ServiceRequest::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $out = ['all' => array_sum($rows)];
        foreach (ServiceRequest::STATUSES as $status) {
            $out[$status] = (int) ($rows[$status] ?? 0);
        }

        return $out;
    }

    public function newCustomers(CarbonInterface $from, CarbonInterface $to): int
    {
        return User::where('role', User::ROLE_CUSTOMER)->whereBetween('created_at', [$from, $to])->count();
    }

    /**
     * One row per day in the period: recharges, sales, refunds and request count.
     * Days with no activity are filled with zeros so charts have no gaps.
     */
    public function daily(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ledger = CreditTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, transaction_type, SUM(amount) as total')
            ->groupBy('day', 'transaction_type')
            ->get()
            ->groupBy('day');

        $requests = ServiceRequest::whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = collect();

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day = $day->copy()->addDay()) {
            $key = $day->toDateString();
            $types = ($ledger[$key] ?? collect())->pluck('total', 'transaction_type');

            $days->push([
                'date' => $key,
                'label' => $day->format('M j'),
                'recharges' => (float) ($types['recharge'] ?? 0),
                'sales' => abs((float) ($types['deduction'] ?? 0)),
                'refunds' => (float) ($types['refund'] ?? 0),
                'requests' => (int) ($requests[$key] ?? 0),
            ]);
        }

        return $days;
    }

    public function topServices(CarbonInterface $from, CarbonInterface $to, int $limit = 10): Collection
    {
        return ServiceRequest::query()
            ->join('services', 'services.id', '=', 'service_requests.service_id')
            ->whereBetween('service_requests.created_at', [$from, $to])
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('orders')
            ->limit($limit)
            ->get([
                'services.id',
                'services.name',
                DB::raw('COUNT(*) as orders'),
                DB::raw("SUM(CASE WHEN {$this->p}service_requests.status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN {$this->p}service_requests.status IN ('failed','cancelled') THEN 1 ELSE 0 END) as failed"),
                // Money kept: refunded requests do not count as revenue.
                DB::raw("SUM(CASE WHEN {$this->p}service_requests.refunded_at IS NULL THEN {$this->p}service_requests.amount ELSE 0 END) as revenue"),
            ]);
    }

    public function topCustomers(CarbonInterface $from, CarbonInterface $to, int $limit = 10): Collection
    {
        return ServiceRequest::query()
            ->join('users', 'users.id', '=', 'service_requests.user_id')
            ->whereBetween('service_requests.created_at', [$from, $to])
            ->groupBy('users.id', 'users.name', 'users.username')
            ->orderByDesc('spent')
            ->limit($limit)
            ->get([
                'users.id',
                'users.name',
                'users.username',
                DB::raw('COUNT(*) as orders'),
                DB::raw("SUM(CASE WHEN {$this->p}service_requests.refunded_at IS NULL THEN {$this->p}service_requests.amount ELSE 0 END) as spent"),
            ]);
    }

    public function categoryBreakdown(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return ServiceRequest::query()
            ->join('services', 'services.id', '=', 'service_requests.service_id')
            ->join('service_categories', 'service_categories.id', '=', 'services.service_category_id')
            ->whereBetween('service_requests.created_at', [$from, $to])
            ->groupBy('service_categories.id', 'service_categories.name')
            ->orderByDesc('revenue')
            ->get([
                'service_categories.name',
                DB::raw('COUNT(*) as orders'),
                DB::raw("SUM(CASE WHEN {$this->p}service_requests.refunded_at IS NULL THEN {$this->p}service_requests.amount ELSE 0 END) as revenue"),
            ]);
    }
}
