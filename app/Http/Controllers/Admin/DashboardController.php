<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function index()
    {
        return view('admin.dashboard', $this->reports->dashboard());
    }
}
