@extends('layouts.auth')
@section('title','Log in')
@section('content')
<h2>Welcome back 👋</h2>
<p class="muted" style="margin:0 0 24px">Log in to run checks and top up your wallet.</p>
<form method="post" action="{{ route('login') }}" data-busy="Logging in…">@csrf
  <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@company.com">@error('email')<span class="err-text">{{ $message }}</span>@enderror</div>
  <div class="field"><label>Password</label><input type="password" name="password" required placeholder="••••••••"></div>
  <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>
  <button type="submit" class="btn btn-lime btn-lg btn-block">Let's go →</button>
</form>
<p class="muted center" style="margin-top:22px">New here? <a class="link" href="{{ route('register') }}">Create a business account</a></p>
@endsection
