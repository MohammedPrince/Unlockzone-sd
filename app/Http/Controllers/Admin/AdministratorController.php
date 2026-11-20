<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Services\AdministratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdministratorController extends Controller
{
    public function __construct(private AdministratorService $admins)
    {
    }

    public function index()
    {
        return view('admin.admins.index', ['admins' => $this->admins->all()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'first_name' => __('first name'), 'last_name' => __('last name'), 'username' => __('username'),
            'email' => __('email'), 'phone' => __('mobile'), 'password' => __('password'),
        ]);

        $admin = $this->admins->create($data, $request->user()->id);

        return redirect()->route('admin.admins.index')->with('success', __('Administrator :name added. They can sign in at the admin login now.', ['name' => $admin->username]));
    }

    public function status(Request $request, int $id)
    {
        $active = $request->boolean('active');

        try {
            $this->admins->setActive($this->admins->find($id), $active, $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['admin' => $e->getMessage()]);
        }

        return back()->with('success', $active ? __('Administrator activated.') : __('Administrator suspended. They can no longer sign in.'));
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->admins->delete($this->admins->find($id), $request->user()->id);
        } catch (WorkflowException $e) {
            return back()->withErrors(['admin' => $e->getMessage()]);
        }

        return back()->with('success', __('Administrator removed.'));
    }
}
