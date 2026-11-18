<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('customer')->after('password');      // customer | admin
            $t->string('company')->nullable()->after('name');
            $t->string('phone', 20)->nullable()->after('email');
            $t->string('gstin', 20)->nullable();
            $t->string('business_type', 50)->nullable();                         // NBFC, DSA, staffing...
            $t->text('use_case')->nullable();                                    // stated lawful purpose
            $t->string('kyb_status', 20)->default('pending');                   // pending | approved | rejected
            $t->string('status', 20)->default('active');                        // active | suspended
            $t->unsignedInteger('low_balance_paise')->default(50000);
            $t->timestamp('approved_at')->nullable();
        });

        Schema::create('wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->bigInteger('balance_paise')->default(0);
            $t->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $t->string('txn_id', 40)->unique();
            $t->string('type', 20);                       // credit | debit | refund | adjustment
            $t->bigInteger('amount_paise');               // signed: + credit, - debit
            $t->bigInteger('balance_after_paise');
            $t->string('description');
            $t->nullableMorphs('source');                 // verification, topup...
            $t->foreignId('created_by')->nullable();      // admin who adjusted
            $t->timestamps();
            $t->index(['wallet_id', 'created_at']);
        });

        Schema::create('verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('verification_id', 50)->unique();  // our id, sent to Cashfree
            $t->string('product', 40);
            $t->string('env', 12)->default('sandbox');    // sandbox | production
            $t->string('source', 12)->default('web');     // web | api | bulk
            $t->foreignId('bulk_job_id')->nullable();
            $t->string('state', 20)->default('processing'); // processing | pending | completed | failed
            $t->string('outcome', 20)->nullable();        // valid | invalid | null
            $t->string('provider_status', 60)->nullable(); // raw status from Cashfree
            $t->string('reference_id', 40)->nullable();   // Cashfree reference
            $t->json('input_masked')->nullable();
            $t->longText('response')->nullable();         // encrypted JSON
            $t->string('error_code', 80)->nullable();
            $t->string('error_message')->nullable();
            $t->unsignedBigInteger('charged_paise')->default(0);
            $t->unsignedBigInteger('cost_paise')->default(0);
            $t->boolean('refunded')->default(false);
            $t->string('client_reference', 100)->nullable(); // customer's own reference (API)
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index(['product', 'created_at']);
        });

        Schema::create('api_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('mode', 10);                       // test | live
            $t->string('prefix', 16);
            $t->string('key_hash', 64)->unique();
            $t->json('ip_whitelist')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('topups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('order_id', 50)->unique();
            $t->string('method', 20);                     // pg | bank
            $t->unsignedBigInteger('amount_paise');
            $t->string('status', 20)->default('created'); // created | paid | failed | pending_approval | approved | rejected
            $t->string('payment_session_id')->nullable();
            $t->string('utr', 60)->nullable();
            $t->string('note')->nullable();
            $t->json('gateway_response')->nullable();
            $t->foreignId('reviewed_by')->nullable();
            $t->timestamp('credited_at')->nullable();
            $t->timestamps();
        });

        Schema::create('product_settings', function (Blueprint $t) {
            $t->id();
            $t->string('product', 40)->unique();
            $t->boolean('enabled')->default(true);
            $t->unsignedBigInteger('price_paise');        // selling price, before GST
            $t->unsignedBigInteger('cost_paise');         // what Cashfree charges you
            $t->timestamps();
        });

        Schema::create('provider_logs', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 30)->default('cashfree_secureid');
            $t->string('env', 12);
            $t->string('method', 8);
            $t->string('endpoint');
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->unsignedInteger('latency_ms')->nullable();
            $t->string('verification_id', 50)->nullable()->index();
            $t->string('error_code', 80)->nullable();
            $t->string('message')->nullable();
            $t->timestamps();
        });

        Schema::create('bulk_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('product', 40);
            $t->string('file_name');
            $t->string('path');
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('processed')->default(0);
            $t->unsignedInteger('valid')->default(0);
            $t->unsignedInteger('invalid')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->string('status', 20)->default('queued'); // queued | running | done | stopped
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['bulk_jobs', 'provider_logs', 'product_settings', 'topups', 'api_keys', 'verifications', 'wallet_transactions', 'wallets'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['role', 'company', 'phone', 'gstin', 'business_type', 'use_case', 'kyb_status', 'status', 'low_balance_paise', 'approved_at']);
        });
    }
};
