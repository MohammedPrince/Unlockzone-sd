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

    public function edit()
    {
        return view('admin.vip-prices', [
            'categories' => ServiceCategory::with(['services' => fn ($q) => $q->orderBy('name')
                ->select(['id', 'service_category_id', 'name', 'price', 'vip_price', 'status'])])
                ->orderBy('sort_order')->orderBy('name')->get()
                ->filter(fn ($c) => $c->services->isNotEmpty())->values(),
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

        return redirect()->route('admin.vip-prices.edit')->with('success', __('VIP prices saved.'));
    }
}
