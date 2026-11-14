<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;

class VisitRepository
{
    /** Visits, unique visitors and sign-ups per source in the period (null $from = all time). */
    public function bySource(?CarbonInterface $from, CarbonInterface $to): array
    {
        $visits = Visit::query()
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->selectRaw('source, COUNT(*) as visits, COUNT(DISTINCT visitor_id) as visitors')
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        $signups = User::where('role', User::ROLE_CUSTOMER)
            ->whereNotNull('signup_source')
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->selectRaw('signup_source, COUNT(*) as total')
            ->groupBy('signup_source')
            ->pluck('total', 'signup_source');

        $rows = [];
        foreach ($visits->keys()->merge($signups->keys())->unique() as $source) {
            $rows[$source] = [
                'visits' => (int) ($visits[$source]->visits ?? 0),
                'visitors' => (int) ($visits[$source]->visitors ?? 0),
                'signups' => (int) ($signups[$source] ?? 0),
            ];
        }

        return $rows;
    }

    /** Unique visitors over the whole period (a person who came from two sources counts once). */
    public function uniqueVisitors(?CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Visit::query()
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->distinct()
            ->count('visitor_id');
    }

    public function newCustomers(?CarbonInterface $from, CarbonInterface $to): int
    {
        return User::where('role', User::ROLE_CUSTOMER)
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->count();
    }
}
