<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Models\Service;
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
        $catalog = $this->services->catalog();
        $favoriteIds = $user->favoriteServices()->pluck('services.id')->all();
        $q = trim((string) $request->query('q'));

        // Opened from "Place an order" in the menu: only that category.
        if ($categoryId = $request->integer('category')) {
            $catalog = $catalog->where('id', $categoryId)->values();
        }

        // Groups: the part of the name before "|" ("AldazRActivator Premium HELLO A12/A13 | iPhone XR" is in
        // "AldazRActivator Premium HELLO A12/A13"); a name without "|" is in its category's group.
        // In catalog order; the same group in two categories is one group.
        $groups = [];
        foreach ($catalog as $category) {
            foreach ($category->services as $service) {
                $name = Service::groupOf($service->name, $category->name);
                $key = Service::groupKey($name);
                $groups[$key] ??= ['key' => $key, 'name' => $name, 'note' => null, 'services' => collect()];
                $groups[$key]['services']->push($service);
            }
        }
        // A group made from a whole category keeps the category's description under its heading.
        foreach ($catalog as $category) {
            if (isset($groups[$k = Service::groupKey($category->name)])) {
                $groups[$k]['note'] = $category->description;
            }
        }
        $groups = collect($groups);
        $groupList = $groups->map(fn ($g) => ['key' => $g['key'], 'name' => $g['name'], 'count' => $g['services']->count()])->values();

        $group = (string) $request->query('group');
        if ($group !== '' && $groups->has($group)) {
            $groups = $groups->only([$group]);
        } else {
            $group = '';
        }
        if ($q !== '') {
            $groups = $groups
                ->map(fn ($g) => ['services' => $g['services']->filter(fn ($s) => mb_stripos($s->name, $q) !== false)->values()] + $g)
                ->filter(fn ($g) => $g['services']->isNotEmpty());
        }

        // 20 services at a time, group by group, under their group headings. The page loads the next 20 as the
        // customer scrolls; a group that continues is joined to the one already shown.
        $all = $groups->flatMap(fn ($g) => $g['services'])->values();
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(), $all->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $onPage = $paginator->getCollection()->pluck('id')->flip();
        $sections = $groups
            ->map(fn ($g) => [
                'key' => 'group-' . $g['key'],
                'title' => $g['name'],
                'note' => $g['note'],
                'total' => $g['services']->count(),
                'services' => $g['services']->filter(fn ($s) => $onPage->has($s->id))->values(),
            ])
            ->filter(fn ($section) => $section['services']->isNotEmpty())
            ->values();

        return view('customer.services.index', [
            'catalog' => $catalog,
            'empty' => $all->isEmpty(),
            'groupList' => $groupList,
            'group' => $group,
            'sections' => $sections,
            'paginator' => $paginator,
            // "Use frequently" services, shown first, on the first page only.
            'favorites' => $paginator->onFirstPage() ? $all->whereIn('id', $favoriteIds)->values() : collect(),
            'favoriteIds' => $favoriteIds,
            'blockedIds' => $user->blockedServices()->pluck('services.id')->all(),
            'servicesSuspended' => (bool) $user->services_suspended,
            'balance' => $this->credits->balance($request->user()->id),
            'q' => $q,
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
