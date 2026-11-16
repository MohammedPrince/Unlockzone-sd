<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Services\ServiceCategoryService;
use App\Services\ServiceImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Settings > Import services from a text file: upload, check the preview, import the ticked rows. */
class ServiceImportController extends Controller
{
    private const SESSION_KEY = 'service_import';

    public function __construct(
        private ServiceImportService $import,
        private ServiceCategoryService $categories,
    ) {
    }

    public function upload(Request $request)
    {
        $request->validate([
            'import_file' => ['required', 'file', 'max:1024', 'mimes:txt', 'mimetypes:text/plain'],
        ], [], ['import_file' => __('text file')]);

        $file = $request->file('import_file');

        try {
            $parsed = $this->import->parse((string) file_get_contents($file->getRealPath()));
        } catch (WorkflowException $e) {
            return back()->withErrors(['import_file' => __($e->getMessage())]);
        }

        if (!$parsed['rows']) {
            return back()->withErrors(['import_file' => __('No services were found in this file. Start the file with the category name, then three lines per service: name, supplier line and a price line such as "$12.5 1-24 Hours".')]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'file' => mb_substr($file->getClientOriginalName(), 0, 100),
            'rows' => $parsed['rows'],
            'skipped' => array_slice($parsed['skipped'], 0, 20),
        ]);

        return redirect()->route('admin.settings.import.show');
    }

    public function show(Request $request)
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (!$data) {
            return redirect()->route('admin.settings')->withErrors(['import_file' => __('Upload a text file first.')]);
        }

        $categoryId = $request->integer('category') ?: null;

        return view('admin.settings-import', [
            'file' => $data['file'],
            'rows' => $this->import->markDuplicates($data['rows']),
            'skipped' => $data['skipped'],
            'categories' => $this->categories->all(),
            'categoryId' => $categoryId,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->session()->get(self::SESSION_KEY);

        if (!$data) {
            return redirect()->route('admin.settings')->withErrors(['import_file' => __('Upload a text file first.')]);
        }

        $input = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['integer', 'min:0'],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'margin' => ['required', 'numeric', 'min:0', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], ['rows.required' => __('Choose at least one service to import.')], ['margin' => __('profit margin')]);

        try {
            $result = $this->import->import(
                $data['rows'], $input['rows'],
                isset($input['service_category_id']) ? (int) $input['service_category_id'] : null,
                (float) $input['margin'], $input['status'], $request->user()->id
            );
        } catch (WorkflowException $e) {
            return back()->withErrors(['import' => __($e->getMessage())])->withInput();
        }

        $request->session()->forget(self::SESSION_KEY);

        $message = __(':count services imported.', ['count' => $result['created']]);
        if ($result['categories']) {
            $message .= ' ' . __(':count new categories created.', ['count' => $result['categories']]);
        }
        if ($result['skipped']) {
            $message .= ' ' . __(':count skipped because a service with the same name already exists.', ['count' => $result['skipped']]);
        }

        return redirect()->route('admin.services.index')->with('success', $message);
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('admin.settings');
    }
}
