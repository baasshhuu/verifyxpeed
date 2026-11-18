<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $r)
    {
        return view('app.api-keys', ['keys' => $r->user()->apiKeys()->latest('id')->get(), 'block' => $r->user()->verifyBlockReason()]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:60', 'mode' => 'required|in:test,live', 'ips' => 'nullable|string|max:500']);
        if ($d['mode'] === 'live' && ($why = $r->user()->verifyBlockReason())) return back()->with('err', $why);
        [$key, $plain] = ApiKey::generate($r->user(), $d['name'], $d['mode']);
        if (! empty($d['ips'])) {
            $key->update(['ip_whitelist' => collect(preg_split('/[\s,]+/', $d['ips']))->filter(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP))->values()->all() ?: null]);
        }
        return back()->with('new_key', $plain)->with('ok', 'Key created. Copy it now, it won\'t be shown again 🔑');
    }

    public function destroy(Request $r, ApiKey $key)
    {
        abort_unless($key->user_id === $r->user()->id, 404);
        $key->update(['revoked_at' => now()]);
        return back()->with('ok', 'Key revoked.');
    }

    public function docs()
    {
        return view('app.docs');
    }
}
