<?php

namespace Tests\Feature;

use App\Models\CategoryField;
use App\Models\CustomerWallet;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every order-form box shows a placeholder: the admin's own, or an example that fits its type and label. */
class FieldPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function field(string $label, string $type, ?string $placeholder = null): CategoryField
    {
        return new CategoryField(['label' => $label, 'input_type' => $type, 'placeholder' => $placeholder]);
    }

    public function test_placeholders_follow_the_type_and_the_label(): void
    {
        $this->assertSame('15 digits, e.g. 356938035643809', $this->field('IEMI or SN', 'imei')->placeholderText());
        $this->assertSame('name@example.com', $this->field('iCloud Email ID', 'email')->placeholderText());
        $this->assertSame('Enter Password to access email', $this->field('Password to access email', 'email')->placeholderText());
        $this->assertSame('e.g. A7', $this->field('CPU TYPE', 'text')->placeholderText());
        $this->assertSame('Paste the link, e.g. https://files.fm/…', $this->field('Screenshot of the devices ABOUT page (full)', 'text')->placeholderText());
        $this->assertSame('Paste the link, e.g. https://files.fm/…', $this->field('Video Link Using (https://files.fm)', 'text')->placeholderText());
        $this->assertSame('e.g. +249 912 345 678', $this->field('whatsapp number', 'text')->placeholderText());
        $this->assertSame('e.g. F2LXK0ABCD12', $this->field('SN', 'text')->placeholderText());
        $this->assertSame('e.g. iPhone 11 Pro Max', $this->field('Model', 'text')->placeholderText());
        $this->assertSame('Enter Questions: 1', $this->field('Questions: 1', 'text')->placeholderText());
        $this->assertSame('e.g. 1', $this->field('QNT', 'number')->placeholderText());
        // The admin's own placeholder always wins.
        $this->assertSame('A7 or A7X', $this->field('CPU TYPE', 'text', 'A7 or A7X')->placeholderText());
    }

    public function test_the_order_form_shows_them(): void
    {
        $category = ServiceCategory::create(['name' => 'iPad', 'slug' => 'ipad', 'status' => 'active', 'sort_order' => 0]);
        $cpu = $category->fields()->create(['label' => 'CPU TYPE', 'input_type' => 'text', 'is_required' => true, 'sort_order' => 1]);
        $service = Service::create([
            'service_category_id' => $category->id, 'name' => 'IPAD CPU A7 AUTO REPLY', 'slug' => 'ipad-a7', 'price' => '3.57',
            'status' => 'active', 'configuration' => ['field_ids' => [$cpu->id], 'execution' => 'manual'],
        ]);
        $customer = new User(['first_name' => 'C', 'name' => 'C', 'username' => 'cust', 'email' => 'c@example.com', 'password' => 'secret-pass-1']);
        $customer->role = 'customer';
        $customer->status = 'active';
        $customer->save();
        CustomerWallet::create(['user_id' => $customer->id, 'balance' => 0]);

        $this->actingAs($customer)->get(route('customer.services.show', $service->id))->assertOk()
            ->assertSee('placeholder="e.g. A7"', false);
    }
}
