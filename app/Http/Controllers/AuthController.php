<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($cred, $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email or password is wrong.'])->onlyInput('email');
        }
        $r->session()->regenerate();
        return redirect()->intended(Auth::user()->isAdmin() ? route('admin.dashboard') : route('dashboard'));
    }

    public function showRegister() { return view('auth.register'); }

    public function register(Request $r, WalletService $wallet)
    {
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'company' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => ['required', 'regex:/^[6-9][0-9]{9}$/'],
            'business_type' => 'required|string|max:50',
            'gstin' => ['nullable', 'alpha_num', 'size:15'],
            'use_case' => 'required|string|min:15|max:500',
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => 'accepted',
        ], ['terms.accepted' => 'Please accept the terms to continue.', 'use_case.min' => 'Tell us a bit more about why you need verifications (at least 15 characters).']);
        $d['gstin'] = $d['gstin'] ? strtoupper($d['gstin']) : null;
        unset($d['terms']);
        $user = User::create($d + ['role' => 'customer', 'kyb_status' => config('verifyxpeed.require_approval') ? 'pending' : 'approved']);
        $user->walletModel();
        if (($c = (float) config('verifyxpeed.signup_credit')) > 0) {
            $wallet->credit($user, (int) round($c * 100), 'Welcome credit 🎁');
        }
        Auth::login($user);
        return redirect()->route('dashboard')->with('ok', 'Account created! 🎉');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('login');
    }
}
