<?php

namespace App\Http\Controllers;

use App\Models\Topup;
use App\Services\Cashfree\PaymentGateway;
use App\Services\TopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function index(Request $r, PaymentGateway $pg)
    {
        $w = $r->user()->walletModel();
        $txns = $w->transactions()->paginate(30);
        $topups = $r->user()->topups()->latest('id')->limit(8)->get();
        $month = $w->transactions()->where('created_at', '>=', now()->startOfMonth());
        $stats = [
            'spent' => -(clone $month)->where('amount_paise', '<', 0)->sum('amount_paise'),
            'added' => (clone $month)->where('type', 'credit')->sum('amount_paise'),
            'refunds' => (clone $month)->where('type', 'refund')->sum('amount_paise'),
        ];
        return view('app.wallet', ['wallet' => $w, 'txns' => $txns, 'topups' => $topups, 'stats' => $stats, 'pgReady' => $pg->configured(), 'pgEnv' => $pg->env()]);
    }

    public function topup(Request $r, PaymentGateway $pg)
    {
        $min = config('verifyxpeed.topup.min'); $max = config('verifyxpeed.topup.max');
        $d = $r->validate(['amount' => "required|numeric|min:$min|max:$max"]);
        $t = Topup::create(['user_id' => $r->user()->id, 'order_id' => 'VXT' . strtoupper(Str::random(14)), 'method' => 'pg', 'amount_paise' => (int) round($d['amount'] * 100), 'status' => 'created']);
        $res = $pg->createOrder($t, $r->user());
        if (! $res->ok()) {
            $t->update(['status' => 'failed', 'gateway_response' => $res->data]);
            return back()->with('err', 'Could not start payment: ' . $res->errorMessage());
        }
        $t->update(['payment_session_id' => $res->get('payment_session_id'), 'gateway_response' => $res->data]);
        return view('app.pay', ['t' => $t, 'mode' => $pg->env() === 'production' ? 'production' : 'sandbox']);
    }

    public function topupReturn(Request $r, TopupService $svc)
    {
        $t = Topup::where('order_id', (string) $r->query('order_id'))->where('user_id', $r->user()->id)->firstOrFail();
        $t = $svc->confirm($t);
        return redirect()->route('wallet')->with($t->credited_at ? 'ok' : 'err', $t->credited_at ? 'Money added! 💸' : 'Payment not confirmed yet. If money left your account, it will show up here within a few minutes.');
    }

    public function bank(Request $r)
    {
        $min = config('verifyxpeed.topup.min');
        $d = $r->validate(['amount' => "required|numeric|min:$min", 'utr' => 'required|string|min:6|max:60', 'note' => 'nullable|string|max:200']);
        Topup::create(['user_id' => $r->user()->id, 'order_id' => 'VXB' . strtoupper(Str::random(14)), 'method' => 'bank', 'amount_paise' => (int) round($d['amount'] * 100), 'status' => 'pending_approval', 'utr' => strtoupper($d['utr']), 'note' => $d['note'] ?? null]);
        return back()->with('ok', 'Got it! We\'ll credit your wallet once the transfer is matched (usually within a few hours).');
    }
}
