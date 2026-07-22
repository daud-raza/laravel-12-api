@extends('layouts.app')

@section('title', 'Log in')

@section('content')
<div class="auth-wrap">
    <form method="POST" action="/login" class="auth-card">
        @csrf
        <h1>Log in</h1>

        @if ($errors->any())
            <div class="auth-error">{{ $errors->first() }}</div>
        @endif

        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <label class="auth-remember">
            <input type="checkbox" name="remember"> Remember me
        </label>

        <button type="submit">Log in</button>
    </form>
</div>
@endsection
