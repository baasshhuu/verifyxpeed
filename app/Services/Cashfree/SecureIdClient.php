<?php

namespace App\Services\Cashfree;

use App\Models\ProviderLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Cashfree Secure ID (https://{sandbox|api}.cashfree.com/verification).
 * Auth: x-client-id + x-client-secret, plus x-cf-signature when a 2FA public key is configured
 * (RSA-OAEP of "clientId.unixTimestamp", base64) — needed if the server IP is not whitelisted.
 */
class SecureIdClient
{
    public function __construct(private string $env = 'sandbox') {}

    public static function for(string $env): self
    {
        return new self($env === 'production' ? 'production' : 'sandbox');
    }

    public function env(): string
    {
        return $this->env;
    }

    public function cfg(): array
    {
        return config("verifyxpeed.cashfree.secureid.{$this->env}");
    }

    public function configured(): bool
    {
        $c = $this->cfg();
        return filled($c['client_id']) && filled($c['client_secret']);
    }

    public function signature(): ?string
    {
        $c = $this->cfg();
        $path = $c['public_key_path'] ?? null;
        if (! $path) return null;
        if (! str_starts_with($path, '/')) $path = base_path($path);
        if (! is_file($path)) return null;
        $key = openssl_pkey_get_public(file_get_contents($path));
        if (! $key) return null;
        $plain = $c['client_id'] . '.' . time();
        return openssl_public_encrypt($plain, $enc, $key, OPENSSL_PKCS1_OAEP_PADDING) ? base64_encode($enc) : null;
    }

    /**
     * @param array $data  JSON body, or for multipart: fields + UploadedFile/path values
     * @param array $files ['field' => ['contents' => binary, 'filename' => 'x.jpg']]
     */
    public function request(string $method, string $path, array $data = [], array $query = [], ?string $apiVersion = null, string $body = 'json', array $files = [], ?string $verificationId = null): CashfreeResponse
    {
        if (! $this->configured()) {
            return CashfreeResponse::notConfigured($this->env);
        }

        $c = $this->cfg();
        $headers = [
            'x-client-id' => $c['client_id'],
            'x-client-secret' => $c['client_secret'],
            'Accept' => 'application/json',
        ];
        if ($apiVersion) $headers['x-api-version'] = $apiVersion;
        if ($sig = $this->signature()) $headers['x-cf-signature'] = $sig;

        $url = rtrim($c['base_url'], '/') . $path;
        $started = microtime(true);

        try {
            $req = Http::withHeaders($headers)->timeout(config('verifyxpeed.cashfree.secureid.timeout', 45));
            if ($query) $url .= '?' . http_build_query($query);

            if (strtoupper($method) === 'GET') {
                $res = $req->get($url);
            } elseif ($body === 'multipart') {
                foreach ($files as $name => $f) {
                    $req = $req->attach($name, $f['contents'], $f['filename']);
                }
                $res = $req->asMultipart()->post($url, collect($data)->map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v)->all());
            } else {
                $res = $req->asJson()->post($url, $data);
            }
        } catch (ConnectionException $e) {
            $this->log($method, $path, null, $started, $verificationId, 'connection_error', $e->getMessage());
            return CashfreeResponse::connectionError($e->getMessage());
        }

        $json = $res->json();
        if (! is_array($json)) $json = ['raw' => mb_substr($res->body(), 0, 2000)];
        $r = new CashfreeResponse($res->status(), $json);
        $this->log($method, $path, $res->status(), $started, $verificationId, $r->ok() ? null : $r->errorCode(), $r->ok() ? null : $r->errorMessage());
        return $r;
    }

    private function log(string $method, string $path, ?int $status, float $started, ?string $vid, ?string $code, ?string $msg): void
    {
        try {
            ProviderLog::create([
                'env' => $this->env, 'method' => strtoupper($method), 'endpoint' => $path,
                'http_status' => $status, 'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'verification_id' => $vid, 'error_code' => $code ? mb_substr($code, 0, 80) : null,
                'message' => $msg ? mb_substr($msg, 0, 250) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Free credential check: an unknown DigiLocker id returns 404 when keys are good. */
    public function ping(): array
    {
        $r = $this->request('GET', '/digilocker', [], ['verification_id' => 'VXPING' . time()]);
        $s = $r->status;
        return match (true) {
            $r->errorCode() === 'not_configured' => ['ok' => false, 'msg' => 'Keys not added in .env yet'],
            $s === 0 => ['ok' => false, 'msg' => 'Could not reach Cashfree: ' . $r->errorMessage()],
            $s === 401 => ['ok' => false, 'msg' => 'Cashfree rejected the client ID / secret (401)'],
            $s === 403 => ['ok' => false, 'msg' => 'Keys OK but this server IP is not whitelisted, or the 2FA public key is missing (403)'],
            in_array($s, [200, 400, 404], true) => ['ok' => true, 'msg' => "Connected · Cashfree answered {$s}"],
            default => ['ok' => false, 'msg' => "Unexpected answer {$s}: " . $r->errorMessage()],
        };
    }
}
