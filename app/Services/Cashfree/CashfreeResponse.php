<?php

namespace App\Services\Cashfree;

class CashfreeResponse
{
    public function __construct(public int $status, public array $data) {}

    public static function notConfigured(string $env): self
    {
        return new self(0, ['type' => 'config_error', 'code' => 'not_configured', 'message' => "Cashfree Secure ID {$env} keys are not added yet (Integration Pending)"]);
    }

    public static function connectionError(string $msg): self
    {
        return new self(0, ['type' => 'connection_error', 'code' => 'connection_error', 'message' => 'Could not reach Cashfree: ' . $msg]);
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function errorCode(): ?string
    {
        if ($this->ok()) return null;
        return $this->data['code'] ?? $this->data['type'] ?? ('http_' . $this->status);
    }

    public function errorMessage(): ?string
    {
        if ($this->ok()) return null;
        return $this->data['message'] ?? 'Cashfree returned HTTP ' . $this->status;
    }

    /** 400/409 are mistakes in the request (bad input); everything else is provider/platform trouble. */
    public function isInputError(): bool
    {
        return in_array($this->status, [400, 404, 409, 422], true) && ($this->data['type'] ?? '') === 'validation_error';
    }

    public function get(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }
}
