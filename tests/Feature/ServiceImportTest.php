<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\ServiceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Settings > Import services from a text file. */
class ServiceImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/import-{$name}.txt"));
    }

    private function upload(string $name): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.settings.import.upload'), [
            'import_file' => UploadedFile::fake()->createWithContent("{$name}.txt", $this->fixture($name)),
        ]);
    }

    public function test_parser_reads_names_categories_prices_and_input_fields(): void
    {
        $parser = app(ServiceImportService::class);

        $honor = $parser->parse($this->fixture('honor'));
        $this->assertCount(7, $honor['rows']);
        $this->assertSame([], $honor['skipped']);
        $this->assertSame([
            'name' => 'HONOR FRP KEY BY - SN/IMEI - [ 1 : 3 HOURS ]', 'category' => 'IMEI Services',
            'price' => 38.0, 'delivery' => '1-3 Hours', 'instant' => false, 'field' => 'sn_imei',
        ], $honor['rows'][0]);
        $this->assertSame('sn', $honor['rows'][1]['field']);
        $this->assertTrue($honor['rows'][2]['instant']);
        $this->assertSame(37.25, $honor['rows'][4]['price']);
        $this->assertSame('sn_imei', $honor['rows'][4]['field']);
        $this->assertSame('imei', $honor['rows'][6]['field']);
        // Every service is under the category on the first line; the supplier lines never become categories.
        $this->assertSame(['IMEI Services'], array_values(array_unique(array_column($honor['rows'], 'category'))));

        // No category line: rows have no category (the admin picks one on the preview).
        $xiaomi = $parser->parse("\xEF\xBB\xBF" . str_replace("\n", "\r\n\r\n", $this->fixture('xiaomi')));
        $this->assertCount(14, $xiaomi['rows']);
        $this->assertNull($xiaomi['rows'][0]['category']);
        $this->assertSame('Xiaomi Mi Account Unlock Service [ Nederland ] Clean IMEI', $xiaomi['rows'][0]['name']);
        $this->assertSame('1-20 days', $xiaomi['rows'][2]['delivery']);
        $this->assertSame('imei', $xiaomi['rows'][0]['field']);

        // A second category line later in the file starts a new category.
        $two = $parser->parse("IMEI Services\nA\nSrc - x\n\$1 Instant\nServer Services\nB\nSrc - y\n\$2 1-3 Hours\nC\nSrc - y\n\$3 Instant");
        $this->assertSame(['IMEI Services', 'Server Services', 'Server Services'], array_column($two['rows'], 'category'));
        $this->assertSame(['A', 'B', 'C'], array_column($two['rows'], 'name'));
    }

    public function test_a_file_without_a_category_line_needs_a_chosen_category(): void
    {
        $this->upload('xiaomi');

        $this->post(route('admin.settings.import.store'), ['rows' => [0], 'margin' => 0, 'status' => 'active'])
            ->assertSessionHasErrors('import');

        $this->assertSame(0, Service::count() - $this->seededServices);
    }

    public function test_upload_preview_and_import_into_categories_from_the_file(): void
    {
        $this->upload('honor')->assertRedirect(route('admin.settings.import.show'));

        $this->get(route('admin.settings.import.show'))->assertOk()
            ->assertSee('Honor Frp Key by Sn Only Instant')
            ->assertSee('Same name appears earlier in the file');

        // Rows 1 and 2 share a name: import every row except the first copy.
        $this->post(route('admin.settings.import.store'), [
            'rows' => [0, 2, 3, 4, 5, 6], 'margin' => 10, 'status' => 'active',
        ])->assertRedirect(route('admin.services.index'))->assertSessionHas('success');

        $category = ServiceCategory::where('name', 'IMEI Services')->firstOrFail();
        $this->assertFalse(ServiceCategory::where('name', 'like', 'Honor Frp%')->exists());
        $this->assertSame('active', $category->status);
        $this->assertSame(6, $category->services()->count());

        $instant = Service::where('name', 'Honor Frp Key by Sn Only Auto Api Instant 100%')->sole();
        $this->assertSame('46.75', $instant->price); // 42.5 + 10%
        $this->assertTrue($instant->is_instant);
        $this->assertSame('sn', $instant->inputFields()->sole()->input_type);

        $both = Service::where('name', 'HONOR FRP KEY BY - SN/IMEI - [ 1 : 3 HOURS ]')->sole();
        $this->assertSame('SN / IMEI', $both->inputFields()->sole()->label);
        $this->assertEqualsCanonicalizing(['IMEI', 'Serial Number (SN)', 'SN / IMEI'], $category->fields()->pluck('label')->all());

        // The same file again: existing services are not imported twice.
        $this->upload('honor');
        $this->get(route('admin.settings.import.show'))->assertOk()->assertSee('Already exists');
        // The copy left out the first time has the same name as an imported one, so it is skipped too.
        $this->post(route('admin.settings.import.store'), ['rows' => [1], 'margin' => 0, 'status' => 'active'])
            ->assertSessionHas('success');
        $this->assertSame(6, $category->services()->count());
        $this->assertSame(6, Service::count() - $this->seededServices);
    }

    private int $seededServices = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seededServices = Service::count();
    }

    public function test_the_same_name_ticked_twice_is_refused(): void
    {
        $this->upload('honor');

        // Rows 1 and 2 are the same service at two prices.
        $this->post(route('admin.settings.import.store'), ['rows' => [1, 2, 3], 'margin' => 0, 'status' => 'active'])
            ->assertSessionHasErrors('import');

        $this->assertSame(0, Service::count() - $this->seededServices);
        $this->assertFalse(ServiceCategory::where('name', 'IMEI Services')->exists());
    }

    public function test_no_duplicate_in_another_category_or_with_other_case_and_spaces(): void
    {
        $other = ServiceCategory::create(['name' => 'Other', 'slug' => 'other-test', 'status' => 'active', 'sort_order' => 0]);
        Service::create([
            'service_category_id' => $other->id, 'name' => 'honor  frp key by sn only INSTANT ', 'slug' => 'manual-honor',
            'price' => 1, 'status' => 'active', 'configuration' => ['field_ids' => []],
        ]);
        // A category that differs only in case and spaces is reused, not created again.
        ServiceCategory::create(['name' => 'imei  SERVICES', 'slug' => 'imei-existing', 'status' => 'active', 'sort_order' => 0]);

        $this->upload('honor');
        $this->get(route('admin.settings.import.show'))->assertOk()->assertSee('Already exists');

        // Row 5 ("Honor Frp Key by Sn Only Instant") is the manual service above.
        $this->post(route('admin.settings.import.store'), ['rows' => [0, 2, 5], 'margin' => 0, 'status' => 'active'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 services imported') && str_contains($m, '1 skipped'));

        $this->assertSame(1, Service::where('name', 'like', 'honor%frp key by sn only instant%')->count());
        $this->assertSame(1, ServiceCategory::where('name', 'like', 'imei%services')->count());
        $this->assertSame(2, ServiceCategory::where('slug', 'imei-existing')->firstOrFail()->services()->count());
    }

    public function test_an_import_waits_while_another_one_holds_the_lock(): void
    {
        $this->upload('honor');
        $lock = \Illuminate\Support\Facades\Cache::lock('service-import', 120);
        $this->assertTrue($lock->get());
        ServiceImportService::$lockWaitSeconds = 0;

        try {
            $this->post(route('admin.settings.import.store'), ['rows' => [0], 'margin' => 0, 'status' => 'active'])
                ->assertSessionHasErrors('import');
        } finally {
            $lock->release();
            ServiceImportService::$lockWaitSeconds = 10;
        }

        $this->assertSame(0, Service::count() - $this->seededServices);
    }

    public function test_import_into_a_chosen_category_as_inactive(): void
    {
        $category = ServiceCategory::create(['name' => 'Xiaomi', 'slug' => 'xiaomi-test', 'status' => 'active', 'sort_order' => 0]);

        $this->upload('xiaomi');
        $this->get(route('admin.settings.import.show', ['category' => $category->id]))->assertOk();

        $this->post(route('admin.settings.import.store'), [
            'rows' => range(0, 13), 'service_category_id' => $category->id, 'margin' => 0, 'status' => 'inactive',
        ])->assertSessionHas('success');

        $this->assertSame(14, $category->services()->where('status', 'inactive')->count());
        $this->assertFalse(ServiceCategory::where('name', 'Xiaomi Remove Account')->exists());
        $this->assertSame('1.90', Service::where('name', 'like', '%Pakistan%')->value('price'));
    }

    public function test_ticked_services_move_to_another_category_keeping_their_inputs(): void
    {
        $this->upload('honor');
        $this->post(route('admin.settings.import.store'), ['rows' => [0, 1, 3], 'margin' => 0, 'status' => 'active']);
        $from = ServiceCategory::where('name', 'IMEI Services')->firstOrFail();
        $to = ServiceCategory::create(['name' => 'Honor', 'slug' => 'honor-move', 'status' => 'active', 'sort_order' => 0]);
        $to->fields()->create(['label' => 'IMEI', 'input_type' => 'imei', 'is_required' => true, 'sort_order' => 0]);

        $ids = $from->services()->orderBy('id')->pluck('id')->all();

        $this->get(route('admin.services.index'))->assertOk()->assertSee('data-check-all', false)->assertSee(route('admin.services.move'));

        // Only two of the three are ticked.
        $this->post(route('admin.services.move'), ['ids' => [$ids[0], $ids[1]], 'target_category_id' => $to->id])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 services moved to Honor'));

        $this->assertSame(1, $from->services()->count());
        $this->assertSame(2, $to->services()->count());

        // Each moved service still asks for the same input, now from the new category's fields.
        $both = Service::findOrFail($ids[0]);
        $this->assertSame('SN / IMEI', $both->inputFields()->sole()->label);
        $this->assertSame($to->id, $both->inputFields()->sole()->service_category_id);
        $this->assertSame('sn', Service::findOrFail($ids[1])->inputFields()->sole()->input_type);
        // The existing IMEI field is reused, the missing SN fields are copied once.
        $this->assertEqualsCanonicalizing(['IMEI', 'SN / IMEI', 'Serial Number (SN)'], $to->fields()->pluck('label')->all());

        $this->post(route('admin.services.move'), ['ids' => [], 'target_category_id' => $to->id])->assertSessionHasErrors('ids');
    }

    public function test_bad_files_and_non_admins_are_refused(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.import.upload'), [
            'import_file' => UploadedFile::fake()->createWithContent('notes.txt', "just some text\nwith no prices"),
        ])->assertSessionHasErrors('import_file');

        $this->post(route('admin.settings.import.store'), ['rows' => [0], 'margin' => 0, 'status' => 'active'])
            ->assertRedirect(route('admin.settings'));

        $customer = new User(['first_name' => 'C', 'name' => 'C', 'username' => 'cust', 'email' => 'c@example.com', 'password' => 'secret-pass-1']);
        $customer->role = 'customer';
        $customer->status = 'active';
        $customer->save();

        $count = Service::count();
        $this->actingAs($customer)->post(route('admin.settings.import.upload'), [
            'import_file' => UploadedFile::fake()->createWithContent('honor.txt', $this->fixture('honor')),
        ])->assertRedirect();
        $this->assertSame($count, Service::count());
        $this->assertNull(session('service_import'));
    }
}
