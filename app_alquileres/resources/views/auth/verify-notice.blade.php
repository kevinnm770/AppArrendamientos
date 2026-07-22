@extends('layouts.auth')

@section('title', 'Verifica tu correo')

@section('contents_auth')
    <div class="text-center">
        <div class="notice-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
        <h1 class="auth-title">Verifica tu correo electrónico</h1>
        <p class="auth-subtitle">
            Enviamos un enlace de verificación a
            <strong>{{ auth()->user()->email }}</strong>.
            Ábrelo para activar tu cuenta.
        </p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success">Te enviamos un nuevo enlace de verificación a tu correo.</div>
    @endif

    <div class="stack-gap">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-lg btn-full">Reenviar correo de verificación</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline btn-lg btn-full">Cerrar sesión</button>
        </form>
    </div>
@endsection
