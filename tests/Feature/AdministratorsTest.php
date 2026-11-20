<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Administrators: admins add, suspend and remove other admins. */
class AdministratorsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->orderBy('id')->firstOrFail();
    }

    private function addAdmin(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.admins.store'), array_merge([
            'first_name' => 'Sara', 'last_name' => 'Ops', 'username' => 'sara', 'email' => 'sara@example.com',
            'phone' => '+249900000000', 'password' => 'strong-pass-1', 'password_confirmation' => 'strong-pass-1',
        ], $overrides));
    }

    public function test_admin_adds_another_admin_who_can_sign_in(): void
    {
        $this->actingAs($this->admin())->get(route('admin.admins.index'))->assertOk()->assertSee('Add administrator');

        $this->addAdmin()->assertSessionHasNoErrors()->assertRedirect(route('admin.admins.index'));

        $new = User::where('username', 'sara')->firstOrFail();
        $this->assertTrue($new->isAdmin());
        $this->assertSame('active', $new->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'admin_created']);

        auth()->logout();
        $this->post(route('admin.login.submit'), ['login' => 'sara', 'password' => 'strong-pass-1'])->assertRedirect();
        $this->assertAuthenticatedAs($new);
    }

    public function test_bad_input_is_refused_and_customers_cannot_add_admins(): void
    {
        $this->addAdmin(['password_confirmation' => 'other'])->assertSessionHasErrors('password');
        $this->addAdmin(['email' => $this->admin()->email])->assertSessionHasErrors('email');
        $this->addAdmin(['role' => 'customer', 'username' => 'x']); // too short: refused, and role is never taken from the form
        $this->assertSame(1, User::where('role', 'admin')->count());

        auth()->logout();
        $this->post(route('admin.admins.store'), ['username' => 'evil'])->assertRedirect(route('admin.login'));
    }

    public function test_suspend_and_remove_with_self_and_last_admin_protected(): void
    {
        $me = $this->admin();
        $this->addAdmin();
        $other = User::where('username', 'sara')->firstOrFail();

        // Never yourself.
        $this->actingAs($me)->post(route('admin.admins.status', $me->id), ['active' => 0])->assertSessionHasErrors('admin');
        $this->actingAs($me)->delete(route('admin.admins.destroy', $me->id))->assertSessionHasErrors('admin');

        // Suspended admins cannot sign in.
        $this->actingAs($me)->post(route('admin.admins.status', $other->id), ['active' => 0])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $other->fresh()->status);
        $this->actingAs($other->fresh())->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        // Activate again, then remove.
        $this->actingAs($me)->post(route('admin.admins.status', $other->id), ['active' => 1]);
        $this->assertSame('active', $other->fresh()->status);
        $this->actingAs($me)->delete(route('admin.admins.destroy', $other->id))->assertSessionHasNoErrors();
        $this->assertNull(User::find($other->id));
        $this->assertDatabaseHas('activity_logs', ['action' => 'admin_removed']);

        // Customers are not reachable through this page.
        $customer = new User(['first_name' => 'C', 'name' => 'C', 'username' => 'cust', 'email' => 'c@example.com', 'password' => 'secret-pass-1']);
        $customer->role = 'customer';
        $customer->status = 'active';
        $customer->save();
        $this->actingAs($me)->delete(route('admin.admins.destroy', $customer->id))->assertNotFound();
    }
}
