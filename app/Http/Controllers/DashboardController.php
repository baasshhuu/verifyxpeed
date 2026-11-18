<?php

namespace App\Http\Controllers;

use App\Models\ProductSetting;
use App\Support\Catalog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $base = $u->verifications();
        $month = (clone $base)->where('created_at', '>=', now()->startOfMonth());
        $stats = [
            'balance' => $u->walletModel()->balance_paise,
            'month_count' => (clone $month)->count(),
            'month_spend' => (clone $month)->where('refunded', false)->sum('charged_paise'),
            'valid' => (clone $month)->where('outcome', 'valid')->count(),
            'done' => (clone $month)->where('state', 'completed')->count(),
            'pending' => (clone $base)->where('state', 'pending')->count(),
        ];
        $days = collect(range(6, 0))->map(function ($i) use ($u) {
            $d = now()->subDays($i);
            return ['label' => $d->format('D'), 'count' => $u->verifications()->whereDate('created_at', $d->toDateString())->count()];
        });
        $recent = (clone $base)->latest('id')->limit(6)->get();
        $top = (clone $month)->selectRaw('product, count(*) as c')->groupBy('product')->orderByDesc('c')->limit(4)->pluck('c', 'product');
        $popular = collect(['pan', 'bank_account', 'digilocker', 'gstin', 'face_match', 'upi_penny_drop'])->map(fn ($k) => Catalog::get($k));
        $prices = ProductSetting::map();
        return view('app.dashboard', compact('stats', 'days', 'recent', 'top', 'popular', 'prices'));
    }
}
