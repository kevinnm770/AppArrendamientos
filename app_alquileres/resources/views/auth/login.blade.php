@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('contents_auth')
    <h1 class="auth-title">Iniciar sesión</h1>
    <p class="auth-subtitle">Ingresa tus datos registrados previamente.</p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('auth.login.authenticate') }}">
        @csrf

        <div class="field">
            <label for="email">Correo electrónico</label>
            <div class="input-wrap">
                <i class="fa-solid fa-envelope"></i>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="@error('email') is-invalid @enderror"
                       placeholder="tucorreo@ejemplo.com" autocomplete="email" autofocus required>
            </div>
            @error('email')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input id="password" type="password" name="password"
                       class="@error('password') is-invalid @enderror"
                       placeholder="••••••••" autocomplete="current-password" required>
            </div>
            @error('password')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="forgot-link">
            <a href="{{ route('auth.password.request') }}">¿Olvidaste tu contraseña?</a>
        </div>

        <label class="checkbox-row">
            <input type="checkbox" name="remember">
            <span>Mantenme ingresado</span>
        </label>

        <button type="submit" class="btn btn-primary btn-lg btn-full">Iniciar sesión</button>
    </form>

    <p class="auth-footer-text">
        ¿Aún no tienes una cuenta? <a href="{{ route('auth.register') }}">Regístrate</a>
    </p>
@endsection
