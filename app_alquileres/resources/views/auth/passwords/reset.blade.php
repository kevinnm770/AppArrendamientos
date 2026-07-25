@extends('layouts.auth')

@section('title', 'Restablecer contraseña')

@section('contents_auth')
    <h1 class="auth-title">Restablecer contraseña</h1>
    <p class="auth-subtitle">Ingresa tu nueva contraseña para continuar.</p>

    <form method="POST" action="{{ route('auth.password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="role" value="{{ $role ?? old('role') }}">

        <div class="field">
            <label for="email">Correo electrónico</label>
            <div class="input-wrap">
                <i class="fa-solid fa-envelope"></i>
                <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}"
                       class="@error('email') is-invalid @enderror"
                       placeholder="tucorreo@ejemplo.com" autocomplete="email" autofocus required>
            </div>
            @error('email')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">Nueva contraseña</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input id="password" type="password" name="password"
                       class="@error('password') is-invalid @enderror"
                       placeholder="••••••••" autocomplete="new-password" required>
            </div>
            @error('password')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmar nueva contraseña</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       placeholder="••••••••" autocomplete="new-password" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-full">Restablecer contraseña</button>
    </form>
@endsection
