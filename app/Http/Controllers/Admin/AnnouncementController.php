<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Repositories\ActivityLogRepository;
use App\Repositories\SettingRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The scrolling bar at the top of the customer site: add, edit, show and hide its messages. */
class AnnouncementController extends Controller
{
    public function __construct(
        private ActivityLogRepository $logs,
        private SettingRepository $settings,
    ) {
    }

    public function index(Request $request)
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::orderBy('sort_order')->orderBy('id')->get(),
            'editing' => $request->integer('edit') ? Announcement::find($request->integer('edit')) : null,
            'colors' => Announcement::COLORS,
        ]);
    }

    public function store(Request $request)
    {
        $item = Announcement::create($this->validated($request));
        $this->logs->log('announcement_saved', 'Added announcement: ' . mb_substr($item->text, 0, 80), $item);

        return redirect()->route('admin.announcements.index')->with('success', __('Announcement added.'));
    }

    public function update(Request $request, int $id)
    {
        $item = Announcement::findOrFail($id);
        $item->update($this->validated($request));
        $this->logs->log('announcement_saved', 'Updated announcement: ' . mb_substr($item->text, 0, 80), $item);

        return redirect()->route('admin.announcements.index')->with('success', __('Announcement saved.'));
    }

    /** Show / hide one message without deleting it. */
    public function toggle(int $id)
    {
        $item = Announcement::findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);
        $this->logs->log('announcement_saved', ($item->is_active ? 'Showed' : 'Hid') . ' announcement: ' . mb_substr($item->text, 0, 80), $item);

        return back()->with('success', $item->is_active ? __('The announcement is now shown.') : __('The announcement is now hidden.'));
    }

    public function destroy(int $id)
    {
        $item = Announcement::findOrFail($id);
        $item->delete();
        $this->logs->log('announcement_deleted', 'Deleted announcement: ' . mb_substr($item->text, 0, 80));

        return redirect()->route('admin.announcements.index')->with('success', __('Announcement deleted.'));
    }

    /** The two switches for the whole bar: on / off, and whether new services are listed in it. */
    public function bar(Request $request)
    {
        $this->settings->setMany([
            'ticker_enabled' => $request->boolean('ticker_enabled') ? '1' : '0',
            'ticker_new_services' => $request->boolean('ticker_new_services') ? '1' : '0',
            // Optional: when the form does not send a speed, the current one is kept.
            'ticker_speed' => $request->validate(['ticker_speed' => ['nullable', Rule::in(['very-slow', 'slow', 'normal', 'fast'])]])['ticker_speed']
                ?? setting('ticker_speed', 'normal'),
        ]);
        $this->logs->log('settings_saved', 'Updated the announcement bar switches');

        return back()->with('success', __('Announcement bar updated.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'text_ar' => ['nullable', 'string', 'max:200'],
            'icon' => ['nullable', 'string', 'max:16'],
            'color' => ['required', Rule::in(Announcement::COLORS)],
            // Only web links: a "javascript:" address must never reach the customer page.
            'link' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/|\/)/i'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
