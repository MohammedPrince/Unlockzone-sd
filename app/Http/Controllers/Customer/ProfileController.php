<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ProfileRequest;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private CustomerService $customers)
    {
    }

    public function edit(Request $request)
    {
        return view('shared.profile', ['user' => $request->user(), 'countries' => config('countries'), 'action' => route('customer.profile.update')]);
    }

    public function update(ProfileRequest $request)
    {
        $this->customers->update($request->user(), $request->safe()->except('current_password'));

        return back()->with('success', 'Profile updated.');
    }
}
