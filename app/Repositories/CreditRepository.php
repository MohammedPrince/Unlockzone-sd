<?php

namespace App\Repositories;

use App\Models\CreditTransaction;
use App\Models\CustomerWallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CreditRepository
{
    /**
     * Returns the customer's wallet row locked FOR UPDATE.
     * Must be called inside a database transaction; the lock is held until it commits.
     */
    public function lockWallet(int $userId): CustomerWallet
    {
        CustomerWallet::firstOrCreate(['user_id' => $userId], ['balance' => 0]);

        return CustomerWallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    public function saveBalance(CustomerWallet $wallet, string $balance): void
    {
        $wallet->balance = $balance;
        $wallet->save();
    }

    public function createEntry(array $data): CreditTransaction
    {
        return CreditTransaction::create($data);
    }

    public function balance(int $userId): string
    {
        return (string) (CustomerWallet::where('user_id', $userId)->value('balance') ?? '0.00');
    }

    private function filtered(array $filters): Builder
    {
        return CreditTransaction::query()
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('transaction_type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(fn ($w) => $w->where('reference', 'like', "%{$v}%")
                    ->orWhere('description', 'like', "%{$v}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('username', 'like', "%{$v}%")));
            });
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['user', 'performer'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Lazy cursor for CSV export so a large ledger never loads into memory at once. */
    public function cursor(array $filters)
    {
        return $this->filtered($filters)->with(['user', 'performer'])->orderByDesc('id')->cursor();
    }

    /** Sum per transaction type for the same filters, e.g. ['recharge' => '500.00', ...]. */
    public function totals(array $filters): array
    {
        $rows = $this->filtered($filters)
            ->selectRaw('transaction_type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type')
            ->all();

        $out = [];
        foreach (CreditTransaction::TYPES as $type) {
            $out[$type] = (float) ($rows[$type] ?? 0);
        }

        return $out;
    }

    public function walletTotal(): float
    {
        return (float) CustomerWallet::sum('balance');
    }
}
