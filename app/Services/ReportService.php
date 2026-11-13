<?php

namespace App\Services;

use App\Repositories\ActivityLogRepository;
use App\Repositories\CreditRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ReportRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\ServiceRequestRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Throwable;

class ReportService
{
    public const PRESETS = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'month' => 'This month',
        'last_month' => 'Last month',
        'year' => 'This year',
        'custom' => 'Custom range',
    ];

    public function __construct(
        private ReportRepository $reports,
        private CreditRepository $credits,
        private CustomerRepository $customers,
        private ServiceRepository $services,
        private ServiceRequestRepository $requests,
        private ActivityLogRepository $logs,
    ) {
    }

    /** Turns ?period / ?from / ?to into a concrete date range. Bad input falls back to 30 days. */
    public function period(array $input): array
    {
        $preset = array_key_exists($input['period'] ?? '', self::PRESETS) ? $input['period'] : '30d';
        $now = Carbon::now();

        [$from, $to] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'custom' => $this->customRange($input, $now),
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        return ['preset' => $preset, 'from' => $from, 'to' => $to, 'label' => $from->translatedFormat('M j, Y') . ' – ' . $to->translatedFormat('M j, Y')];
    }

    private function customRange(array $input, Carbon $now): array
    {
        try {
            $from = Carbon::parse($input['from'] ?? '')->startOfDay();
            $to = Carbon::parse($input['to'] ?? '')->endOfDay();
        } catch (Throwable) {
            return [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()];
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // Cap at two years so a typo cannot build a 100-year daily series.
        if ($from->diffInDays($to) > 731) {
            $from = $to->copy()->subDays(731)->startOfDay();
        }

        return [$from, $to];
    }

    public function overview(array $period): array
    {
        [$from, $to] = [$period['from'], $period['to']];
        $requests = $this->reports->requestCounts($from, $to);
        $closed = $requests['completed'] + $requests['failed'] + $requests['cancelled'];

        return [
            'credit' => $this->reports->creditTotals($from, $to),
            'requests' => $requests,
            'successRate' => $closed > 0 ? round($requests['completed'] / $closed * 100, 1) : null,
            'newCustomers' => $this->reports->newCustomers($from, $to),
            'daily' => $this->reports->daily($from, $to),
            'topServices' => $this->reports->topServices($from, $to),
            'topCustomers' => $this->reports->topCustomers($from, $to),
            'categories' => $this->reports->categoryBreakdown($from, $to),
            'walletTotal' => $this->credits->walletTotal(),
        ];
    }

    public function dashboard(): array
    {
        $today = $this->period(['period' => 'today']);
        $month = $this->period(['period' => 'month']);
        $fortnight = ['from' => Carbon::now()->subDays(13)->startOfDay(), 'to' => Carbon::now()->endOfDay()];

        return [
            'customers' => $this->customers->statusCounts(),
            'services' => $this->services->counts(),
            'requests' => $this->requests->statusCounts(),
            'today' => $this->reports->creditTotals($today['from'], $today['to']),
            'month' => $this->reports->creditTotals($month['from'], $month['to']),
            'walletTotal' => $this->credits->walletTotal(),
            'daily' => $this->reports->daily($fortnight['from'], $fortnight['to']),
            'recentRequests' => $this->requests->recent(8),
            'recentTransactions' => $this->credits->paginate([], 8),
        ];
    }

    public function activity(array $filters): LengthAwarePaginator
    {
        return $this->logs->paginate($filters);
    }

    public function activityActions(): array
    {
        return $this->logs->actions();
    }

    public function ledgerExportRows(array $filters): \Generator
    {
        yield ['ID', 'Date', 'Customer', 'Username', 'Type', 'Amount', 'Balance before', 'Balance after', 'Reference', 'Description', 'Performed by'];

        foreach ($this->credits->cursor($filters) as $t) {
            yield [
                $t->id, $t->created_at->format('Y-m-d H:i'), $t->user?->name, $t->user?->username, $t->transaction_type,
                $t->amount, $t->balance_before, $t->balance_after, $t->reference, $t->description, $t->performer?->username ?? 'system',
            ];
        }
    }

    public function dailyExportRows(array $period): \Generator
    {
        yield ['Date', 'Recharges', 'Sales', 'Refunds', 'Net revenue', 'Requests'];

        foreach ($this->reports->daily($period['from'], $period['to']) as $d) {
            yield [$d['date'], $d['recharges'], $d['sales'], $d['refunds'], $d['sales'] - $d['refunds'], $d['requests']];
        }
    }
}
