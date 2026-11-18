<?php

namespace App\Services;

use App\Repositories\VisitRepository;
use Illuminate\Support\Carbon;

/** Visitors by source and new customers, for the admin dashboard. */
class TrafficService
{
    public const RANGES = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'all' => 'All time',
    ];

    public function __construct(private VisitRepository $visits, private VisitSourceService $sources)
    {
    }

    public function summary(?string $range): array
    {
        $range = array_key_exists((string) $range, self::RANGES) ? $range : '7d';
        $to = Carbon::now()->endOfDay();
        $from = match ($range) {
            'today' => Carbon::now()->startOfDay(),
            '30d' => Carbon::now()->subDays(29)->startOfDay(),
            'all' => null,
            default => Carbon::now()->subDays(6)->startOfDay(),
        };

        $bySource = $this->visits->bySource($from, $to);
        $totalVisits = array_sum(array_column($bySource, 'visits'));

        $rows = [];
        foreach ($bySource as $source => $row) {
            $rows[] = $row + [
                'source' => $source,
                'label' => $this->sources->label($source),
                'share' => $totalVisits > 0 ? round($row['visits'] / $totalVisits * 100, 1) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => [$b['visits'], $b['signups']] <=> [$a['visits'], $a['signups']]);

        return [
            'range' => $range,
            'visits' => $totalVisits,
            'visitors' => $this->visits->uniqueVisitors($from, $to),
            'newCustomers' => $this->visits->newCustomers($from, $to),
            'signups' => array_sum(array_column($bySource, 'signups')),
            'rows' => $rows,
        ];
    }

    /** Ready-to-share links tagged with their source, for posting on each platform. */
    public function trackingLinks(): array
    {
        $base = rtrim(url('/'), '/');

        return collect(['facebook', 'whatsapp', 'telegram', 'instagram', 'tiktok'])
            ->mapWithKeys(fn ($s) => [$s => ['label' => $this->sources->label($s), 'url' => $base . '/?ref=' . $s]])
            ->all();
    }
}
