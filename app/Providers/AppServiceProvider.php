<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) \App\Models\Setting::applyToConfig();
        } catch (\Throwable) {
            // database not ready yet (fresh install)
        }

        $by = fn (Request $r, string $name) => $name . ':' . ($r->user()?->id ?: $r->ip());
        $json = fn (int $sec) => response()->json(['error' => ['code' => 'rate_limited', 'message' => "Too many requests. Try again in {$sec}s."]], 429);
        $web = fn () => back()->with('err', 'Whoa, slow down a sec 😅 Too many attempts, try again in a minute.');

        RateLimiter::for('checks', fn (Request $r) => Limit::perMinute(60)->by($by($r, 'checks'))->response(fn () => $web()));
        RateLimiter::for('refresh', fn (Request $r) => Limit::perMinute(30)->by($by($r, 'refresh'))->response(fn () => $web()));
        RateLimiter::for('otp', fn (Request $r) => Limit::perMinute(10)->by($by($r, 'otp') . ':' . (is_object($v = $r->route('verification')) ? $v->getKey() : $v))->response(fn () => $web()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by('login:' . $r->ip() . ':' . strtolower((string) $r->input('email')))->response(fn () => back()->withErrors(['email' => 'Too many attempts. Wait a minute and try again.'])->onlyInput('email')));
        RateLimiter::for('register', fn (Request $r) => Limit::perMinute(5)->by('register:' . $r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by('api:' . ($r->attributes->get('api_key')?->id ?: $r->ip()))->response(fn (Request $req, array $h) => $json((int) ($h['Retry-After'] ?? 60))));
    }
}
