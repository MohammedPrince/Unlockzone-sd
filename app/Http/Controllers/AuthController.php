<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use App\Http\Requests\Customer\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Customer portal: sign in, register, sign out, password reset. */
class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private CustomerService $customers,
    ) {
    }

    public function showLogin()
    {
        return view('auth.login', ['portal' => 'customer']);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->input('login'), $request->input('password'), 'customer', $request->boolean('remember'));

        if (!$result['ok']) {
            return back()->withErrors(['login' => $result['message']])->onlyInput('login', 'remember');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function showRegister()
    {
        abort_unless(setting('allow_registration') === '1', 403, 'Registration is currently closed.');

        return view('auth.register', ['countries' => config('countries')]);
    }

    public function register(RegisterRequest $request)
    {
        try {
            $customer = $this->customers->register($request->validated(), $request->session()->get('visit_source'));
        } catch (WorkflowException $e) {
            return back()->withErrors(['username' => $e->getMessage()])->withInput($request->except('password', 'password_confirmation'));
        }

        $message = $customer->status === 'pending'
            ? 'Your account was created and is waiting for administrator activation.'
            : 'Your account is ready. You can sign in now.';

        return redirect()->route('login')->with('success', $message);
    }

    public function logout(Request $request)
    {
        $wasAdmin = (bool) $request->user()?->isAdmin();

        $this->auth->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($wasAdmin ? 'admin.login' : 'login');
    }

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);

        $this->auth->sendResetLink($data['email']);

        // Same answer whether or not the email exists.
        return back()->with('success', 'If that email belongs to an account, a reset link has been sent to it.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!$this->auth->resetPassword($data + ['password_confirmation' => $request->input('password_confirmation')])) {
            return back()->withErrors(['email' => 'This reset link is invalid or has expired. Please request a new one.'])->onlyInput('email');
        }

        return redirect()->route('login')->with('success', 'Your password was changed. You can sign in now.');
    }
}
