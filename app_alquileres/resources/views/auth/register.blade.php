@extends('layouts.auth')

@section('title', 'Crear cuenta')

@section('contents_auth')
    <h1 class="auth-title">Regístrate</h1>
    <p class="auth-subtitle">Ingresa tus datos para crear tu cuenta.</p>

    <form method="POST" action="{{ route('auth.register.store') }}">
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
            <label for="username">Nombre de usuario</label>
            <div class="input-wrap">
                <i class="fa-solid fa-at"></i>
                <input id="username" type="text" name="username" value="{{ old('username') }}"
                       class="@error('username') is-invalid @enderror"
                       placeholder="Nombre de usuario" required>
            </div>
            @error('username')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="fullname">Nombre completo</label>
            <div class="input-wrap">
                <i class="fa-solid fa-user"></i>
                <input id="fullname" type="text" name="fullname" value="{{ old('fullname') }}"
                       class="@error('fullname') is-invalid @enderror"
                       placeholder="Nombre completo" required>
            </div>
            @error('fullname')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="id_number">Identificación gubernamental</label>
            <div class="input-wrap">
                <i class="fa-solid fa-id-card"></i>
                <input id="id_number" type="text" name="id_number" value="{{ old('id_number') }}"
                       class="@error('id_number') is-invalid @enderror"
                       placeholder="Identificación gubernamental" required>
            </div>
            @error('id_number')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="phone">Número telefónico</label>
            <div class="input-wrap">
                <i class="fa-solid fa-phone"></i>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}"
                       class="@error('phone') is-invalid @enderror"
                       placeholder="Número telefónico" required>
            </div>
            @error('phone')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input id="password" type="password" name="password"
                       class="@error('password') is-invalid @enderror"
                       placeholder="••••••••" required>
            </div>
            @error('password')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="@error('password_confirmation') is-invalid @enderror"
                       placeholder="••••••••" required>
            </div>
            @error('password_confirmation')
                <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="role-toggle" role="group" aria-label="Tipo de usuario">
            <input type="radio" name="role" id="role_lessor" value="lessor">
            <label for="role_lessor">Arrendador</label>

            <input type="radio" name="role" id="role_roomer" value="roomer" checked>
            <label for="role_roomer">Inquilino</label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-full">Crear cuenta</button>
    </form>

    <p class="auth-footer-text">
        ¿Ya tienes una cuenta? <a href="{{ route('auth.login') }}">Inicia sesión</a>
    </p>
@endsection
