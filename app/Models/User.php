<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'company', 'email', 'phone', 'password', 'role', 'gstin', 'business_type', 'use_case', 'kyb_status', 'status', 'low_balance_paise', 'approved_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'approved_at' => 'datetime', 'password' => 'hashed'];
    }

    public function wallet(): HasOne { return $this->hasOne(Wallet::class); }
    public function verifications(): HasMany { return $this->hasMany(Verification::class); }
    public function apiKeys(): HasMany { return $this->hasMany(ApiKey::class); }
    public function topups(): HasMany { return $this->hasMany(Topup::class); }
    public function bulkJobs(): HasMany { return $this->hasMany(BulkJob::class); }

    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function walletModel(): Wallet
    {
        return $this->wallet ?? $this->wallet()->create(['balance_paise' => 0]);
    }

    /** null when allowed, otherwise the reason */
    public function verifyBlockReason(): ?string
    {
        if ($this->status === 'suspended') return 'Your account is suspended. Contact support.';
        if (config('verifyxpeed.require_approval') && $this->kyb_status !== 'approved' && ! $this->isAdmin()) {
            return $this->kyb_status === 'rejected'
                ? 'Your business application was not approved. Contact support.'
                : 'Your business is under review. You can run checks once an admin approves it (usually same day).';
        }
        return null;
    }

    public function displayName(): string { return $this->company ?: $this->name; }
}
