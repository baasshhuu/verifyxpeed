<?php

namespace App\Http\Controllers;

use App\Models\Verification;
use App\Services\VerificationService;
use App\Support\Catalog;
use App\Support\Money;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    private function own(Request $r, Verification $v): void
    {
        abort_unless($v->user_id === $r->user()->id || $r->user()->isAdmin(), 404);
    }

    public function index(Request $r)
    {
        $q = $this->filtered($r);
        $items = $q->latest('id')->paginate(25)->withQueryString();
        return view('app.history', ['items' => $items, 'products' => Catalog::all(), 'f' => $r->only(['product', 'state', 'q'])]);
    }

    private function filtered(Request $r)
    {
        $q = $r->user()->verifications();
        if ($p = $r->query('product')) $q->where('product', $p);
        if ($s = $r->query('state')) {
            match ($s) {
                'valid', 'invalid' => $q->where('outcome', $s),
                default => $q->where('state', $s),
            };
        }
        if ($t = trim((string) $r->query('q'))) $q->where(fn ($w) => $w->where('verification_id', 'like', "%$t%")->orWhere('client_reference', 'like', "%$t%"));
        return $q;
    }

    public function export(Request $r)
    {
        $rows = $this->filtered($r)->latest('id')->limit(10000)->get();
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['verification_id', 'date', 'check', 'source', 'state', 'outcome', 'provider_status', 'charged_inr', 'refunded', 'input']);
        foreach ($rows as $v) {
            fputcsv($out, [$v->verification_id, $v->created_at->format('Y-m-d H:i'), $v->productName(), $v->source, $v->state, $v->outcome, $v->provider_status, $v->charged_paise / 100, $v->refunded ? 'yes' : 'no', collect($v->input_masked)->map(fn ($x, $k) => "$k=$x")->implode('; ')]);
        }
        rewind($out);
        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="verifications-' . now()->format('Ymd-His') . '.csv"']);
    }

    public function show(Request $r, Verification $verification)
    {
        $this->own($r, $verification);
        return view('app.result', ['v' => $verification, 'p' => $verification->meta()]);
    }

    public function refresh(Request $r, Verification $verification, VerificationService $svc)
    {
        $this->own($r, $verification);
        $v = $svc->refresh($verification);
        return back()->with($v->error_code ? 'err' : 'ok', $v->error_code ? 'Cashfree said: ' . $v->error_message : 'Status updated: ' . ($v->provider_status ?: 'no change'));
    }

    public function otp(Request $r, Verification $verification, VerificationService $svc)
    {
        $this->own($r, $verification);
        $r->validate(['otp' => 'required|digits_between:4,8']);
        $v = $svc->verifyOtp($verification, $r->input('otp'));
        return back()->with($v->state === 'completed' ? 'ok' : 'err', $v->state === 'completed' ? 'OTP verified ✅' : ('OTP not accepted: ' . ($v->error_message ?: 'try again')));
    }

    public function document(Request $r, Verification $verification, string $type, VerificationService $svc)
    {
        $this->own($r, $verification);
        $res = $svc->fetchDocument($verification, $type);
        return back()->with($res['ok'] ? 'ok' : 'err', $res['ok'] ? ucfirst(strtolower(str_replace('_', ' ', $type))) . ' fetched 📄' : $res['message']);
    }
}
