<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer;
use Illuminate\Support\Facades\Route;

// Visitors see the public home page; signed-in users are sent to their dashboard.
Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');

/* ---------- Search engines (no session or cookies) ---------- */
Route::withoutMiddleware('web')->group(function () {
    Route::get('robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');
    Route::get('sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'sitemap'])->name('sitemap');
});

/* ---------- Services for visitors, without signing in (can be closed in Admin > Settings) ---------- */
$publicCatalog = function () {
    Route::get('catalog', [\App\Http\Controllers\CatalogController::class, 'index'])->name('public.catalog');
    Route::get('catalog/{category}', [\App\Http\Controllers\CatalogController::class, 'category'])->where('category', '[0-9]{1,9}(-[^/]*)?')->name('public.category');
    Route::get('service/{service}', [\App\Http\Controllers\CatalogController::class, 'service'])->where('service', '[0-9]{1,9}(-[^/]*)?')->name('public.service');
};
$publicCatalog();

/* ---------- The public pages in Arabic at their own addresses (/ar/...), so search engines can find them ---------- */
Route::prefix('ar')->name('ar.')->group(function () use ($publicCatalog) {
    Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::view('terms', 'legal.terms')->name('legal.terms');
    Route::view('privacy', 'legal.privacy')->name('legal.privacy');
    $publicCatalog();
});

/* ---------- Public legal pages (Arabic or English, following the chosen language) ---------- */
// DHRU Fusion Pro Reseller providers announce finished orders here; the result is then read from their API.
Route::post('provider-callback/{provider}', \App\Http\Controllers\ProviderCallbackController::class)
    ->whereNumber('provider')->middleware('throttle:120,1')->name('providers.callback');

Route::view('terms', 'legal.terms')->name('legal.terms');
Route::view('privacy', 'legal.privacy')->name('legal.privacy');

/* ---------- Language switch (top bar and sign-in pages) ---------- */
Route::get('language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, \App\Http\Middleware\SetLocale::SUPPORTED, true), 404);

    // Leaving an Arabic address (/ar/...) for English: go to the same page without "/ar".
    $from = url()->previous();
    $root = rtrim(url('/'), '/');
    $path = str_starts_with($from, $root . '/') ? substr($from, strlen($root)) : '';
    if ($locale === 'en' && preg_match('#^/ar(?=[/?]|$)#', $path)) {
        $from = $root . '/' . ltrim(substr($path, 3), '/');
    }

    return redirect()->to($from)->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->name('locale');

/* ---------- Guests: customer sign in, registration, password reset ---------- */
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.submit');

    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.submit');

    Route::get('forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:4,1')->name('password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');

    Route::get('admin/login', [Admin\AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('admin/login', [Admin\AuthController::class, 'login'])->middleware('throttle:8,1')->name('admin.login.submit');
});

// The link emailed after registration (signed, works for a few minutes).
Route::get('email/confirm/{id}/{hash}', [AuthController::class, 'confirmEmail'])
    ->whereNumber('id')->middleware('throttle:10,1')->name('verification.verify');

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/* ---------- Customer portal ---------- */
Route::middleware('role:customer')->name('customer.')->group(function () {
    Route::get('dashboard', [Customer\DashboardController::class, 'index'])->name('dashboard');

    Route::get('services', [Customer\ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{id}', [Customer\ServiceController::class, 'show'])->whereNumber('id')->name('services.show');
    Route::post('services/{id}/favorite', [Customer\ServiceController::class, 'favorite'])->whereNumber('id')->name('services.favorite');
    Route::post('services/{id}/order', [Customer\ServiceRequestController::class, 'store'])->whereNumber('id')->middleware('throttle:30,1')->name('services.order');

    Route::get('requests', [Customer\ServiceRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/export', [Customer\ServiceRequestController::class, 'export'])->name('requests.export');
    Route::get('requests/live', [Customer\ServiceRequestController::class, 'live'])->name('requests.live');
    Route::get('requests/{id}', [Customer\ServiceRequestController::class, 'show'])->whereNumber('id')->name('requests.show');

    Route::get('credits', [Customer\CreditController::class, 'index'])->name('credits');
    Route::get('credits/export', [Customer\CreditController::class, 'export'])->name('credits.export');
    Route::get('guide', fn () => view('customer.guide'))->name('guide');

    Route::get('profile', [Customer\ProfileController::class, 'edit'])->name('profile');
    Route::put('profile', [Customer\ProfileController::class, 'update'])->name('profile.update');
});

/* ---------- Admin control panel ---------- */
Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    // Admin manual: one page, English or Arabic following the language switch.
    Route::view('guide', 'admin.guide')->name('guide');

    Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [Admin\NotificationController::class, 'read'])->name('notifications.read');

    Route::get('customers/export', [Admin\CustomerController::class, 'export'])->name('customers.export');
    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [Admin\CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [Admin\CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{id}', [Admin\CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
    Route::get('customers/{id}/edit', [Admin\CustomerController::class, 'edit'])->whereNumber('id')->name('customers.edit');
    Route::put('customers/{id}', [Admin\CustomerController::class, 'update'])->whereNumber('id')->name('customers.update');
    Route::post('customers/{id}/status', [Admin\CustomerController::class, 'status'])->whereNumber('id')->name('customers.status');
    Route::post('customers/{id}/services/suspend', [Admin\CustomerController::class, 'suspendServices'])->whereNumber('id')->name('customers.services.suspend');
    Route::post('customers/{id}/services/block', [Admin\CustomerController::class, 'blockService'])->whereNumber('id')->name('customers.services.block');
    Route::delete('customers/{id}/services/block/{serviceId}', [Admin\CustomerController::class, 'unblockService'])->whereNumber(['id', 'serviceId'])->name('customers.services.unblock');
    Route::post('customers/{id}/vip', [Admin\CustomerController::class, 'vip'])->whereNumber('id')->name('customers.vip');

    // Administrators: add, suspend or remove other admins (never yourself, never the last active one).
    Route::get('admins', [Admin\AdministratorController::class, 'index'])->name('admins.index');
    Route::post('admins', [Admin\AdministratorController::class, 'store'])->middleware('throttle:10,1')->name('admins.store');
    Route::get('admins/{id}/edit', [Admin\AdministratorController::class, 'edit'])->whereNumber('id')->name('admins.edit');
    Route::put('admins/{id}', [Admin\AdministratorController::class, 'update'])->whereNumber('id')->name('admins.update');
    Route::post('admins/{id}/status', [Admin\AdministratorController::class, 'status'])->whereNumber('id')->name('admins.status');
    Route::delete('admins/{id}', [Admin\AdministratorController::class, 'destroy'])->whereNumber('id')->name('admins.destroy');

    // VIP prices: what customers marked as VIP pay.
    Route::get('vip-prices', [Admin\VipPriceController::class, 'edit'])->name('vip-prices.edit');
    Route::put('vip-prices', [Admin\VipPriceController::class, 'update'])->name('vip-prices.update');
    Route::post('vip-prices/bulk', [Admin\VipPriceController::class, 'bulk'])->name('vip-prices.bulk');
    Route::post('vip-prices/customers',[Admin\VipPriceController::class, 'addCustomer'])->name('vip-prices.customers.add');
    Route::delete('vip-prices/customers/{id}', [Admin\VipPriceController::class, 'removeCustomer'])->whereNumber('id')->name('vip-prices.customers.remove');

    Route::get('credits', [Admin\CreditController::class, 'index'])->name('credits.index');
    Route::get('credits/export', [Admin\CreditController::class, 'export'])->name('credits.export');
    Route::get('credits/new', [Admin\CreditController::class, 'create'])->name('credits.create');
    Route::post('credits', [Admin\CreditController::class, 'store'])->name('credits.store');

    Route::get('categories', [Admin\ServiceCategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/create', [Admin\ServiceCategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [Admin\ServiceCategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{id}/edit', [Admin\ServiceCategoryController::class, 'edit'])->whereNumber('id')->name('categories.edit');
    Route::put('categories/{id}', [Admin\ServiceCategoryController::class, 'update'])->whereNumber('id')->name('categories.update');
    Route::post('categories/{id}/toggle', [Admin\ServiceCategoryController::class, 'toggle'])->whereNumber('id')->name('categories.toggle');
    Route::delete('categories/{id}', [Admin\ServiceCategoryController::class, 'destroy'])->whereNumber('id')->name('categories.destroy');

    Route::get('services', [Admin\ServiceController::class, 'index'])->name('services.index');
    Route::get('services/create', [Admin\ServiceController::class, 'create'])->name('services.create');
    Route::post('services', [Admin\ServiceController::class, 'store'])->name('services.store');
    Route::get('services/{id}/edit', [Admin\ServiceController::class, 'edit'])->whereNumber('id')->name('services.edit');
    Route::put('services/{id}', [Admin\ServiceController::class, 'update'])->whereNumber('id')->name('services.update');
    Route::post('services/move', [Admin\ServiceController::class, 'move'])->name('services.move');
    Route::post('services/{id}/provider-fields', [Admin\ServiceController::class, 'providerFields'])->whereNumber('id')->middleware('throttle:10,1')->name('services.provider-fields');
    Route::post('services/{id}/toggle', [Admin\ServiceController::class, 'toggle'])->whereNumber('id')->name('services.toggle');
    Route::delete('services/{id}', [Admin\ServiceController::class, 'destroy'])->whereNumber('id')->name('services.destroy');
    Route::post('uploads/image', [Admin\UploadController::class, 'image'])->middleware('throttle:30,1')->name('uploads.image');

    Route::get('requests', [Admin\ServiceRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/export', [Admin\ServiceRequestController::class, 'export'])->name('requests.export');
    Route::get('requests/live', [Admin\ServiceRequestController::class, 'live'])->name('requests.live');
    Route::get('requests/{id}', [Admin\ServiceRequestController::class, 'show'])->whereNumber('id')->name('requests.show');
    Route::put('requests/{id}', [Admin\ServiceRequestController::class, 'update'])->whereNumber('id')->name('requests.update');
    Route::post('requests/{id}/provider/send', [Admin\ServiceRequestController::class, 'sendToProvider'])->whereNumber('id')->name('requests.provider.send');
    Route::post('requests/{id}/provider/check', [Admin\ServiceRequestController::class, 'checkProvider'])->whereNumber('id')->name('requests.provider.check');

    /* Connections to other providers' servers (DHRU Fusion API) */
    Route::get('providers', [Admin\ApiProviderController::class, 'index'])->name('providers.index');
    Route::get('providers/create', [Admin\ApiProviderController::class, 'create'])->name('providers.create');
    Route::post('providers', [Admin\ApiProviderController::class, 'store'])->name('providers.store');
    Route::post('providers/update-fields', [Admin\ApiProviderController::class, 'updateAllFields'])->middleware('throttle:5,1')->name('providers.update-fields');
    Route::post('providers/repair-fields', [Admin\ApiProviderController::class, 'repairFields'])->name('providers.repair-fields');
    Route::post('providers/sync-orders', [Admin\ApiProviderController::class, 'syncOrders'])->name('providers.sync-orders');
    Route::get('providers/{id}/edit', [Admin\ApiProviderController::class, 'edit'])->whereNumber('id')->name('providers.edit');
    Route::put('providers/{id}', [Admin\ApiProviderController::class, 'update'])->whereNumber('id')->name('providers.update');
    Route::delete('providers/{id}', [Admin\ApiProviderController::class, 'destroy'])->whereNumber('id')->name('providers.destroy');
    Route::post('providers/{id}/test', [Admin\ApiProviderController::class, 'test'])->whereNumber('id')->name('providers.test');
    Route::post('providers/{id}/sync', [Admin\ApiProviderController::class, 'sync'])->whereNumber('id')->name('providers.sync');
    Route::get('providers/{id}/services', [Admin\ApiProviderController::class, 'services'])->whereNumber('id')->name('providers.services');
    Route::post('providers/{id}/import', [Admin\ApiProviderController::class, 'import'])->whereNumber('id')->name('providers.import');

    /* Other servers using our API (we are their provider); listed on the API providers page */
    Route::get('api-clients/create', [Admin\ApiClientController::class, 'create'])->name('api-clients.create');
    Route::post('api-clients', [Admin\ApiClientController::class, 'store'])->name('api-clients.store');
    Route::get('api-clients/{id}/edit', [Admin\ApiClientController::class, 'edit'])->whereNumber('id')->name('api-clients.edit');
    Route::put('api-clients/{id}', [Admin\ApiClientController::class, 'update'])->whereNumber('id')->name('api-clients.update');
    Route::post('api-clients/{id}/status', [Admin\ApiClientController::class, 'status'])->whereNumber('id')->name('api-clients.status');
    Route::post('api-clients/{id}/key', [Admin\ApiClientController::class, 'regenerate'])->whereNumber('id')->name('api-clients.key');
    Route::delete('api-clients/{id}', [Admin\ApiClientController::class, 'destroy'])->whereNumber('id')->name('api-clients.destroy');

    /* The scrolling announcement bar on the customer site */
    Route::get('announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::post('announcements/bar', [Admin\AnnouncementController::class, 'bar'])->name('announcements.bar');
    Route::put('announcements/{id}', [Admin\AnnouncementController::class, 'update'])->whereNumber('id')->name('announcements.update');
    Route::post('announcements/{id}/toggle', [Admin\AnnouncementController::class, 'toggle'])->whereNumber('id')->name('announcements.toggle');
    Route::delete('announcements/{id}', [Admin\AnnouncementController::class, 'destroy'])->whereNumber('id')->name('announcements.destroy');

    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [Admin\ReportController::class, 'export'])->name('reports.export');
    Route::get('activity', [Admin\ReportController::class, 'activity'])->name('activity');

    // Server Tools: only the fixed tool keys exist; anything else is a 404.
    Route::get('tools', [Admin\ServerToolController::class, 'index'])->name('tools.index');
    // Registered before tools/{tool} so "mail" is never taken for a tool key.
    Route::put('tools/mail', [Admin\ServerToolController::class, 'saveMail'])->name('tools.mail.save');
    Route::post('tools/mail/test', [Admin\ServerToolController::class, 'testMail'])->middleware('throttle:5,1')->name('tools.mail.test');
    Route::post('tools/{tool}', [Admin\ServerToolController::class, 'run'])->where('tool', '[a-z\-]+')->middleware('throttle:20,1')->name('tools.run');
    Route::get('tools/log/download', [Admin\ServerToolController::class, 'downloadLog'])->name('tools.log.download');
    Route::delete('tools/log', [Admin\ServerToolController::class, 'clearLog'])->name('tools.log.clear');
    // The backup list lives on the Server Tools page.
    Route::get('tools/backups', fn () => redirect()->to(route('admin.tools.index') . '#backups'))->name('tools.backups.index');
    Route::get('tools/backups/{file}', [Admin\ServerToolController::class, 'download'])->where('file', 'backup-[0-9\-]+\.sql')->name('tools.backups.download');
    Route::delete('tools/backups/{file}', [Admin\ServerToolController::class, 'destroy'])->where('file', 'backup-[0-9\-]+\.sql')->name('tools.backups.destroy');

    Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings');
    Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/maintenance', [Admin\SettingController::class, 'maintenance'])->name('maintenance');
    Route::post('settings/import', [Admin\ServiceImportController::class, 'upload'])->middleware('throttle:20,1')->name('settings.import.upload');
    Route::get('settings/import', [Admin\ServiceImportController::class, 'show'])->name('settings.import.show');
    Route::post('settings/import/confirm', [Admin\ServiceImportController::class, 'store'])->name('settings.import.store');
    Route::delete('settings/import', [Admin\ServiceImportController::class, 'cancel'])->name('settings.import.cancel');
    Route::get('settings/catalog/export', [Admin\CatalogTransferController::class, 'export'])->name('settings.catalog.export');
    Route::post('settings/catalog/import', [Admin\CatalogTransferController::class, 'import'])->middleware('throttle:10,1')->name('settings.catalog.import');
    Route::get('profile', [Admin\SettingController::class, 'profile'])->name('profile');
    Route::put('profile', [Admin\SettingController::class, 'updateProfile'])->name('profile.update');
});
