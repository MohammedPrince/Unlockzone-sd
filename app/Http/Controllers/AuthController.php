<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use App\Http\Requests\Customer\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
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

        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        try {
            $this->customers->register($request->validated(), $request->session()->get('visit_source'));
        } catch (WorkflowException $e) {
            return back()->withErrors(['username' => $e->getMessage()])->withInput($request->except('password', 'password_confirmation'));
        }

        $message = setting('new_account_status') === 'pending'
            ? 'Your account was created. Please confirm your email using the link we sent you; your account is then waiting for administrator activation.'
            : 'Your account was created. Please open the link we sent to your email within 5 minutes to confirm it, then you can sign in.';

        return redirect()->route('login')->with('success', $message);
    }

    /** The confirmation link from the registration email. */
    public function confirmEmail(Request $request, int $id, string $hash)
    {
        $customer = User::where('role', User::ROLE_CUSTOMER)->find($id);

        if (!$request->hasValidSignature() || !$customer || !hash_equals(sha1((string) $customer->email), $hash)) {
            if ($customer?->hasConfirmedEmail()) {
                return redirect()->route('login')->with('success', 'Your email is already confirmed.');
            }

            return redirect()->route('login')->withErrors([
                'login' => 'This confirmation link has expired or is not valid. Your account is waiting for administrator activation.',
            ]);
        }

        $customer = $this->customers->confirmEmail($customer);

        $message = $customer->isActive()
            ? 'Your email is confirmed. Now you can sign in.'
            : 'Your email is confirmed. Your account is waiting for administrator activation.';

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
