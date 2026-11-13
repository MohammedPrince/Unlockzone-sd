<?php

namespace Database\Seeders;

use App\Models\CustomerWallet;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Repositories\SettingRepository;
use Illuminate\Database\Seeder;

/**
 * Starting data: one Super Admin, the default settings and the blueprint's example categories.
 * Safe to run more than once.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!User::where('role', 'admin')->exists()) {
            $admin = new User([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'name' => 'Super Admin',
                'username' => 'admin',
                'email' => 'admin@dhru.local',
                // Change this right after the first sign-in (Admin > My profile).
                'password' => 'Admin@12345',
            ]);
            $admin->role = 'admin';
            $admin->status = 'active';
            $admin->save();
        }

        app(SettingRepository::class)->setMany(array_merge(SettingRepository::DEFAULTS, app(SettingRepository::class)->all()));

        if (ServiceCategory::count() === 0) {
            $emi = ServiceCategory::create(['name' => 'EMI Services', 'slug' => 'emi-services', 'description' => 'Services ordered by IMEI or lock code.', 'status' => 'active', 'sort_order' => 1]);
            $imei = $emi->fields()->create(['label' => 'IMEI', 'input_type' => 'imei', 'placeholder' => '15-digit IMEI', 'is_required' => true, 'sort_order' => 0]);
            $lock = $emi->fields()->create(['label' => 'Lock Code', 'input_type' => 'lock_code', 'placeholder' => 'Lock code', 'is_required' => true, 'sort_order' => 1]);
            $emi->fields()->create(['label' => 'Serial Number', 'input_type' => 'sn', 'placeholder' => 'Device serial number', 'is_required' => true, 'sort_order' => 2]);
            $emi->fields()->create(['label' => 'Email', 'input_type' => 'email', 'placeholder' => 'name@example.com', 'is_required' => true, 'sort_order' => 3]);

            $emi->services()->create(['name' => 'Haafedk test 1', 'slug' => 'haafedk-test-1', 'description' => 'Example service that asks for an IMEI.', 'price' => 5, 'status' => 'active', 'delivery_time' => '1-24 hours', 'configuration' => ['field_ids' => [$imei->id], 'execution' => 'manual']]);
            $emi->services()->create(['name' => 'Haafedk test 2', 'slug' => 'haafedk-test-2', 'description' => 'Example service that asks for a lock code.', 'price' => 8, 'status' => 'active', 'delivery_time' => '1-24 hours', 'configuration' => ['field_ids' => [$lock->id], 'execution' => 'manual']]);

            ServiceCategory::create(['name' => 'Server Services', 'slug' => 'server-services', 'description' => 'Server based services.', 'status' => 'active', 'sort_order' => 2]);
            ServiceCategory::create(['name' => 'Other Services', 'slug' => 'other-services', 'description' => 'Everything else.', 'status' => 'active', 'sort_order' => 3]);
        }

        // Every customer must have a wallet row.
        User::where('role', 'customer')->doesntHave('wallet')->get()
            ->each(fn (User $u) => CustomerWallet::create(['user_id' => $u->id, 'balance' => 0]));
    }
}
