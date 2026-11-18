<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSetting;
use App\Models\ProviderLog;
use App\Models\Setting;
use App\Models\Topup;
use App\Models\User;
use App\Models\Verification;
use App\Models\Wallet;
use App\Services\Cashfree\PaymentGateway;
use App\Services\Cashfree\SecureIdClient;
use App\Services\InsufficientBalance;
use App\Services\TopupService;
use App\Services\WalletService;
use App\Support\Catalog;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $month = Verification::where('created_at', '>=', now()->startOfMonth())->where('refunded', false);
        $revenue = (clone $month)->sum('charged_paise');
        $gstRate = config('verifyxpeed.gst_rate');
        $revenueExGst = (int) round($revenue / (1 + $gstRate));
        $cost = (clone $month)->where('state', '!=', 'failed')->sum('cost_paise');
        $stats = [
            'revenue' => $revenueExGst,
            'cost' => $cost,
            'margin' => $revenueExGst - $cost,
            'customers' => User::where('role', 'customer')->count(),
            'pending_kyb' => User::where('role', 'customer')->where('kyb_status', 'pending')->count(),
            'today' => Verification::whereDate('created_at', today())->count(),
            'fail_today' => Verification::whereDate('created_at', today())->where('state', 'failed')->count(),
            'balances' => Wallet::sum('balance_paise'),
            'pending_topups' => Topup::where('status', 'pending_approval')->count(),
        ];
        $days = collect(range(6, 0))->map(fn ($i) => ['label' => now()->subDays($i)->format('D'), 'count' => Verification::whereDate('created_at', now()->subDays($i)->toDateString())->count()]);
        $mix = (clone $month)->selectRaw('product, count(*) as c, sum(charged_paise) as rev, sum(cost_paise) as cost')->groupBy('product')->orderByDesc('c')->limit(8)->get();
        $low = User::where('role', 'customer')->whereHas('wallet', fn ($q) => $q->whereColumn('balance_paise', '<', 'users.low_balance_paise'))->with('wallet')->limit(8)->get();
        $env = ['web' => config('verifyxpeed.web_env'), 'sandbox' => SecureIdClient::for('sandbox')->configured(), 'production' => SecureIdClient::for('production')->configured(), 'pg' => app(PaymentGateway::class)->configured()];
        return view('admin.dashboard', compact('stats', 'days', 'mix', 'low', 'env'));
    }

    public function customers(Request $r)
    {
        $q = User::where('role', 'customer')->with('wallet')->withCount('verifications');
        if ($s = $r->query('kyb')) $q->where('kyb_status', $s);
        if ($t = trim((string) $r->query('q'))) $q->where(fn ($w) => $w->where('company', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%"));
        return view('admin.customers', ['users' => $q->latest('id')->paginate(25)->withQueryString(), 'f' => $r->only(['kyb', 'q'])]);
    }

    public function customer(User $user)
    {
        $user->load('wallet');
        return view('admin.customer', [
            'u' => $user,
            'txns' => $user->walletModel()->transactions()->limit(30)->get(),
            'recent' => $user->verifications()->latest('id')->limit(15)->get(),
            'keys' => $user->apiKeys()->latest('id')->get(),
        ]);
    }

    public function kyb(Request $r, User $user)
    {
        $d = $r->validate(['decision' => 'required|in:approved,rejected,pending']);
        $user->update(['kyb_status' => $d['decision'], 'approved_at' => $d['decision'] === 'approved' ? now() : null]);
        return back()->with('ok', 'Business marked ' . $d['decision']);
    }

    public function status(Request $r, User $user)
    {
        $user->update(['status' => $user->status === 'suspended' ? 'active' : 'suspended']);
        return back()->with('ok', 'Account is now ' . $user->status);
    }

    public function adjust(Request $r, User $user, WalletService $wallet)
    {
        $d = $r->validate(['type' => 'required|in:credit,debit', 'amount' => 'required|numeric|min:1|max:10000000', 'reason' => 'required|string|min:4|max:200']);
        $p = (int) round($d['amount'] * 100);
        try {
            $d['type'] === 'credit'
                ? $wallet->credit($user, $p, 'Admin credit · ' . $d['reason'], null, 'adjustment', $r->user()->id)
                : $wallet->debit($user, $p, 'Admin debit · ' . $d['reason'], null, 'adjustment', $r->user()->id);
        } catch (InsufficientBalance) {
            return back()->with('err', 'Customer does not have that much balance.');
        }
        return back()->with('ok', 'Wallet adjusted');
    }

    public function verifications(Request $r)
    {
        $q = Verification::with('user');
        if ($p = $r->query('product')) $q->where('product', $p);
        if ($s = $r->query('state')) $q->where('state', $s);
        if ($t = trim((string) $r->query('q'))) $q->where('verification_id', 'like', "%$t%");
        return view('admin.verifications', ['items' => $q->latest('id')->paginate(30)->withQueryString(), 'products' => Catalog::all(), 'f' => $r->only(['product', 'state', 'q'])]);
    }

    public function topups(Request $r)
    {
        $q = Topup::with('user');
        if ($s = $r->query('status')) $q->where('status', $s);
        return view('admin.topups', ['items' => $q->latest('id')->paginate(30)->withQueryString(), 'f' => $r->only('status'), 'bank' => config('verifyxpeed.topup.bank_details')]);
    }

    public function approveTopup(Request $r, Topup $topup, TopupService $svc)
    {
        if ($r->input('decision') === 'reject') {
            if ($topup->status === 'pending_approval') $topup->update(['status' => 'rejected', 'reviewed_by' => $r->user()->id]);
            return back()->with('ok', 'Top-up rejected');
        }
        $svc->approveBank($topup, $r->user()->id);
        return back()->with('ok', 'Approved and wallet credited 💸');
    }

    public function recheckTopup(Topup $topup, TopupService $svc)
    {
        $t = $svc->confirm($topup);
        return back()->with('ok', 'Order status: ' . ($t->gateway_response['order_status'] ?? $t->status));
    }

    public function pricing()
    {
        return view('admin.pricing', ['products' => Catalog::all(), 'prices' => ProductSetting::map()]);
    }

    public function savePricing(Request $r)
    {
        $r->validate(['price.*' => 'required|numeric|min:0', 'cost.*' => 'required|numeric|min:0']);
        foreach (Catalog::keys() as $k) {
            ProductSetting::updateOrCreate(['product' => $k], [
                'enabled' => $r->boolean("enabled.$k"),
                'price_paise' => (int) round(((float) $r->input("price.$k")) * 100),
                'cost_paise' => (int) round(((float) $r->input("cost.$k")) * 100),
            ]);
        }
        return back()->with('ok', 'Pricing saved. New prices apply to the next check.');
    }

    public function provider()
    {
        return view('admin.provider', [
            'logs' => ProviderLog::latest('id')->paginate(40),
            'cfg' => [
                'web_env' => config('verifyxpeed.web_env'),
                'sandbox_id' => config('verifyxpeed.cashfree.secureid.sandbox.client_id'),
                'prod_id' => config('verifyxpeed.cashfree.secureid.production.client_id'),
                'pg_id' => config('verifyxpeed.cashfree.pg.app_id'),
                'sandbox_sig' => (bool) SecureIdClient::for('sandbox')->signature(),
                'prod_sig' => (bool) SecureIdClient::for('production')->signature(),
                'sandbox' => SecureIdClient::for('sandbox')->configured(),
                'production' => SecureIdClient::for('production')->configured(),
                'pg' => app(PaymentGateway::class)->configured(),
                'pg_env' => app(PaymentGateway::class)->env(),
                'webhook_secureid' => route('webhooks.secureid'),
                'webhook_pg' => route('webhooks.pg'),
            ],
            'ping' => session('ping'),
            'saved' => Setting::allDecrypted(),
            'serverIp' => request()->server('SERVER_ADDR'),
        ]);
    }

    public function saveKeys(Request $r)
    {
        $d = $r->validate([
            'cf_sandbox_id' => 'nullable|string|max:120', 'cf_sandbox_secret' => 'nullable|string|max:255', 'cf_sandbox_pubkey' => 'nullable|string|max:5000',
            'cf_prod_id' => 'nullable|string|max:120', 'cf_prod_secret' => 'nullable|string|max:255', 'cf_prod_pubkey' => 'nullable|string|max:5000',
            'pg_env' => 'required|in:sandbox,production', 'pg_app_id' => 'nullable|string|max:120', 'pg_secret' => 'nullable|string|max:255',
            'web_env' => 'required|in:sandbox,production',
            'kyc_template' => 'nullable|string|max:80', 'vkyc_user_template' => 'nullable|string|max:80', 'vkyc_agent_template' => 'nullable|string|max:80',
            'bank_details' => 'nullable|string|max:500',
        ]);
        foreach (['cf_sandbox' => 'sandbox', 'cf_prod' => 'production'] as $pre => $env) {
            $pem = trim((string) ($d[$pre . '_pubkey'] ?? ''));
            if ($pem !== '') {
                if (! openssl_pkey_get_public($pem)) return back()->withInput()->with('err', "That {$env} public key doesn't look like a valid PEM key.");
                $path = storage_path("app/keys/cashfree_{$env}.pem");
                @mkdir(dirname($path), 0700, true);
                file_put_contents($path, $pem . "\n");
                @chmod($path, 0600);
                Setting::put($pre . '_pubkey', $path);
            }
            if ($r->boolean($pre . '_clear')) {
                foreach (['_id', '_secret', '_pubkey'] as $suf) Setting::put($pre . $suf, null);
            }
        }
        // secrets and ids: blank means "keep what's saved"
        foreach (['cf_sandbox_id', 'cf_sandbox_secret', 'cf_prod_id', 'cf_prod_secret', 'pg_app_id', 'pg_secret'] as $k) {
            if (filled($d[$k] ?? null)) Setting::put($k, trim($d[$k]));
        }
        if ($r->boolean('pg_clear')) { Setting::put('pg_app_id', null); Setting::put('pg_secret', null); }
        foreach (['pg_env', 'web_env', 'kyc_template', 'vkyc_user_template', 'vkyc_agent_template', 'bank_details'] as $k) {
            Setting::put($k, $d[$k] ?? null);
        }
        Setting::applyToConfig();
        if ($d['web_env'] === 'production' && ! SecureIdClient::for('production')->configured()) {
            return back()->with('err', 'Saved, but the web dashboard is set to production and production keys are empty, so checks will fail. Add them or switch back to sandbox.');
        }
        return back()->with('ok', 'Keys saved (encrypted) 🔐 Now press "Test connection".');
    }

    public function ping(Request $r)
    {
        $env = $r->input('env') === 'production' ? 'production' : 'sandbox';
        return back()->with('ping', ['env' => $env] + SecureIdClient::for($env)->ping());
    }
}
