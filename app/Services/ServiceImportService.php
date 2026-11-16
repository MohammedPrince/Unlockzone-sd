<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Repositories\ActivityLogRepository;
use App\Repositories\ServiceCategoryRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Settings > Import services from a text file.
 *
 * The file starts with the category name, then lists the services, three lines each:
 *     IMEI Services                             <- category (every service below goes in it)
 *     Honor Frp Key by Sn Only Instant          <- service name
 *     Honor Frp - Direct Source Services        <- supplier group / source: ignored
 *     $42.5 Instant                             <- price, then delivery time
 * Empty lines are ignored. Another category line can appear before any later service to start a new category.
 * A service with only its name above the price line also works. A file without a category line is imported
 * into the category chosen on the preview page.
 */
class ServiceImportService
{
    public const MAX_ROWS = 500;

    /** How long an import waits for one that is already running. */
    public static int $lockWaitSeconds = 10;

    /** The input field the customer fills in for each detected kind. */
    private const FIELDS = [
        'imei' => ['label' => 'IMEI', 'input_type' => 'imei', 'placeholder' => '15-digit IMEI'],
        'sn' => ['label' => 'Serial Number (SN)', 'input_type' => 'sn', 'placeholder' => 'Serial number'],
        'sn_imei' => ['label' => 'SN / IMEI', 'input_type' => 'text', 'placeholder' => 'Serial number or IMEI'],
    ];

    public function __construct(
        private ServiceRepository $services,
        private ServiceCategoryRepository $categories,
        private ActivityLogRepository $logs,
    ) {
    }

    /**
     * Reads the text into rows: ['name', 'category', 'price', 'delivery', 'instant', 'field'].
     * Returns ['rows' => [...], 'skipped' => [lines that are not part of a service]].
     */
    public function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $lines = array_values(array_filter(
            array_map(fn ($l) => trim(preg_replace('/\s+/u', ' ', $l)), preg_split('/\R/u', $text)),
            fn ($l) => $l !== ''
        ));

        $rows = [];
        $skipped = [];
        $buffer = [];
        $category = null;

        foreach ($lines as $line) {
            if (!preg_match('/^\$\s*([0-9][0-9,]*(?:\.[0-9]+)?)\s*(.*)$/u', $line, $m)) {
                $buffer[] = $line;
                continue;
            }

            if (!$buffer) {
                $skipped[] = $line;
                continue;
            }

            if (count($buffer) >= 3) {
                // Category line, name, source line: the line before the name starts a new category.
                $category = mb_substr($buffer[count($buffer) - 3], 0, 150);
                array_push($skipped, ...array_slice($buffer, 0, -3));
                $name = $buffer[count($buffer) - 2];
            } else {
                // Name and source line (source is dropped), or just the name.
                $name = $buffer[0];
            }

            $buffer = [];

            $delivery = trim($m[2]);
            $rows[] = [
                'name' => mb_substr($name, 0, 150),
                'category' => $category,
                'price' => round((float) str_replace(',', '', $m[1]), 2),
                'delivery' => $delivery !== '' ? mb_substr($delivery, 0, 60) : null,
                'instant' => (bool) preg_match('/^instant\b/i', $delivery),
                'field' => $this->detectField($name),
            ];

            if (count($rows) > self::MAX_ROWS) {
                throw new WorkflowException('The file has more than ' . self::MAX_ROWS . ' services. Split it into smaller files.');
            }
        }

        array_push($skipped, ...$buffer);

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /** What the customer must type: SN, IMEI, or either. Clean-IMEI services and unknowns ask for IMEI. */
    private function detectField(string $name): string
    {
        $sn = (bool) preg_match('/\b(SN|S\/N|serial)\b/i', $name);
        $imei = (bool) preg_match('/\bIMEI\b/i', $name) && !preg_match('/\bclean\s+IMEI\b/i', $name);

        return match (true) {
            $sn && $imei => 'sn_imei',
            $sn => 'sn',
            default => 'imei',
        };
    }

    public static function fieldLabel(string $kind): string
    {
        return self::FIELDS[$kind]['label'] ?? $kind;
    }

    /** Names are compared without case and extra spaces: "Honor  FRP " and "honor frp" are the same service. */
    public static function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    /** Every service name already on the site (any category), as keys. */
    private function existingKeys(): array
    {
        return Service::pluck('name')->mapWithKeys(fn ($n) => [self::key($n) => true])->all();
    }

    /**
     * Adds 'exists' (a service with this name is already on the site, in any category) and
     * 'repeat' (the same name appears earlier in the file) to every row.
     */
    public function markDuplicates(array $rows): array
    {
        $existing = $this->existingKeys();
        $seen = [];

        foreach ($rows as $i => $row) {
            $key = self::key($row['name']);

            $rows[$i]['exists'] = isset($existing[$key]);
            $rows[$i]['repeat'] = isset($seen[$key]);
            $seen[$key] = true;
        }

        return $rows;
    }

    /**
     * Creates the chosen rows. With $categoryId every service goes into that category; without it each row
     * goes into the category named in the file, created (active) when missing.
     * Price = file price + $margin percent. Returns ['created' => int, 'skipped' => int, 'categories' => int].
     */
    public function import(array $rows, array $indexes, ?int $categoryId, float $margin, string $status, int $adminId): array
    {
        $chosen = array_values(array_intersect_key($rows, array_flip(array_map('intval', $indexes))));

        if (!$chosen) {
            throw new WorkflowException('Choose at least one service to import.');
        }

        $target = $categoryId ? ServiceCategory::findOrFail($categoryId) : null;

        if (!$target && collect($chosen)->contains(fn ($r) => blank($r['category']))) {
            throw new WorkflowException('The file has no category line at the top. Choose a category to import the services into.');
        }

        // The same name ticked twice (e.g. one supplier service listed at two prices): the admin picks one.
        $twice = collect($chosen)->groupBy(fn ($r) => self::key($r['name']))->filter(fn ($g) => $g->count() > 1);
        if ($twice->isNotEmpty()) {
            throw new WorkflowException(__('These services are ticked more than once. Keep only one of each: :names', [
                'names' => $twice->map(fn ($g) => $g->first()['name'])->implode(', '),
            ]));
        }

        // One import at a time, so a double click or a second tab cannot create the same services twice.
        $lock = Cache::lock('service-import', 120);

        try {
            $lock->block(self::$lockWaitSeconds);
        } catch (LockTimeoutException) {
            throw new WorkflowException('Another import is running. Try again in a moment.');
        }

        try {
            return $this->createRows($chosen, $target, $margin, $status, $adminId);
        } finally {
            $lock->release();
        }
    }

    private function createRows(array $chosen, ?ServiceCategory $target, float $margin, string $status, int $adminId): array
    {
        return DB::transaction(function () use ($chosen, $target, $margin, $status, $adminId) {
            $created = 0;
            $skipped = 0;
            $newCategories = 0;
            $categories = [];
            // Read inside the lock, so services added since the preview are seen too.
            $existing = $this->existingKeys();

            foreach ($chosen as $row) {
                $key = self::key($row['name']);

                if (isset($existing[$key])) {
                    $skipped++;
                    continue;
                }

                $category = $target ?? ($categories[self::key($row['category'])] ??= $this->findOrCreateCategory($row['category'], $newCategories));
                $existing[$key] = true;

                $this->services->create([
                    'service_category_id' => $category->id,
                    'name' => $row['name'],
                    'slug' => $this->services->uniqueSlug($row['name']),
                    'description' => null,
                    'price' => number_format(round($row['price'] * (1 + $margin / 100), 2), 2, '.', ''),
                    'status' => $status,
                    'delivery_time' => $row['delivery'],
                    'is_instant' => $row['instant'],
                    'configuration' => ['field_ids' => [$this->fieldId($category, $row['field'])], 'execution' => 'manual'],
                ]);
                $created++;
            }

            $this->logs->log('services_imported', "Imported {$created} services from a text file", null, [
                'margin' => $margin, 'skipped' => $skipped, 'new_categories' => $newCategories,
            ], $adminId);

            return ['created' => $created, 'skipped' => $skipped, 'categories' => $newCategories];
        });
    }

    private function findOrCreateCategory(string $name, int &$newCategories): ServiceCategory
    {
        $category = ServiceCategory::get()->first(fn ($c) => self::key($c->name) === self::key($name));

        if ($category) {
            return $category;
        }

        $newCategories++;

        return $this->categories->create([
            'name' => $name,
            'slug' => $this->categories->uniqueSlug($name),
            'description' => null,
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    /** The category's field for this kind of input, added to the category when it does not have one yet. */
    private function fieldId(ServiceCategory $category, string $kind): int
    {
        $spec = self::FIELDS[$kind] ?? self::FIELDS['imei'];
        $fields = $category->fields()->get();

        $field = $spec['input_type'] === 'text'
            ? $fields->first(fn ($f) => mb_strtolower($f->label) === mb_strtolower($spec['label']))
            : $fields->firstWhere('input_type', $spec['input_type']);

        $field ??= $category->fields()->create($spec + ['is_required' => true, 'sort_order' => $fields->count()]);

        return $field->id;
    }
}
