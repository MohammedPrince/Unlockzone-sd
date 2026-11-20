<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Services\CatalogTransferService;
use Illuminate\Http\Request;

/** Settings > Export / Import all categories and services (copy the catalog from DEV to PROD). */
class CatalogTransferController extends Controller
{
    public function __construct(private CatalogTransferService $catalog)
    {
    }

    public function export()
    {
        $json = json_encode($this->catalog->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(fn () => print($json), 'catalog-' . now()->format('Y-m-d-His') . '.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'catalog_file' => ['required', 'file', 'max:20480', 'extensions:json'],
        ], [], ['catalog_file' => __('export file')]);

        try {
            $categories = $this->catalog->parse((string) file_get_contents($request->file('catalog_file')->getRealPath()));
            $r = $this->catalog->import($categories, $request->boolean('update_existing'), $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['catalog_file' => __($e->getMessage())]);
        }

        $message = __('Catalog imported: :cc new categories, :cu updated; :sc new services, :su updated.', [
            'cc' => $r['categories_created'], 'cu' => $r['categories_updated'],
            'sc' => $r['services_created'], 'su' => $r['services_updated'],
        ]);
        if ($r['services_skipped']) {
            $message .= ' ' . __(':count existing services were left as they are.', ['count' => $r['services_skipped']]);
        }
        if ($r['providers_missing']) {
            $message .= ' ' . __(':count services use an API provider that is not on this site, so they were saved as manual. Add the provider, then connect them on the service page.', ['count' => $r['providers_missing']]);
        }

        return redirect()->route('admin.services.index')->with('success', $message);
    }
}
