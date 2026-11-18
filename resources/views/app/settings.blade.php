@extends('layouts.app')
@section('title','Settings')
@section('content')
<div class="page-h"><div><h1 class="title">Settings ⚙️</h1><p class="sub">Your business profile and login.</p></div></div>
<div class="grid g2">
  <div class="card">
    <div class="card-h"><h3>Business profile</h3><span class="badge {{ ['approved'=>'ok','pending'=>'warn','rejected'=>'err'][$u->kyb_status] }}">KYB {{ $u->kyb_status }}</span></div>
    <form method="post" action="{{ route('settings.update') }}">@csrf
      <div class="form-grid">
        <div class="field"><label>Your name</label><input type="text" name="name" value="{{ old('name', $u->name) }}" required></div>
        <div class="field"><label>Business name</label><input type="text" name="company" value="{{ old('company', $u->company) }}" required></div>
        <div class="field"><label>Mobile</label><input type="tel" name="phone" value="{{ old('phone', $u->phone) }}" required>@error('phone')<span class="err-text">{{ $message }}</span>@enderror</div>
        <div class="field"><label>GSTIN (for invoices)</label><input type="text" name="gstin" value="{{ old('gstin', $u->gstin) }}" maxlength="15" style="text-transform:uppercase">@error('gstin')<span class="err-text">{{ $message }}</span>@enderror</div>
        <div class="field"><label>Low-balance alert (₹)</label><input type="number" name="low_balance" value="{{ old('low_balance', $u->low_balance_paise / 100) }}" min="0"></div>
        <div class="field"><label>Email</label><input type="email" value="{{ $u->email }}" disabled></div>
      </div>
      <button class="btn btn-lime" type="submit">Save</button>
    </form>
  </div>
  <div class="card">
    <div class="card-h"><h3>Change password 🔒</h3></div>
    <form method="post" action="{{ route('settings.password') }}">@csrf
      <div class="field"><label>Current password</label><input type="password" name="current_password" required>@error('current_password')<span class="err-text">{{ $message }}</span>@enderror</div>
      <div class="form-grid"><div class="field"><label>New password</label><input type="password" name="password" required minlength="8">@error('password')<span class="err-text">{{ $message }}</span>@enderror</div><div class="field"><label>Confirm</label><input type="password" name="password_confirmation" required></div></div>
      <button class="btn" type="submit">Update password</button>
    </form>
  </div>
</div>
@endsection
