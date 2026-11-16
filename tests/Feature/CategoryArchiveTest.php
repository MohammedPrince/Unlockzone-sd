<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Deleting a category: refused with open orders, archives services with finished orders, keeps customers' history. */
class CategoryArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function customer(): User
    {
        $user = new User(['first_name' => 'C', 'name' => 'C', 'username' => 'cust', 'email' => 'c@example.com', 'password' => 'secret-pass-1']);
        $user->role = 'customer';
        $user->status = 'active';
        $user->save();

        return $user;
    }

    private function category(string $name): ServiceCategory
    {
        return ServiceCategory::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'status' => 'active', 'sort_order' => 0]);
    }

    private function service(ServiceCategory $category, string $name): Service
    {
        return Service::create([
            'service_category_id' => $category->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
            'price' => 5, 'status' => 'active', 'configuration' => ['field_ids' => []],
        ]);
    }

    private function order(User $user, Service $service, string $status): ServiceRequest
    {
        return ServiceRequest::create([
            'user_id' => $user->id, 'service_id' => $service->id, 'reference' => 'R' . uniqid(),
            'amount' => 5, 'status' => $status, 'request_data' => [],
        ]);
    }

    public function test_delete_is_refused_while_an_order_is_open_then_archives_and_keeps_history(): void
    {
        $customer = $this->customer();
        $category = $this->category('IMEI Services');
        $ordered = $this->service($category, 'Honor FRP');
        $unused = $this->service($category, 'Never ordered');
        $open = $this->order($customer, $ordered, 'processing');

        $this->actingAs($this->admin())->delete(route('admin.categories.destroy', $category->id))
            ->assertSessionHasErrors('category');
        $this->assertNotSoftDeleted($category);

        $open->update(['status' => 'completed']);

        $this->delete(route('admin.categories.destroy', $category->id))->assertSessionHas('success');

        // The service with an order and the category are archived; the unused service is gone for good.
        $this->assertSoftDeleted($category);
        $this->assertSoftDeleted($ordered);
        $this->assertDatabaseMissing('services', ['id' => $unused->id]);
        $this->assertFalse(Service::whereKey($ordered->id)->exists());

        // The customer still finds the order, greyed out, under the category's Order history entry.
        $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk()
            ->assertSee('IMEI Orders')->assertSee('cnav-archived', false);
        $this->get(route('customer.requests.index', ['category' => $category->id]))->assertOk()
            ->assertSee('Honor FRP')->assertSee('is-archived', false)->assertSee('This category was removed');
        $this->get(route('customer.requests.show', $open->id))->assertOk()->assertSee('Honor FRP');

        // A new category with the same name can be created again.
        $again = $this->category('IMEI Services 2');
        $this->assertNotSame($category->id, $again->id);
    }

    public function test_an_empty_or_unordered_category_is_removed_completely(): void
    {
        $category = $this->category('Unordered Services');
        $this->service($category, 'Unused');

        $this->actingAs($this->admin())->delete(route('admin.categories.destroy', $category->id))->assertSessionHas('success');

        $this->assertDatabaseMissing('service_categories', ['id' => $category->id]);
        $this->assertSame(0, Service::withTrashed()->where('service_category_id', $category->id)->count());
    }

    public function test_order_history_menu_is_named_by_category_without_a_server_orders_entry(): void
    {
        $customer = $this->customer();
        $this->service($this->category('IMEI Services'), 'A');
        $this->service($this->category('Server'), 'B');

        $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk()
            ->assertSee('IMEI Orders')
            ->assertSee('Server Orders') // "Server" + Orders, from the category
            ->assertDontSee('IMEI Services Orders');

        $this->assertSame('IMEI Orders', ServiceCategory::where('name', 'IMEI Services')->first()->ordersLabel());
        $this->assertSame('Server Orders', ServiceCategory::where('name', 'Server')->first()->ordersLabel());
    }
}
