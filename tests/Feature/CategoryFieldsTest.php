<?php

namespace Tests\Feature;

use App\Models\CategoryField;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Categories > Edit: big field lists save, and removing a field removes it from the services too. */
class CategoryFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    /** A category like the live "IMEI Service": 55 fields (first "IEMI or SN"), and services using some of them. */
    private function bigCategory(): array
    {
        $category = ServiceCategory::create(['name' => 'IMEI Service', 'slug' => 'imei-service-big', 'status' => 'active', 'sort_order' => 0]);
        $fields = collect(range(1, 55))->map(fn ($i) => $category->fields()->create([
            'label' => $i === 1 ? 'IEMI or SN' : "Field {$i}", 'input_type' => $i === 1 ? 'imei' : 'text', 'is_required' => true, 'sort_order' => $i,
        ]));
        $services = collect(range(1, 3))->map(fn ($i) => Service::create([
            'service_category_id' => $category->id, 'name' => "Micromax {$i}", 'slug' => "micromax-{$i}", 'price' => '0.42', 'status' => 'active',
            'configuration' => ['field_ids' => [$fields[0]->id, $fields[4]->id], 'execution' => 'manual'],
        ]));

        return [$category, $fields, $services];
    }

    /** The form's rows as the browser sends them, without the ones in $drop. */
    private function rows($fields, array $drop = []): array
    {
        return $fields->reject(fn ($f) => in_array($f->id, $drop, true))->values()
            ->map(fn ($f) => ['id' => $f->id, 'label' => $f->label, 'input_type' => $f->input_type, 'placeholder' => '', 'is_required' => '1'])->all();
    }

    public function test_removing_a_field_from_a_big_category_removes_it_from_its_services_in_one_save(): void
    {
        [$category, $fields, $services] = $this->bigCategory();
        $iemi = $fields[0];

        // The edit page says how many services use the field before removing it.
        $this->actingAs($this->admin())->get(route('admin.categories.edit', $category->id))->assertOk()
            ->assertSee('Used by 3 services')
            ->assertSee('name="fields_total" value="55"', false);

        $rows = $this->rows($fields, [$iemi->id]);
        $this->put(route('admin.categories.update', $category->id), [
            'name' => 'IMEI Service', 'status' => 'active', 'sort_order' => 0, 'fields' => $rows, 'fields_total' => count($rows),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.categories.index'));

        $this->assertNull(CategoryField::find($iemi->id));
        $this->assertSame(54, $category->fields()->count());
        foreach ($services as $service) {
            $this->assertSame([$fields[4]->id], $service->fresh()->fieldIds()); // gone from every service, the rest kept
        }
        $this->get(route('admin.services.edit', $services[0]->id))->assertOk()->assertDontSee('IEMI or SN');
    }

    public function test_a_form_cut_short_by_the_server_is_refused_and_nothing_is_removed(): void
    {
        [$category, $fields, $services] = $this->bigCategory();

        // The browser sent 55 rows but only 40 arrived (PHP max_input_vars): saving would delete 15 fields.
        $this->actingAs($this->admin())->put(route('admin.categories.update', $category->id), [
            'name' => 'IMEI Service', 'status' => 'active', 'sort_order' => 0,
            'fields' => array_slice($this->rows($fields), 0, 40), 'fields_total' => 55,
        ])->assertSessionHasErrors('fields');

        $this->assertSame(55, $category->fields()->count());
        $this->assertSame([$fields[0]->id, $fields[4]->id], $services[0]->fresh()->fieldIds());
    }

    public function test_up_to_150_fields(): void
    {
        $category = ServiceCategory::create(['name' => 'Many', 'slug' => 'many', 'status' => 'active', 'sort_order' => 0]);
        $make = fn ($n) => array_map(fn ($i) => ['label' => "F{$i}", 'input_type' => 'text', 'is_required' => '1'], range(1, $n));
        $save = fn ($n) => $this->actingAs($this->admin())->put(route('admin.categories.update', $category->id), [
            'name' => 'Many', 'status' => 'active', 'sort_order' => 0, 'fields' => $make($n),
        ]);

        $save(150)->assertSessionHasNoErrors();
        $this->assertSame(150, $category->fields()->count());
        $save(151)->assertSessionHasErrors('fields');
    }
}
