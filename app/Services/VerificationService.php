<?php

namespace App\Services;

use App\Models\ProductSetting;
use App\Models\User;
use App\Models\Verification;
use App\Services\Cashfree\CashfreeResponse;
use App\Services\Cashfree\SecureIdClient;
use App\Support\Catalog;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Runs a Cashfree Secure ID product for a customer:
 * charge wallet → call Cashfree → save result → refund automatically if the call fails.
 * "Not verified" results (e.g. PAN invalid) are charged, because the source was checked.
 */
class VerificationService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * @param array $input validated input; files as UploadedFile
     * @throws InsufficientBalance|ProductUnavailable
     */
    public function start(User $user, string $productKey, array $input, string $source = 'web', ?string $env = null, ?int $bulkJobId = null, ?string $clientRef = null, bool $charge = true): Verification
    {
        $p = Catalog::get($productKey);
        $set = ProductSetting::for($productKey);
        if (! $p || ! $set['enabled']) throw new ProductUnavailable('This check is switched off right now.');

        $env ??= config('verifyxpeed.web_env');
        $amount = $charge ? Money::withGst($set['price_paise']) : 0;

        $v = Verification::create([
            'user_id' => $user->id,
            'verification_id' => 'VX' . strtoupper((string) Str::ulid()),
            'product' => $productKey,
            'env' => $env,
            'source' => $source,
            'bulk_job_id' => $bulkJobId,
            'state' => 'processing',
            'input_masked' => $this->mask($p, $input),
            'charged_paise' => $amount,
            'cost_paise' => $set['cost_paise'],
            'client_reference' => $clientRef,
        ]);

        if ($amount > 0) {
            try {
                $this->wallet->debit($user, $amount, $p['name'] . ' · ' . $v->verification_id, $v);
            } catch (InsufficientBalance $e) {
                $v->delete();
                throw $e;
            }
        }

        $client = SecureIdClient::for($env);
        try {
            $res = $this->callCreate($client, $p, $v, $input);
        } catch (\Throwable $e) {
            report($e);
            $res = new CashfreeResponse(0, ['type' => 'internal_error', 'code' => 'internal_error', 'message' => 'Something broke on our side. You were not charged.']);
        }

        if (! $res->ok()) {
            $this->fail($v, $res);
            return $v->fresh();
        }

        if ($p['flow'] === 'sync') {
            $outcome = $this->outcome($p['outcome'], $res->data);
            $v->fill([
                'state' => 'completed',
                'outcome' => $outcome,
                'provider_status' => $this->providerStatus($res->data),
                'reference_id' => (string) ($res->get('reference_id') ?? $res->get('ref_id') ?? ''),
                'response' => ['result' => $res->data],
            ])->save();
        } else {
            $v->fill([
                'state' => 'pending',
                'provider_status' => $this->providerStatus($res->data) ?: 'CREATED',
                'reference_id' => (string) ($res->get('reference_id') ?? $res->get('ref_id') ?? ''),
                'response' => ['create' => $res->data],
            ])->save();
        }
        return $v->fresh();
    }

    /** Link flows: ask Cashfree for the latest status (free). */
    public function refresh(Verification $v): Verification
    {
        $p = $v->meta();
        if (! $p || $p['flow'] !== 'link' || $v->state !== 'pending') return $v;
        $st = $p['status'];
        $client = SecureIdClient::for($v->env);
        $res = $client->request('GET', $st['path'], [], [$st['query'] => $v->verification_id], $p['api_version'], 'json', [], $v->verification_id);
        if (! $res->ok()) {
            $v->forceFill(['error_code' => $res->errorCode(), 'error_message' => $res->errorMessage()])->save();
            return $v;
        }
        $status = strtoupper((string) data_get($res->data, $st['key'], ''));
        $resp = $v->response ?? [];
        $resp['result'] = $res->data;
        $v->response = $resp;
        $v->provider_status = $status ?: $v->provider_status;
        $v->error_code = null; $v->error_message = null;
        if (in_array($status, $st['valid'], true)) {
            $v->state = 'completed'; $v->outcome = 'valid';
        } elseif (in_array($status, $st['invalid'], true)) {
            $v->state = 'completed'; $v->outcome = 'invalid';
        }
        $v->save();
        return $v;
    }

    /** Mobile 360 step 2 */
    public function verifyOtp(Verification $v, string $otp): Verification
    {
        $p = $v->meta();
        if (! $p || $p['flow'] !== 'otp' || $v->state !== 'pending') return $v;
        $res = SecureIdClient::for($v->env)->request('POST', $p['otp']['path'], ['verification_id' => $v->verification_id, 'otp' => $otp], [], $p['api_version'], 'json', [], $v->verification_id);
        $resp = $v->response ?? [];
        if (! $res->ok()) {
            // wrong OTP etc. — keep pending so they can retry
            $v->forceFill(['error_code' => $res->errorCode(), 'error_message' => $res->errorMessage()])->save();
            return $v;
        }
        $status = strtoupper((string) $res->get('status'));
        $resp['result'] = $res->data;
        $v->fill([
            'response' => $resp,
            'provider_status' => $status,
            'state' => 'completed',
            'outcome' => in_array($status, $p['otp']['valid'], true) ? 'valid' : 'invalid',
            'error_code' => null, 'error_message' => null,
        ])->save();
        return $v;
    }

    /** DigiLocker: fetch a consented document (free). */
    public function fetchDocument(Verification $v, string $type): array
    {
        $p = $v->meta();
        $type = strtoupper($type);
        if (! $p || empty($p['documents']) || ! in_array($type, $p['documents'], true)) {
            return ['ok' => false, 'message' => 'Unknown document type'];
        }
        $res = SecureIdClient::for($v->env)->request('GET', '/digilocker/document/' . $type, [], ['verification_id' => $v->verification_id], null, 'json', [], $v->verification_id);
        if (! $res->ok()) return ['ok' => false, 'message' => $res->errorMessage()];
        $resp = $v->response ?? [];
        $resp['documents'][$type] = $res->data;
        $v->response = $resp;
        $v->save();
        return ['ok' => true, 'data' => $res->data];
    }

    /* ---------------------------------------------------------------- */

    private function callCreate(SecureIdClient $c, array $p, Verification $v, array $in): CashfreeResponse
    {
        $vid = $v->verification_id;

        if ($p['key'] === 'esign') {
            /** @var UploadedFile $doc */
            $doc = $in['document'];
            $up = $c->request('POST', '/esignature/document', [], [], null, 'multipart', ['document' => ['contents' => file_get_contents($doc->getRealPath()), 'filename' => $doc->getClientOriginalName() ?: 'document.pdf']], $vid);
            if (! $up->ok()) return $up;
            $body = [
                'verification_id' => $vid,
                'document_id' => (int) $up->get('document_id'),
                'notification_modes' => ['email'],
                'auth_type' => 'AADHAAR',
                'expiry_in_days' => (string) ($in['expiry_in_days'] ?? 7),
                'capture_location' => false,
                'signers' => [array_filter([
                    'name' => $in['signer_name'],
                    'email' => $in['signer_email'],
                    'phone' => $in['signer_phone'] ?? null,
                    'sequence' => 1,
                    'sign_positions' => [[
                        'page' => (int) ($in['page'] ?? 1),
                        'top_left_x_coordinate' => 360, 'top_left_y_coordinate' => 700,
                        'bottom_right_x_coordinate' => 560, 'bottom_right_y_coordinate' => 780,
                    ]],
                ], fn ($x) => $x !== null && $x !== '')],
            ];
            if ($r = $this->httpsUrl('public.done', $vid)) $body['redirect_url'] = $r;
            return $c->request('POST', '/esignature', $body, [], null, 'json', [], $vid);
        }

        if ($p['key'] === 'vkyc') {
            $user = $c->request('POST', '/user', array_filter(['phone' => $in['phone'], 'name' => $in['name'] ?? null, 'email' => $in['email'] ?? null, 'user_id' => $vid]), [], $p['api_version'], 'json', [], $vid);
            if (! $user->ok()) return $user;
            $body = array_filter([
                'verification_id' => $vid,
                'user_id' => $user->get('user_id', $vid),
                'user_template' => config('verifyxpeed.cashfree.vkyc_user_template'),
                'agent_template' => config('verifyxpeed.cashfree.vkyc_agent_template'),
                'notification_types' => ['sms', 'whatsapp'],
            ]);
            return $c->request('POST', '/vkyc', $body, [], $p['api_version'], 'json', [], $vid);
        }

        [$data, $files] = $this->buildBody($p, $vid, $in);
        return $c->request($p['method'], $p['path'], $data, [], $p['api_version'], $p['body'], $files, $vid);
    }

    /** @return array{0: array, 1: array} */
    public function buildBody(array $p, string $vid, array $in): array
    {
        $data = []; $files = [];
        foreach ($p['fields'] as $f) {
            $name = $f['name'];
            $val = $in[$name] ?? null;
            if ($f['type'] === 'file') {
                if ($val instanceof UploadedFile) {
                    $files[$name] = ['contents' => file_get_contents($val->getRealPath()), 'filename' => $val->getClientOriginalName() ?: ($name . '.' . ($val->extension() ?: 'jpg'))];
                }
                continue;
            }
            if ($f['type'] === 'checkbox') { if ($val) $data[$name] = true; continue; }
            if ($val === null || $val === '' || $val === []) continue;
            if (is_string($val)) {
                $val = trim($val);
                if (! empty($f['upper'])) $val = strtoupper($val);
            }
            $data[$name] = $val;
        }
        if ($p['vid']) $data = ['verification_id' => $vid] + $data;
        $data = array_merge($p['extra'], $data);

        if ($p['consent']) {
            $data['user_consent'] = [
                'obtained' => true,
                'type' => 'EXPLICIT',
                'timestamp' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
                'purpose' => 'Identity verification for customer onboarding',
            ];
        }
        if ($p['key'] === 'digilocker') {
            $data['company_name'] = mb_substr(preg_replace('/[^A-Za-z0-9 _]/', '', config('verifyxpeed.brand')), 0, 50) ?: 'VerifyXpeed';
            if ($r = $this->httpsUrl('public.done', $vid)) $data['redirect_url'] = $r;
        }
        if ($p['key'] === 'reverse_penny_drop' && ($r = $this->httpsUrl('public.done', $vid))) {
            $data['redirect_url'] = $r;
        }
        if ($p['key'] === 'kyc_link') {
            $data['template_name'] = config('verifyxpeed.cashfree.kyc_link_template');
        }
        if ($p['key'] === 'digilocker_account' && isset($data['mobile_number'])) {
            $data['mobile_number'] = substr(preg_replace('/\D/', '', $data['mobile_number']), -10);
        }
        return [$data, $files];
    }

    private function httpsUrl(string $route, string $vid): ?string
    {
        try {
            $u = route($route, $vid);
        } catch (\Throwable) {
            return null;
        }
        return str_starts_with($u, 'https://') ? $u : null;
    }

    private function fail(Verification $v, CashfreeResponse $res): void
    {
        $v->fill([
            'state' => 'failed',
            'error_code' => mb_substr((string) $res->errorCode(), 0, 80),
            'error_message' => mb_substr((string) $res->errorMessage(), 0, 250),
            'response' => ['error' => $res->data, 'http_status' => $res->status],
        ])->save();

        if ($v->charged_paise > 0 && ! $v->refunded) {
            $this->wallet->credit($v->user, $v->charged_paise, 'Refund · ' . $v->productName() . ' failed · ' . $v->verification_id, $v, 'refund');
            $v->forceFill(['refunded' => true])->save();
        }
    }

    public function outcome(string $strategy, array $d): ?string
    {
        $status = strtoupper((string) ($d['status'] ?? ''));
        return match ($strategy) {
            'valid_bool' => ! empty($d['valid']) ? 'valid' : 'invalid',
            'account_status' => strtoupper((string) ($d['account_status'] ?? '')) === 'VALID' ? 'valid' : 'invalid',
            'face_match' => ($status === 'SUCCESS' && strtoupper((string) ($d['face_match_result'] ?? '')) === 'YES') ? 'valid' : 'invalid',
            'liveness' => ($status === 'SUCCESS' && ! empty($d['liveness'])) ? 'valid' : 'invalid',
            'name_match' => in_array(strtoupper((string) ($d['reason'] ?? '')), ['DIRECT_MATCH', 'GOOD_PARTIAL_MATCH'], true) ? 'valid' : 'invalid',
            default => in_array($status, ['VALID', 'SUCCESS', 'ACCOUNT_EXISTS'], true) ? 'valid' : 'invalid',
        };
    }

    private function providerStatus(array $d): ?string
    {
        foreach (['status', 'account_status', 'form_status', 'pan_status'] as $k) {
            if (isset($d[$k]) && is_scalar($d[$k])) return mb_substr(strtoupper((string) $d[$k]), 0, 60);
        }
        if (array_key_exists('valid', $d)) return $d['valid'] ? 'VALID' : 'INVALID';
        return null;
    }

    public function mask(array $p, array $in): array
    {
        $out = [];
        foreach ($p['fields'] as $f) {
            $v = $in[$f['name']] ?? null;
            if ($v === null || $v === '' || $v === []) continue;
            if ($v instanceof UploadedFile) { $out[$f['name']] = '📎 ' . mb_substr($v->getClientOriginalName(), 0, 60); continue; }
            if (is_array($v)) { $out[$f['name']] = implode(', ', $v); continue; }
            if (is_bool($v)) { $out[$f['name']] = $v ? 'yes' : 'no'; continue; }
            $v = (string) $v;
            if (! empty($f['upper'])) $v = strtoupper($v);
            if (! empty($f['mask'])) $v = self::maskValue($v);
            $out[$f['name']] = mb_substr($v, 0, 120);
        }
        return $out;
    }

    public static function maskValue(string $v): string
    {
        if (str_contains($v, '@')) {
            [$a, $b] = explode('@', $v, 2);
            return mb_substr($a, 0, 2) . str_repeat('•', max(1, mb_strlen($a) - 2)) . '@' . $b;
        }
        $len = mb_strlen($v);
        if ($len <= 4) return str_repeat('•', $len);
        $keep = $len >= 10 ? 4 : 2;
        return mb_substr($v, 0, 2) . str_repeat('•', max(1, $len - 2 - $keep)) . mb_substr($v, -$keep);
    }
}
