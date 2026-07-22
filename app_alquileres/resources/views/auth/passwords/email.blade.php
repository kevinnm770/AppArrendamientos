@extends('layouts.auth')

@section('title', 'Recuperar contraseña')

@section('contents_auth')
    <h1 class="auth-title">¿Olvidaste tu contraseña?</h1>
    <p class="auth-subtitle">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('auth.password.email') }}">
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

        <button type="submit" class="btn btn-primary btn-lg btn-full">Enviar enlace de recuperación</button>
    </form>

    <p class="auth-footer-text">
        ¿Ya la recordaste? <a href="{{ route('auth.login') }}">Inicia sesión</a>
    </p>
@endsection
