<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $period = $this->reports->period($request->only(['period', 'from', 'to']));

        return view('admin.reports.index', $this->reports->overview($period) + [
            'period' => $period,
            'presets' => ReportService::PRESETS,
        ]);
    }

    public function export(Request $request)
    {
        $period = $this->reports->period($request->only(['period', 'from', 'to']));

        return $this->csv('daily-report', $this->reports->dailyExportRows($period));
    }

    public function activity(Request $request)
    {
        $filters = $request->only(['q', 'action', 'from', 'to']);

        return view('admin.reports.activity', [
            'logs' => $this->reports->activity($filters),
            'actions' => $this->reports->activityActions(),
            'filters' => $filters,
        ]);
    }
}
