<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Repositories\SettingRepository;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** VIP prices: what customers marked as VIP pay, as a percentage on the site price and/or a fixed price per service. */
class VipPriceController extends Controller
{
    public function __construct(
        private SettingRepository $settings,
        private ActivityLogRepository $logs,
    ) {
    }

    /** Services per page in "Price per service". */
    public const PER_PAGE = 20;

    public function edit(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $categoryId = $request->integer('category');
        // "fixed" = only services with their own VIP price, "percent" = only those using the percentage.
        $only = in_array($request->query('only'), ['fixed', 'percent'], true) ? $request->query('only') : '';

        // In catalog order (category by category, then by name); archived categories are left out.
        $services = Service::query()
            ->join('service_categories as c', 'c.id', '=', 'services.service_category_id')
            ->whereNull('c.deleted_at')
            ->when($search !== '', fn ($q) => $q->where('services.name', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->when($categoryId, fn ($q) => $q->where('services.service_category_id', $categoryId))
            ->when($only === 'fixed', fn ($q) => $q->whereNotNull('services.vip_price'))
            ->when($only === 'percent', fn ($q) => $q->whereNull('services.vip_price'))
            ->orderBy('c.sort_order')->orderBy('c.name')->orderBy('services.name')->orderBy('services.id')
            ->select(['services.id', 'services.service_category_id', 'services.name', 'services.price', 'services.vip_price', 'services.status'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->fragment('vip-price-list');

        // This page's services under their category headings.
        $categoryRows = ServiceCategory::whereIn('id', $services->pluck('service_category_id')->unique())->get()->keyBy('id');
        $categories = $services->getCollection()
            ->groupBy('service_category_id')
            ->map(fn ($group, $id) => $categoryRows[$id]->setRelation('services', $group))
            ->values();

        return view('admin.vip-prices', [
            'services' => $services,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'only' => $only,
            // The "Category" filter: every category that has services, with how many.
            'categoryOptions' => ServiceCategory::withCount('services')->having('services_count', '>', 0)
                ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'fixedCount' => Service::whereNotNull('vip_price')->count(),
            'adjust' => (float) $this->settings->get('vip_price_adjust', 0),
            'vipCustomers' => User::where('role', User::ROLE_CUSTOMER)->where('is_vip', true)
                ->orderBy('username')->get(['id', 'name', 'username']),
            // For "Add a VIP customer".
            'otherCustomers' => User::where('role', User::ROLE_CUSTOMER)->where('is_vip', false)
                ->orderBy('username')->get(['id', 'name', 'username', 'status']),
        ]);
    }

    /** Adds a customer to the VIP customers from this page. */
    public function addCustomer(Request $request, CustomerService $customers)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_CUSTOMER)],
        ], [], ['user_id' => __('customer')]);

        $customer = $customers->find((int) $data['user_id']);
        $customers->setVip($customer, true, $request->user()->id);

        return redirect()->route('admin.vip-prices.edit')->with('success', __(':name now pays VIP prices.', ['name' => $customer->username]));
    }

    /** Removes a customer from the VIP customers: they pay the normal site prices again. */
    public function removeCustomer(Request $request, CustomerService $customers, int $id)
    {
        $customer = $customers->find($id);
        $customers->setVip($customer, false, $request->user()->id);

        return redirect()->route('admin.vip-prices.edit')->with('success', __(':name now pays the normal site prices.', ['name' => $customer->username]));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'price_adjust' => ['required', 'numeric', 'min:-90', 'max:1000', 'decimal:0,2'],
            'prices' => ['array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
        ], [], ['price_adjust' => __('VIP percentage'), 'prices.*' => __('VIP price')]);

        // [service id => fixed VIP price or null], only for services that exist.
        $wanted = collect($data['prices'] ?? [])
            ->mapWithKeys(fn ($v, $id) => [(int) $id => ($v === null || $v === '') ? null : number_format((float) $v, 2, '.', '')]);
        $current = Service::whereIn('id', $wanted->keys())->pluck('vip_price', 'id');

        $changed = 0;
        DB::transaction(function () use ($wanted, $current, &$changed) {
            foreach ($wanted as $id => $price) {
                if (!$current->has($id)) {
                    continue;
                }
                $old = $current[$id] === null ? null : number_format((float) $current[$id], 2, '.', '');
                if ($old !== $price) {
                    Service::whereKey($id)->update(['vip_price' => $price]);
                    $changed++;
                }
            }
        });

        $adjust = rtrim(rtrim(number_format((float) $data['price_adjust'], 2, '.', ''), '0'), '.');
        $this->settings->setMany(['vip_price_adjust' => $adjust]);

        $this->logs->log('vip_prices', "Updated VIP prices ({$adjust}%, {$changed} fixed price change(s))", null, [
            'price_adjust' => $adjust, 'changed' => $changed,
        ], $request->user()->id);

        // Back to the same page and search the admin was on.
        $back = array_filter([
            'q' => trim((string) $request->input('q')),
            'category' => $request->integer('category') ?: '',
            'only' => in_array($request->input('only'), ['fixed', 'percent'], true) ? $request->input('only') : '',
            'page' => max(1, $request->integer('page')),
        ], fn ($v) => $v !== '' && $v !== 1);

        return redirect()->to(route('admin.vip-prices.edit', $back) . '#vip-price-list')->with('success', __('VIP prices saved.'));
    }
}
