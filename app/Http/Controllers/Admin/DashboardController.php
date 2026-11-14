<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Services\TrafficService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private ReportService $reports, private TrafficService $traffic)
    {
    }

    public function index(Request $request)
    {
        return view('admin.dashboard', $this->reports->dashboard() + [
            'traffic' => $this->traffic->summary($request->query('traffic')),
            'trafficRanges' => TrafficService::RANGES,
            'trackingLinks' => $this->traffic->trackingLinks(),
        ]);
    }
}
