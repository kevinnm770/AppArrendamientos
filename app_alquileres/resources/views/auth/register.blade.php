@extends('layouts.auth')
@section('contents_auth')
    <h1 class="auth-title">Regístrate</h1>
    <p class="auth-subtitle mb-5">Ingresa tus datos.</p>

    <form method="POST" action="{{ route('auth.register.store') }}">
        @csrf
        <div class="form-group position-relative has-icon-left mb-4">
            <input type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror form-control-xl" name="email" autocomplete="email" placeholder="Correo electrónico" autofocus required>
            <div class="form-control-icon">
                <i class="bi bi-envelope"></i>
            </div>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <div class="form-group position-relative has-icon-left mb-4">
            <input type="text" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror form-control-xl" name="username" placeholder="Nombre de usuario" required>
            <div class="form-control-icon">
                <i class="bi bi-person"></i>
            </div>

            @error('username')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <div class="form-group position-relative has-icon-left mb-4">
            <input type="text" value="{{ old('fullname') }}" class="form-control @error('fullname') is-invalid @enderror form-control-xl" name="fullname" placeholder="Nombre completo" required>
            <div class="form-control-icon">
                <i class="bi bi-person"></i>
            </div>

            @error('fullname')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <div class="form-group position-relative has-icon-left mb-4">
            <input type="text" value="{{ old('id_number') }}" class="form-control @error('id_number') is-invalid @enderror form-control-xl" name="id_number" placeholder="Identificación gubernamental" required>
            <div class="form-control-icon">
                <i class="bi bi-person"></i>
            </div>

            @error('id_number')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <div class="form-group position-relative has-icon-left mb-4">
            <input type="text" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror form-control-xl" name="phone" placeholder="Numero telefónico" required>
            <div class="form-control-icon">
                <i class="bi bi-person"></i>
            </div>

            @error('phone')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <div class="form-group position-relative has-icon-left mb-4">
            <input type="password" class="form-control @error('password') is-invalid @enderror form-control-xl" name="password" placeholder="Contraseña" required>
            <div class="form-control-icon">
                <i class="bi bi-shield-lock"></i>
            </div>

            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <div class="form-group position-relative has-icon-left mb-4">
            <input type="password" class="form-control @error('confrim_password') is-invalid @enderror form-control-xl" name="password_confirmation" placeholder="Confirmar contraseña" required>
            <div class="form-control-icon">
                <i class="bi bi-shield-lock"></i>
            </div>

            @error('password_confirmation')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="btn-group btn-group-lg" role="group" aria-label="Tipo de usuario">
            <input type="radio" class="btn-check" name="role" id="role_lessor" value="lessor">
            <label class="btn btn-outline-primary" for="role_lessor">Arrendador</label>

            <input type="radio" class="btn-check" name="role" id="role_roomer" value="roomer" checked>
            <label class="btn btn-outline-primary" for="role_roomer">Inquilino</label>
        </div>

        <button class="btn btn-primary btn-block btn-lg shadow-lg mt-5">Sign Up</button>
    </form>
    <div class="text-center mt-5 text-lg fs-4">
        <p class='text-gray-600'>Already have an account? <a href="auth-login.html" class="font-bold">Log
                in</a>.</p>
    </div>
@endsection
