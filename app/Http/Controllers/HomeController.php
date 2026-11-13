<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ServiceCategory;
use App\Repositories\ServiceRepository;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** The public home page for visitors. Signed-in users go straight to their own dashboard. */
    public function index(Request $request, ServiceRepository $services)
    {
        if ($user = $request->user()) {
            return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'customer.dashboard');
        }

        // Active categories with how many services they sell and the lowest price, so visitors see what is on offer.
        $categories = ServiceCategory::where('status', 'active')
            ->withCount(['services' => fn ($q) => $q->where('status', 'active')])
            ->withMin(['services' => fn ($q) => $q->where('status', 'active')], 'price')
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->services_count > 0)
            ->values();

        $ticker = setting('ticker_enabled') === '1';

        return view('home', [
            'categories' => $categories,
            'tickerItems' => $ticker ? Announcement::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() : collect(),
            'tickerServices' => $ticker && setting('ticker_new_services') === '1' ? $services->recentlyAdded((int) setting('new_badge_days', 7)) : collect(),
        ]);
    }
}
