<?php

namespace App\Repositories;

use App\Models\CustomerWallet;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerRepository
{
    private function customers(): Builder
    {
        return User::where('role', User::ROLE_CUSTOMER);
    }

    private function filtered(array $filters): Builder
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'created_at', 'last_login_at'], true) ? $filters['sort'] : 'created_at';

        return $this->customers()
            ->with('wallet')
            ->withCount('serviceRequests')
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")
                    ->orWhere('username', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%")
                    ->orWhere('phone', 'like', "%{$v}%"));
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy($sort, $sort === 'name' ? 'asc' : 'desc');
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)->paginate($perPage)->withQueryString();
    }

    /** Chunked iteration for CSV export (keeps eager loads, unlike cursor()). */
    public function cursor(array $filters)
    {
        return $this->filtered($filters)->lazy(200);
    }

    /** Only ever returns customers, so an admin id can never be loaded through customer screens. */
    public function findOrFail(int $id): User
    {
        return $this->customers()->with('wallet')->findOrFail($id);
    }

    public function create(array $data, string $status): User
    {
        $user = new User($data);
        $user->role = User::ROLE_CUSTOMER;
        $user->status = $status;
        $user->save();

        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user;
    }

    public function setStatus(User $user, string $status): User
    {
        $user->status = $status;
        $user->save();

        return $user;
    }

    public function statusCounts(): array
    {
        $counts = $this->customers()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return [
            'all' => array_sum($counts),
            'active' => (int) ($counts['active'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
            'suspended' => (int) ($counts['suspended'] ?? 0),
        ];
    }

    public function search(string $term, int $limit = 20)
    {
        return $this->customers()
            ->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function allForSelect()
    {
        return $this->customers()->orderBy('name')->get(['id', 'name', 'username']);
    }
}
