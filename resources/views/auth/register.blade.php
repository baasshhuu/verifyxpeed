@extends('layouts.auth')
@section('title','Sign up')
@section('content')
<h2>Start verifying ✨</h2>
<p class="muted" style="margin:0 0 22px">Tell us about your business. We approve accounts fast (usually same day).</p>
<form method="post" action="{{ route('register') }}" data-busy="Creating account…">@csrf
  <div class="form-grid">
    <div class="field"><label>Your name</label><input type="text" name="name" value="{{ old('name') }}" required>@error('name')<span class="err-text">{{ $message }}</span>@enderror</div>
    <div class="field"><label>Business name</label><input type="text" name="company" value="{{ old('company') }}" required>@error('company')<span class="err-text">{{ $message }}</span>@enderror</div>
    <div class="field"><label>Work email</label><input type="email" name="email" value="{{ old('email') }}" required>@error('email')<span class="err-text">{{ $message }}</span>@enderror</div>
    <div class="field"><label>Mobile</label><input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="10" placeholder="10 digits">@error('phone')<span class="err-text">{{ $message }}</span>@enderror</div>
    <div class="field"><label>Business type</label>
      <select name="business_type" required>@foreach (['NBFC / Lender','Loan DSA','Fintech','Staffing / HR','Rental / Real estate','E-commerce / Marketplace','Logistics','Other'] as $bt)<option @selected(old('business_type')===$bt)>{{ $bt }}</option>@endforeach</select></div>
    <div class="field"><label>GSTIN (optional)</label><input type="text" name="gstin" value="{{ old('gstin') }}" maxlength="15" style="text-transform:uppercase">@error('gstin')<span class="err-text">{{ $message }}</span>@enderror</div>
  </div>
  <div class="field"><label>What will you verify, and why?</label><textarea name="use_case" required placeholder="e.g. PAN + bank checks for borrowers applying for personal loans">{{ old('use_case') }}</textarea>@error('use_case')<span class="err-text">{{ $message }}</span>@enderror</div>
  <div class="form-grid">
    <div class="field"><label>Password</label><input type="password" name="password" required minlength="8">@error('password')<span class="err-text">{{ $message }}</span>@enderror</div>
    <div class="field"><label>Confirm password</label><input type="password" name="password_confirmation" required></div>
  </div>
  <label class="check"><input type="checkbox" name="terms" value="1" @checked(old('terms'))> I'll only verify people who've given consent, for a lawful purpose (DPDP Act 2023), and I accept the terms.</label>
  @error('terms')<p class="err-text" style="margin-top:-8px">{{ $message }}</p>@enderror
  <button type="submit" class="btn btn-lime btn-lg btn-block">Create account 🚀</button>
</form>
<p class="muted center" style="margin-top:20px">Already have one? <a class="link" href="{{ route('login') }}">Log in</a></p>
@endsection
