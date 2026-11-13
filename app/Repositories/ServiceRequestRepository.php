<?php

namespace App\Repositories;

use App\Models\ServiceRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ServiceRequestRepository
{
    private function filtered(array $filters): Builder
    {
        return ServiceRequest::query()
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->whereHas('service', fn ($s) => $s->where('service_category_id', (int) $v)))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(fn ($w) => $w->where('reference', 'like', "%{$v}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('username', 'like', "%{$v}%"))
                    ->orWhereHas('service', fn ($s) => $s->where('name', 'like', "%{$v}%")));
            });
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['user', 'service'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function cursor(array $filters)
    {
        return $this->filtered($filters)->with(['user', 'service'])->orderByDesc('id')->cursor();
    }

    public function findOrFail(int $id): ServiceRequest
    {
        return ServiceRequest::with(['user.wallet', 'service.category', 'processor', 'transactions'])->findOrFail($id);
    }

    /**
     * Authorization check: a customer can only ever load their own request.
     * Someone else's id gives the same 404 as a missing one.
     */
    public function findOwnedOrFail(int $userId, int $id): ServiceRequest
    {
        return ServiceRequest::with(['service.category', 'transactions'])
            ->where('user_id', $userId)
            ->findOrFail($id);
    }

    /** Row-locked copy for status changes. Call inside a transaction. */
    public function lock(int $id): ServiceRequest
    {
        return ServiceRequest::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function findByIdempotencyKey(int $userId, string $key): ?ServiceRequest
    {
        return ServiceRequest::where('user_id', $userId)->where('idempotency_key', $key)->first();
    }

    public function create(array $data): ServiceRequest
    {
        return ServiceRequest::create($data);
    }

    public function newReference(): string
    {
        do {
            $reference = 'SR-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (ServiceRequest::where('reference', $reference)->exists());

        return $reference;
    }

    public function statusCounts(?int $userId = null): array
    {
        $rows = ServiceRequest::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
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

    /** Credit already taken for orders that are still open (pending or processing). */
    public function lockedAmount(int $userId): string
    {
        return (string) ServiceRequest::where('user_id', $userId)
            ->whereIn('status', ['pending', 'processing'])
            ->whereNull('refunded_at')
            ->sum('amount');
    }

    public function recent(int $limit = 8, ?int $userId = null)
    {
        return ServiceRequest::with(['user', 'service'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
