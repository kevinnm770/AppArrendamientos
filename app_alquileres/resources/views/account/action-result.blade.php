@extends('layouts.auth')

@section('title', $title)

@section('contents_auth')
    <div class="text-center">
        <div class="notice-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <h1 class="auth-title">{{ $title }}</h1>
        <p class="auth-subtitle">{{ $message }}</p>
    </div>

    <a href="{{ route('auth.login') }}" class="btn btn-primary btn-lg btn-full">Ir a iniciar sesión</a>
@endsection
