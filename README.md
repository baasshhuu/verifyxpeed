# VerifyXpeed ⚡

Wallet-based KYC & verification platform on **Cashfree Secure ID**. Laravel 11 · PHP 8.2+.

## What's inside
- **30 Cashfree products**: PAN, PAN Lite, PAN 360, DigiLocker (Aadhaar/PAN/DL documents), DigiLocker account check, Aadhaar masking, driving licence, voter ID, passport, vehicle RC, bank penny drop, UPI ID verify, IFSC, reverse penny drop, GSTIN, PAN→GSTINs, CIN, Udyam, PAN→Udyam, FSSAI, face match, liveness, name match, Smart OCR, Mobile 360 (OTP), employment, IP, geocoding, reverse geocoding, Aadhaar e-Sign, KYC form link, Video KYC.
- **Wallet**: integer-paise ledger with row locks; charge → call → automatic refund if Cashfree fails. Top-up via Cashfree PG (webhook + return URL, credited once) or bank transfer with admin approval.
- **Customer panel**: dashboard, run checks, history + CSV export, bulk CSV (queued, resumable), wallet, API keys, docs, settings.
- **Customer REST API** `/api/v1`: test keys → Cashfree sandbox (free), live keys → production (charged).
- **Admin panel**: business (KYB) approvals, customers + wallet adjustments, all checks, top-ups, pricing & margins per product, Cashfree connection test + full API log.
- **Security**: Cashfree results encrypted at rest, sensitive inputs masked, webhook HMAC verification, per-action rate limits, consent capture (DPDP Act).

## Install on the VPS
```bash
sudo bash deploy/install.sh verify.yourdomain.com admin@yourmail.com 'StrongPassword'
```
Then log in as admin → **Cashfree & logs** → paste your Cashfree keys → Save → Test connection. (Keys are stored encrypted; `.env` works too.)

## Cashfree setup checklist
1. Secure ID → Developers → API keys: sandbox + production client ID/secret → paste in Admin → Cashfree & logs.
2. Secure ID → Developers → Two-Factor Authentication: whitelist the server IP (or upload the public key and set `CASHFREE_SECUREID_PUBLIC_KEY`).
3. Secure ID webhooks → `https://<domain>/webhooks/cashfree/secure-id`
4. PG webhooks → `https://<domain>/webhooks/cashfree/pg`
5. Admin → Pricing: enter your real Cashfree cost per check (defaults are placeholders).
6. Switch `VX_WEB_ENV=production` when production keys are live.

## Useful commands
```bash
php artisan vx:make-admin email@x.com 'password'   # create / reset an admin
php artisan queue:work                              # bulk worker (supervisor runs this)
```
