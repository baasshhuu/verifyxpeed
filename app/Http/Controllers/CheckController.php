<?php

namespace App\Http\Controllers;

use App\Models\ProductSetting;
use App\Services\InsufficientBalance;
use App\Services\ProductUnavailable;
use App\Services\VerificationService;
use App\Support\Catalog;
use App\Support\InputRules;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    public function index(Request $r)
    {
        $prices = ProductSetting::map();
        $cat = $r->query('cat');
        $q = trim((string) $r->query('q'));
        $products = collect(Catalog::all())
            ->filter(fn ($p) => $prices[$p['key']]['enabled'])
            ->filter(fn ($p) => ! $cat || $p['cat'] === $cat)
            ->filter(fn ($p) => $q === '' || str_contains(strtolower($p['name'] . ' ' . $p['tagline']), strtolower($q)));
        return view('app.checks', compact('products', 'prices', 'cat', 'q'));
    }

    public function show(Request $r, string $product)
    {
        $p = Catalog::get($product) ?? abort(404);
        $price = ProductSetting::for($product);
        abort_unless($price['enabled'], 404);
        $recent = $r->user()->verifications()->where('product', $product)->latest('id')->limit(5)->get();
        $block = $r->user()->verifyBlockReason();
        $sandbox = config('verifyxpeed.web_env') === 'sandbox';
        return view('app.check', compact('p', 'price', 'recent', 'block', 'sandbox'));
    }

    public function run(Request $r, string $product, VerificationService $svc)
    {
        $p = Catalog::get($product) ?? abort(404);
        if ($why = $r->user()->verifyBlockReason()) return back()->with('err', $why);
        $r->validate(['consent' => 'accepted'], ['consent.accepted' => 'Please confirm the person gave consent.']);
        $data = InputRules::validate($p, $r->all() + $r->allFiles());
        try {
            $v = $svc->start($r->user(), $product, $data, 'web');
        } catch (InsufficientBalance) {
            return back()->withInput()->with('err', 'Wallet balance is too low for this check. Add money and try again 👀');
        } catch (ProductUnavailable $e) {
            return back()->with('err', $e->getMessage());
        }
        return redirect()->route('verifications.show', $v);
    }
}
