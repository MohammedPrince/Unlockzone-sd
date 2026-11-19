<?php

namespace Tests\Feature;

use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** VIP customers see and pay VIP prices: a percentage on the site price, or a fixed price per service. */
class VipPricingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(string $username, float $balance = 50): User
    {
        $user = new User([
            'first_name' => ucfirst($username), 'last_name' => 'Test', 'name' => ucfirst($username) . ' Test',
            'username' => $username, 'email' => "{$username}@example.com", 'password' => 'secret-pass-1',
        ]);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();
        CustomerWallet::create(['user_id' => $user->id, 'balance' => 0]);
        app(CreditService::class)->recharge($user, $balance, null, 'test', $this->admin()->id);

        return $user;
    }

    private function order(User $customer, Service $service)
    {
        return $this->actingAs($customer)->post(route('customer.services.order', $service->id), [
            'idempotency_key' => (string) Str::uuid(),
            'fields' => [$service->fieldIds()[0] => '123456789012345'],
            'terms' => '1',
        ]);
    }

    public function test_admin_sets_vip_prices_and_only_vip_customers_pay_them(): void
    {
        $test1 = Service::where('slug', 'haafedk-test-1')->firstOrFail(); // site price 5.00
        $test2 = Service::where('slug', 'haafedk-test-2')->firstOrFail(); // site price 8.00
        $vip = $this->customer('vipguy');
        $normal = $this->customer('normalguy');

        // The page lists the services; 10% off everything, and a fixed 3.00 for test 1.
        $this->actingAs($this->admin())->get(route('admin.vip-prices.edit'))->assertOk()->assertSee('Haafedk test 1');
        $this->put(route('admin.vip-prices.update'), [
            'price_adjust' => '-10',
            'prices' => [$test1->id => '3', $test2->id => ''],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.vip-prices.edit') . '#vip-price-list');
        $this->assertSame('3.00', $test1->fresh()->vip_price);
        $this->assertNull($test2->fresh()->vip_price);

        // Turned on from the customer's profile.
        $this->post(route('admin.customers.vip', $vip->id), ['vip' => 1])->assertRedirect();
        $this->assertTrue($vip->fresh()->is_vip);
        $this->get(route('admin.customers.show', $vip->id))->assertOk()->assertSee('Back to normal prices');

        // The VIP customer sees the VIP price with the normal price struck through; others see the site price.
        $this->actingAs($vip->fresh())->get(route('customer.services.show', $test2->id))->assertOk()
            ->assertSee(price('7.20'))->assertSee('tag-vip-price', false);
        $this->actingAs($normal)->get(route('customer.services.show', $test2->id))->assertOk()
            ->assertSee(price('8.00'))->assertDontSee(price('7.20'))->assertDontSee('tag-vip-price', false);
        $this->actingAs($vip->fresh())->get(route('customer.services.index'))->assertOk()->assertSee('tag-vip-price', false);

        // Charged: fixed price wins, otherwise the percentage; a normal customer pays the site price.
        $this->order($vip->fresh(), $test1)->assertSessionHasNoErrors();
        $this->order($vip->fresh(), $test2)->assertSessionHasNoErrors();
        $this->order($normal, $test1)->assertSessionHasNoErrors();
        $this->assertSame(['3.00', '7.20'], ServiceRequest::where('user_id', $vip->id)->orderBy('id')->pluck('amount')->all());
        $this->assertSame('5.00', ServiceRequest::where('user_id', $normal->id)->value('amount'));
        $this->assertSame('39.80', $vip->wallet()->first()->balance);

        // Turned off: back to the site price.
        $this->actingAs($this->admin())->post(route('admin.customers.vip', $vip->id), ['vip' => 0]);
        $this->order($vip->fresh(), $test2)->assertSessionHasNoErrors();
        $this->assertSame('8.00', ServiceRequest::where('user_id', $vip->id)->latest('id')->value('amount'));
    }

    public function test_admin_adds_and_removes_vip_customers_from_the_vip_prices_page(): void
    {
        $customer = $this->customer('newvip');

        // Offered in "Add a VIP customer" while not VIP.
        $this->actingAs($this->admin())->get(route('admin.vip-prices.edit'))->assertOk()
            ->assertSee('vip-add-customer', false)->assertSee('newvip — Newvip Test');

        $this->post(route('admin.vip-prices.customers.add'), ['user_id' => $customer->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.vip-prices.edit'));
        $this->assertTrue($customer->fresh()->is_vip);
        $this->get(route('admin.vip-prices.edit'))->assertSee(route('admin.vip-prices.customers.remove', $customer->id), false)
            ->assertDontSee('newvip — Newvip Test');

        // An administrator cannot be added; nor can an unknown id.
        $this->post(route('admin.vip-prices.customers.add'), ['user_id' => $this->admin()->id])->assertSessionHasErrors('user_id');
        $this->post(route('admin.vip-prices.customers.add'), ['user_id' => 999999])->assertSessionHasErrors('user_id');
        $this->assertFalse((bool) $this->admin()->fresh()->is_vip);

        $this->delete(route('admin.vip-prices.customers.remove', $customer->id))->assertRedirect(route('admin.vip-prices.edit'));
        $this->assertFalse($customer->fresh()->is_vip);
    }

    public function test_price_list_shows_20_services_per_page_and_searches_every_page(): void
    {
        $category = \App\Models\ServiceCategory::where('slug', 'server-services')->firstOrFail();
        foreach (range(1, 25) as $i) {
            $category->services()->create(['name' => sprintf('Bulk service %02d', $i), 'slug' => "bulk-{$i}", 'price' => 2, 'status' => 'active']);
        }
        $total = Service::count();
        $this->actingAs($this->admin());

        $first = $this->get(route('admin.vip-prices.edit'))->assertOk()
            ->assertSee(__('Showing :from–:to of :total', ['from' => 1, 'to' => 20, 'total' => $total]));
        $this->assertSame(20, substr_count($first->getContent(), 'data-vip-price '));

        // Search covers services that are not on the first page.
        $found = $this->get(route('admin.vip-prices.edit', ['q' => 'Bulk service 25']))->assertOk()->assertSee('Bulk service 25');
        $this->assertSame(1, substr_count($found->getContent(), 'data-vip-price '));

        // Saving from page 2 of a search returns there.
        $last = Service::where('slug', 'bulk-25')->firstOrFail();
        $this->put(route('admin.vip-prices.update'), ['price_adjust' => '0', 'q' => 'Bulk', 'page' => 2, 'prices' => [$last->id => '1.5']])
            ->assertRedirect(route('admin.vip-prices.edit', ['q' => 'Bulk', 'page' => 2]) . '#vip-price-list');
        $this->assertSame('1.50', $last->fresh()->vip_price);

        // Category filter, "fixed price only" filter, and the save returns to them.
        $byCategory = $this->get(route('admin.vip-prices.edit', ['category' => $category->id]))->assertOk()
            ->assertSee('Bulk service 01')->assertDontSee('Haafedk test 1')->assertSee('data-vip-group="' . $category->id . '"', false);
        $this->assertSame(20, substr_count($byCategory->getContent(), 'data-vip-price '));
        $fixedOnly = $this->get(route('admin.vip-prices.edit', ['only' => 'fixed']))->assertOk()->assertSee('Bulk service 25');
        $this->assertSame(1, substr_count($fixedOnly->getContent(), 'data-vip-price '));
        $this->put(route('admin.vip-prices.update'), ['price_adjust' => '0', 'category' => $category->id, 'only' => 'fixed'])
            ->assertRedirect(route('admin.vip-prices.edit', ['category' => $category->id, 'only' => 'fixed']) . '#vip-price-list');
    }

    public function test_the_vip_customer_tag_and_vip_prices_go_together(): void
    {
        $customer = $this->customer('tagged');
        $update = fn (string $tag) => $this->actingAs($this->admin())->put(route('admin.customers.update', $customer->id), [
            'first_name' => 'Tagged', 'username' => 'tagged', 'email' => 'tagged@example.com', 'customer_tag' => $tag,
        ])->assertSessionHasNoErrors();

        // Tagging "vip" (any case) gives VIP prices, and the VIP prices page lists them as VIP.
        $update('vip');
        $this->assertTrue($customer->fresh()->is_vip);
        $this->get(route('admin.vip-prices.edit'))->assertSee(route('admin.vip-prices.customers.remove', $customer->id), false)
            ->assertDontSee('tagged — Tagged Test');
        // One badge in the customer list, not a yellow tag plus a purple one.
        $this->assertSame(0, substr_count($this->get(route('admin.customers.index'))->getContent(), '<span class="tag-vip">vip</span>'));

        // Another tag is just a label; changing away from "VIP" removes VIP prices.
        $update('Gold');
        $this->assertFalse($customer->fresh()->is_vip);

        // VIP given from the page keeps a non-VIP tag; removing VIP removes a "VIP" tag.
        $this->post(route('admin.vip-prices.customers.add'), ['user_id' => $customer->id]);
        $this->assertSame('Gold', $customer->fresh()->customer_tag);
        $update('VIP');
        $this->delete(route('admin.vip-prices.customers.remove', $customer->id));
        $this->assertFalse($customer->fresh()->is_vip);
        $this->assertNull($customer->fresh()->customer_tag);
    }

    public function test_a_save_without_a_service_price_keeps_the_fixed_prices(): void
    {
        $test1 = Service::where('slug', 'haafedk-test-1')->firstOrFail();
        $test1->forceFill(['vip_price' => '4.00'])->save();

        // The page sends only changed prices, so a missing one must stay as it is.
        $this->actingAs($this->admin())->put(route('admin.vip-prices.update'), ['price_adjust' => '5'])
            ->assertSessionHasNoErrors();
        $this->assertSame('4.00', $test1->fresh()->vip_price);
        $this->assertSame('5', setting('vip_price_adjust'));

        $this->put(route('admin.vip-prices.update'), ['price_adjust' => '-95'])->assertSessionHasErrors('price_adjust');
    }
}
