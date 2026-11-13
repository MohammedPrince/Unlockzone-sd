<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    public function showLogin()
    {
        return view('auth.login', ['portal' => 'admin']);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->input('login'), $request->input('password'), 'admin', $request->boolean('remember'));

        if (!$result['ok']) {
            return back()->withErrors(['login' => $result['message']])->onlyInput('login');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }
}
