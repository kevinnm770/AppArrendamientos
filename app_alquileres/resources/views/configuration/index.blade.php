@extends('layouts.admin')

@section('content')
    <div class="row">
        <div class="col-md-6 col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Datos de usuario</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form class="form form-vertical"
                            action="{{ route('admin.configuration.user.update') }}"
                            method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')

                            <div class="container-image" style="height: 70px;width:70px;max-content;border-radius:50%;background-color:gray;">
                                @if (!empty($user?->profile_photo_path))
                                    <img src="{{ asset('storage/'.$user->profile_photo_path) }}"
                                        height="100%" width="100%" style="border-radius:50%;" id="ImgUser" alt="Imagen de usuario">
                                @else
                                    <img src="{{ asset('storage/profiles_images/UserProfile_default.png') }}"
                                        height="100%" width="100%" style="border-radius:50%;" id="ImgUser" alt="Imagen de usuario">
                                @endif
                            </div>

                            <input type="file"
                                name="profile_photo_path"
                                id="profile_photo_path"
                                class="form-control mb-3 @error('profile_photo_path') is-invalid @enderror"
                                accept="image/*"
                                onchange="previewUserImage(event)">

                            @error('profile_photo_path')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <script>
                                function previewUserImage(e) {
                                    const file = e.target.files?.[0];
                                    if (!file) return;
                                    document.getElementById('ImgUser').src = URL.createObjectURL(file);
                                }
                            </script>

                            <label for="name">Nombre de usuario</label>
                            <input type="text"
                                class="form-control mb-3 @error('name') is-invalid @enderror"
                                placeholder="User1234"
                                id="username"
                                name="name"
                                value="{{ old('name', $user->name ?? '') }}"
                                required>

                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <label for="email">Correo electrónico</label>
                            <input type="text"
                                class="form-control mb-3 @error('email') is-invalid @enderror"
                                placeholder="user@email.com"
                                id="email"
                                name="email"
                                value="{{ old('email', $user->email ?? '') }}"
                                required>

                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <button type="submit" class="btn btn-primary me-1 mb-1">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Datos de {{$datarole->role}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form class="form form-vertical"
                            action="{{ $user->isLessor()
                                    ? route('admin.configuration.lessor.update')
                                    : route('admin.configuration.lessor.update') }}"
                            method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')

                            <label for="legal_name">Nombre completo</label>
                            <input type="text"
                                class="form-control mb-3 @error('legal_name') is-invalid @enderror"
                                placeholder="Peter Smith"
                                id="legal_name"
                                name="legal_name"
                                value="{{ old('legal_name', $datarole->legal_name ?? '') }}"
                                required>

                            @error('legal_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <label for="id_number">Número de identificación gubernamental</label>
                            <input type="number"
                                class="form-control mb-3 @error('id_number') is-invalid @enderror"
                                placeholder="111222333444555"
                                id="id_number"
                                name="id_number"
                                value="{{ old('id_number', $datarole->id_number ?? '') }}"
                                required>

                            @error('id_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <label for="phone">Número telefónico</label>
                            <input type="text"
                                class="form-control mb-3 @error('phone') is-invalid @enderror"
                                placeholder="60708090"
                                id="phone"
                                name="phone"
                                value="{{ old('phone', $datarole->phone ?? '') }}" required>

                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            @if ($user->isLessor())
                                <label for="address">Dirección de residencia (opcional)</label>
                                <textarea name="address" placeholder="Juan Pérez, Calle Falsa 123, Edificio B, Apto 402, San José, Costa Rica" id="address" class="form-control mb-3 @error('address') is-invalid @enderror" cols="30" rows="3"></textarea>

                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif

                            <button type="submit" class="btn btn-primary me-1 mb-1">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
