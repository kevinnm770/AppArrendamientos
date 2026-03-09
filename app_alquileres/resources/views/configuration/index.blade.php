@php
    $layout = $user->isLessor() ? 'layouts.admin' : 'layouts.tenant';
@endphp

@extends($layout)

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
                            action="{{ $user->isLessor() ? route('admin.configuration.user.update') : route('tenant.configuration.user.update')}}"
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
                    <h4 class="card-title">Datos de {{ $datarole->role }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form class="form form-vertical"
                            action="{{ $user->isLessor() ? route('admin.configuration.lessor.update') : route('tenant.configuration.roomer.update') }}"
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

                            @if ($user->isLessor())
                                <label for="commercial_name">Nombre comercial</label>
                                <input type="text"
                                    class="form-control mb-3 @error('commercial_name') is-invalid @enderror"
                                    placeholder="Inmobiliaria ABC"
                                    id="commercial_name"
                                    name="commercial_name"
                                    value="{{ old('commercial_name', $datarole->commercial_name ?? '') }}">

                                @error('commercial_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <label for="identification_type">Tipo de identificación</label>
                                @php($selectedIdType = old('identification_type', $datarole->identification_type ?? 'fisico'))
                                <select class="form-control mb-3 @error('identification_type') is-invalid @enderror" id="identification_type" name="identification_type" required>
                                    <option value="fisico" @selected($selectedIdType === 'fisico')>Física</option>
                                    <option value="juridico" @selected($selectedIdType === 'juridico')>Jurídica</option>
                                    <option value="dimex" @selected($selectedIdType === 'dimex')>DIMEX</option>
                                    <option value="nite" @selected($selectedIdType === 'nite')>NITE</option>
                                </select>

                                @error('identification_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif

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
                                <div class="alert alert-secondary">
                                    <strong>Estado CRLibre:</strong><br>
                                    Cuenta técnica: {{ $datarole->crlibre_username ? 'Conectada' : 'Pendiente' }}<br>
                                    Certificado: {{ $datarole->certificate_code ? 'Cargado' : 'Pendiente' }}
                                    @if ($datarole->certificate_uploaded_at)
                                        ({{ $datarole->certificate_uploaded_at->format('Y-m-d H:i') }})
                                    @endif
                                </div>

                                <label for="email">Correo para facturación</label>
                                <input type="email"
                                    class="form-control mb-3 @error('email') is-invalid @enderror"
                                    placeholder="facturacion@empresa.cr"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', $datarole->email ?? $user->email ?? '') }}">

                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <label for="address">Dirección fiscal</label>
                                <textarea name="address" placeholder="Juan Pérez, Calle Falsa 123, Edificio B, Apto 402, San José, Costa Rica" id="address" class="form-control mb-3 @error('address') is-invalid @enderror" cols="30" rows="3">{{ old('address', $datarole->address ?? '') }}</textarea>

                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <div class="row">
                                    <div class="col-md-3">
                                        <label for="province">Provincia</label>
                                        <input type="text" class="form-control mb-3 @error('province') is-invalid @enderror" id="province" name="province" maxlength="1" value="{{ old('province', $datarole->province ?? '') }}" placeholder="1">
                                        @error('province')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="canton">Cantón</label>
                                        <input type="text" class="form-control mb-3 @error('canton') is-invalid @enderror" id="canton" name="canton" maxlength="2" value="{{ old('canton', $datarole->canton ?? '') }}" placeholder="01">
                                        @error('canton')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="district">Distrito</label>
                                        <input type="text" class="form-control mb-3 @error('district') is-invalid @enderror" id="district" name="district" maxlength="2" value="{{ old('district', $datarole->district ?? '') }}" placeholder="01">
                                        @error('district')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label for="barrio">Barrio</label>
                                        <input type="text" class="form-control mb-3 @error('barrio') is-invalid @enderror" id="barrio" name="barrio" maxlength="2" value="{{ old('barrio', $datarole->barrio ?? '') }}" placeholder="01">
                                        @error('barrio')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <label for="other_signs">Otras señas</label>
                                <textarea name="other_signs" id="other_signs" class="form-control mb-3 @error('other_signs') is-invalid @enderror" rows="2" placeholder="Frente al parque, edificio color azul">{{ old('other_signs', $datarole->other_signs ?? '') }}</textarea>
                                @error('other_signs')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <label for="economic_activity_code">Código de actividad económica</label>
                                <input type="text"
                                    class="form-control mb-3 @error('economic_activity_code') is-invalid @enderror"
                                    placeholder="682001"
                                    id="economic_activity_code"
                                    name="economic_activity_code"
                                    maxlength="6"
                                    value="{{ old('economic_activity_code', $datarole->economic_activity_code ?? '') }}">

                                @error('economic_activity_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <hr>
                                <h6>Credenciales estrictamente necesarias</h6>

                                <label for="certificate_file">Certificado digital (.p12)</label>
                                <input type="file"
                                    class="form-control mb-3 @error('certificate_file') is-invalid @enderror"
                                    id="certificate_file"
                                    name="certificate_file"
                                    accept=".p12,.pfx">
                                <small class="text-muted d-block mb-2">Si cargas un .p12, el sistema registrará o iniciará sesión en CRLibre y lo subirá por ti.</small>

                                @error('certificate_file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <label for="certificate_pin">PIN del certificado</label>
                                <input type="password"
                                    class="form-control mb-3 @error('certificate_pin') is-invalid @enderror"
                                    id="certificate_pin"
                                    name="certificate_pin"
                                    placeholder="Solo complétalo si deseas registrar o actualizar el certificado">

                                @error('certificate_pin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <label for="hacienda_username">Usuario de Hacienda</label>
                                <input type="text"
                                    class="form-control mb-3 @error('hacienda_username') is-invalid @enderror"
                                    id="hacienda_username"
                                    name="hacienda_username"
                                    value="{{ old('hacienda_username', $datarole->hacienda_username ?? '') }}">

                                @error('hacienda_username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <label for="hacienda_password">Contraseña de Hacienda</label>
                                <input type="password"
                                    class="form-control mb-3 @error('hacienda_password') is-invalid @enderror"
                                    id="hacienda_password"
                                    name="hacienda_password"
                                    placeholder="Solo complétala si deseas registrarla o actualizarla">

                                @error('hacienda_password')
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
