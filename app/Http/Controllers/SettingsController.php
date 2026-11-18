<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function index(Request $r) { return view('app.settings', ['u' => $r->user()]); }

    public function update(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'company' => 'required|string|max:150', 'phone' => ['required', 'regex:/^[6-9][0-9]{9}$/'], 'gstin' => ['nullable', 'alpha_num', 'size:15'], 'low_balance' => 'required|numeric|min:0|max:1000000']);
        $r->user()->update(['name' => $d['name'], 'company' => $d['company'], 'phone' => $d['phone'], 'gstin' => $d['gstin'] ? strtoupper($d['gstin']) : null, 'low_balance_paise' => (int) round($d['low_balance'] * 100)]);
        return back()->with('ok', 'Saved ✅');
    }

    public function password(Request $r)
    {
        $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(8)]]);
        $r->user()->update(['password' => Hash::make($r->input('password'))]);
        return back()->with('ok', 'Password changed 🔒');
    }
}
