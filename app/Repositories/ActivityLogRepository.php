<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ActivityLogRepository
{
    /** Writes an audit row. Never lets a logging failure break the action being logged. */
    public function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null): void
    {
        try {
            ActivityLog::create([
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'description' => mb_substr($description, 0, 255),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'properties' => $properties ?: null,
                'ip_address' => request()?->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return ActivityLog::with('user')
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function actions(): array
    {
        return ActivityLog::query()->distinct()->orderBy('action')->pluck('action')->all();
    }
}
