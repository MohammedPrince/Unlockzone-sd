<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Http\Requests\Customer\ProfileRequest;
use App\Services\CustomerService;
use App\Services\SettingService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private CustomerService $accounts,
    ) {
    }

    public function edit()
    {
        return view('admin.settings', ['settings' => $this->settings->all()]);
    }

    public function update(SettingsRequest $request)
    {
        $this->settings->save($request->validated(), $request->user()->id);

        return back()->with('success', 'Settings saved.');
    }

    /** The one-click maintenance switch at the top of the Settings page. */
    public function maintenance(Request $request)
    {
        $on = $request->boolean('enabled');
        $this->settings->setMaintenance($on, $request->user()->id);

        return back()->with('success', $on
            ? __('Maintenance mode is on. Visitors and customers now see the maintenance page.')
            : __('Maintenance mode is off. The site is open again.'));
    }

    /** The signed-in administrator's own name, email and password. */
    public function profile(Request $request)
    {
        return view('shared.profile', ['user' => $request->user(), 'action' => route('admin.profile.update')]);
    }

    public function updateProfile(ProfileRequest $request)
    {
        $this->accounts->update($request->user(), $request->safe()->except('current_password'));

        return back()->with('success', 'Profile updated.');
    }
}
