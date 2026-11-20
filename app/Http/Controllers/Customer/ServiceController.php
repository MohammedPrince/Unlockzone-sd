<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Repositories\CreditRepository;
use App\Services\ServiceManagementService;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceManagementService $services,
        private ServiceRequestService $requests,
        private CreditRepository $credits,
    ) {
    }

    /** Services per page in the customer catalog. */
    public const PER_PAGE = 20;

    public function index(Request $request)
    {
        $user = $request->user();
        $catalog = $this->services->catalog($request->query('q'));
        $favoriteIds = $user->favoriteServices()->pluck('services.id')->all();

        // Opened from "Place an order" in the menu: only that category.
        if ($categoryId = $request->integer('category')) {
            $catalog = $catalog->where('id', $categoryId)->values();
        }

        // 20 services at a time, in catalog order (category by category), under their category headings. The page
        // loads the next 20 as the customer scrolls; a category that continues is joined to the one already shown.
        $all = $catalog->flatMap->services->values();
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(), $all->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $onPage = $paginator->getCollection()->pluck('id')->flip();
        $sections = $catalog
            ->map(fn ($category) => [
                'key' => 'category-' . $category->id,
                'title' => $category->name,
                'note' => $category->description,
                'total' => $category->services->count(),
                'services' => $category->services->filter(fn ($s) => $onPage->has($s->id))->values(),
            ])
            ->filter(fn ($section) => $section['services']->isNotEmpty())
            ->values();

        return view('customer.services.index', [
            'catalog' => $catalog,
            'sections' => $sections,
            'paginator' => $paginator,
            // "Use frequently" services, shown first, on the first page only.
            'favorites' => $paginator->onFirstPage() ? $all->whereIn('id', $favoriteIds)->values() : collect(),
            'favoriteIds' => $favoriteIds,
            'blockedIds' => $user->blockedServices()->pluck('services.id')->all(),
            'servicesSuspended' => (bool) $user->services_suspended,
            'balance' => $this->credits->balance($request->user()->id),
            'q' => $request->query('q'),
        ]);
    }

    /** The order form for one service. */
    public function show(Request $request, int $id)
    {
        try {
            $service = $this->requests->orderableService($id);
        } catch (WorkflowException $e) {
            return redirect()->route('customer.services.index')->withErrors(['service' => __($e->getMessage())]);
        }

        return view('customer.services.show', [
            'service' => $service,
            'fields' => $service->inputFields(),
            'canOrder' => $request->user()->canOrder($service->id),
            'isFavorite' => $request->user()->favoriteServices()->whereKey($service->id)->exists(),
            'balance' => $this->credits->balance($request->user()->id),
            // One key per form render: submitting the same form twice charges once.
            'idempotencyKey' => old('idempotency_key', (string) Str::uuid()),
        ]);
    }

    /** "Want to use frequently": pins the service to the top of the customer's catalog, or unpins it. */
    public function favorite(Request $request, int $id)
    {
        $added = $this->services->toggleFavorite($request->user(), $id);

        return back()->with('success', $added ? __('Added to your frequently used services.') : __('Removed from your frequently used services.'));
    }
}
