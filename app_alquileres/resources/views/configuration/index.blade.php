@php
    $layout = $user->isLessor() ? 'layouts.admin' : 'layouts.tenant';
@endphp

@extends($layout)

@section('content')
    <style>
        .note-cyan {
            color: #00e5ff;
        }
    </style>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
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

                            <div class="text-center mb-3">
                                <label for="profile_photo_path" class="profile-photo-picker" title="Cambiar foto de perfil">
                                    @if (!empty($user?->profile_photo_path))
                                        <img src="{{ asset('storage/'.$user->profile_photo_path) }}" id="ImgUser" alt="Imagen de usuario">
                                    @else
                                        <img src="{{ asset('storage/profiles_images/UserProfile_default.png') }}" id="ImgUser" alt="Imagen de usuario">
                                    @endif
                                    <span class="profile-photo-overlay"><i class="bi bi-camera-fill"></i></span>
                                </label>

                                <input type="file"
                                    name="profile_photo_path"
                                    id="profile_photo_path"
                                    class="d-none @error('profile_photo_path') is-invalid @enderror"
                                    accept="image/*"
                                    onchange="previewUserImage(event)">

                                @error('profile_photo_path')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

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

                            <button type="submit" class="btn btn-primary me-1 mb-1">Guardar</button>
                        </form>

                        <hr>

                        <h5 class="mb-3">Correo electrónico</h5>
                        <p class="mb-3">Correo actual: <strong>{{ $user->email }}</strong></p>

                        @if ($pendingEmailChange)
                            <div class="alert alert-secondary">
                                Tienes una solicitud pendiente para cambiar tu correo a
                                <strong>{{ $pendingEmailChange->new_email }}</strong>.
                                Falta confirmar:
                                @if (!$pendingEmailChange->current_confirmed_at)
                                    el correo actual
                                @endif
                                @if (!$pendingEmailChange->current_confirmed_at && !$pendingEmailChange->new_confirmed_at)
                                    y
                                @endif
                                @if (!$pendingEmailChange->new_confirmed_at)
                                    el correo nuevo
                                @endif
                                . Revisa ambas bandejas de entrada.
                            </div>
                        @endif

                        <form class="form form-vertical" action="{{ route('account.email-change.request') }}" method="POST">
                            @csrf

                            <label for="new_email">Nuevo correo electrónico</label>
                            <input type="email"
                                class="form-control mb-3 @error('new_email') is-invalid @enderror"
                                placeholder="nuevo@correo.com"
                                id="new_email"
                                name="new_email"
                                value="{{ old('new_email') }}"
                                required>

                            @error('new_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="note-cyan d-block mb-3">
                                Enviaremos una autorización a tu correo actual y una verificación al correo nuevo. El cambio se aplica solo cuando ambos confirmen.
                            </small>

                            <button type="submit" class="btn btn-primary me-1 mb-1">Solicitar cambio de correo</button>
                        </form>

                        <hr>

                        <h5 class="mb-3">Contraseña</h5>

                        @if ($pendingPasswordChange)
                            <div class="alert alert-secondary">
                                Tienes una solicitud pendiente para cambiar tu contraseña. Revisa tu correo para confirmarla.
                            </div>
                        @endif

                        <form class="form form-vertical" action="{{ route('account.password-change.request') }}" method="POST">
                            @csrf

                            <label for="current_password">Contraseña actual</label>
                            <input type="password"
                                class="form-control mb-3 @error('current_password') is-invalid @enderror"
                                id="current_password"
                                name="current_password"
                                required>

                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <label for="password">Nueva contraseña</label>
                            <input type="password"
                                class="form-control mb-3 @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                required>

                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <label for="password_confirmation">Confirmar nueva contraseña</label>
                            <input type="password"
                                class="form-control mb-3"
                                id="password_confirmation"
                                name="password_confirmation"
                                required>

                            <small class="note-cyan d-block mb-3">
                                Te enviaremos un enlace de confirmación por correo. La contraseña cambiará solo cuando lo confirmes.
                            </small>

                            <button type="submit" class="btn btn-primary me-1 mb-1">Solicitar cambio de contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h4 class="card-title">Datos de {{ $datarole->role }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form id="datarole-form" class="form form-vertical"
                            action="{{ $user->isLessor() ? route('admin.configuration.lessor.update') : route('tenant.configuration.roomer.update') }}"
                            method="POST"
                            enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')

                            @if ($user->isLessor())
                                <label for="commercial_name">Nombre comercial (Opcional)</label>
                                <input type="text"
                                    class="form-control mb-3 @error('commercial_name') is-invalid @enderror"
                                    placeholder="Inmobiliaria ABC"
                                    id="commercial_name"
                                    name="commercial_name"
                                    value="{{ old('commercial_name', $datarole->commercial_name ?? '') }}">

                                @error('commercial_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif

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

                            <label for="id_number">Número de identificación gubernamental</label>
                            <input type="text"
                                inputmode="numeric"
                                class="form-control mb-1 @error('id_number') is-invalid @enderror"
                                placeholder="111222333444555"
                                id="id_number"
                                name="id_number"
                                value="{{ old('id_number', $datarole->id_number ?? '') }}"
                                required>
                            <small class="note-cyan d-block mb-3" id="id_number_hint"></small>

                            @error('id_number')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <label for="legal_name">Nombre completo</label>
                            <input type="text"
                                class="form-control mb-1 @error('legal_name') is-invalid @enderror"
                                placeholder="Se completa automáticamente con el tipo y número de identificación"
                                id="legal_name"
                                name="legal_name"
                                value="{{ old('legal_name', $datarole->legal_name ?? '') }}"
                                readonly
                                required>
                            <small class="note-cyan d-block mb-3" id="legal_name_hint">
                                Se carga automáticamente y no puede editarse manualmente: es el nombre registrado bajo esa identificación.
                            </small>

                            @error('legal_name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
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
                                <label for="email">Correo para facturación (Obligatorio para Hacienda)</label>
                                <input type="email"
                                    class="form-control mb-3 @error('email') is-invalid @enderror"
                                    placeholder="facturacion@empresa.cr"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', $datarole->email ?? $user->email ?? '') }}">

                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif

                            @php($selectedProvince = old('province', $datarole->province ?? ''))
                            @php($selectedCanton = old('canton', $datarole->canton ?? ''))
                            @php($selectedDistrict = old('district', $datarole->district ?? ''))

                            <div class="row">
                                <div class="col-md-4">
                                    <label for="province">Provincia @if ($user->isLessor()) (Obligatorio para Hacienda) @else (Opcional) @endif</label>
                                    <select class="form-select mb-1 @error('province') is-invalid @enderror" id="province" name="province">
                                        <option value="">Selecciona...</option>
                                        @foreach ($provinces as $province)
                                            <option value="{{ $province->code }}" @selected($selectedProvince === $province->code)>{{ $province->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('province')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="canton">Cantón @if ($user->isLessor()) (Obligatorio para Hacienda) @else (Opcional) @endif</label>
                                    <select class="form-select mb-1 @error('canton') is-invalid @enderror" id="canton" name="canton" data-selected="{{ $selectedCanton }}" disabled>
                                        <option value="">Selecciona la provincia primero</option>
                                    </select>
                                    @error('canton')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="district">Distrito @if ($user->isLessor()) (Obligatorio para Hacienda) @else (Opcional) @endif</label>
                                    <select class="form-select mb-1 @error('district') is-invalid @enderror" id="district" name="district" data-selected="{{ $selectedDistrict }}" disabled>
                                        <option value="">Selecciona el cantón primero</option>
                                    </select>
                                    @error('district')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <label for="barrio">Barrio (Opcional)</label>
                            <input type="text"
                                class="form-control mb-1 @error('barrio') is-invalid @enderror"
                                id="barrio"
                                name="barrio"
                                maxlength="50"
                                value="{{ old('barrio', $datarole->barrio ?? '') }}"
                                placeholder="Ej: Barrio González Lahmann">
                            <small class="note-cyan d-block mb-3">Texto libre (mínimo 5 caracteres), tal como aparece en tu dirección registrada — Hacienda no maneja un catálogo codificado de barrios.</small>
                            @error('barrio')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <button type="submit" class="btn btn-primary me-1 mb-1">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($user->isLessor())
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <h4 class="card-title">Datos de Hacienda (facturación electrónica)</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <label for="economic_activity_code">Código de actividad económica (Obligatorio para Hacienda)</label>
                            <input type="text"
                                form="datarole-form"
                                class="form-control mb-1 @error('economic_activity_code') is-invalid @enderror"
                                placeholder="6209.0"
                                id="economic_activity_code"
                                name="economic_activity_code"
                                maxlength="6"
                                value="{{ old('economic_activity_code', $datarole->economic_activity_code ?? '') }}">
                            <div id="activity_suggestions" class="mb-1"></div>
                            <small class="note-cyan d-block mb-3">Formato exacto de ATV: 4 dígitos, un punto y 1 dígito (ej. "6209.0"). Se sugiere automáticamente al escribir tu número de identificación; debe ser una de tus actividades realmente inscritas.</small>

                            @error('economic_activity_code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <label for="certificate_file">Certificado digital (.p12) (Obligatorio para Hacienda)</label>
                            <input type="file"
                                form="datarole-form"
                                class="form-control mb-1 @error('certificate_file') is-invalid @enderror"
                                id="certificate_file"
                                name="certificate_file"
                                accept=".p12,.pfx">
                            <small class="note-cyan d-block mb-1">El certificado se guarda cifrado y se usa localmente para firmar cada comprobante antes de enviarlo a Hacienda.</small>
                            <small class="d-block mb-3 text-{{ $datarole->certificate_code ? 'muted' : 'danger' }}">
                                Estado actual: {{ $datarole->certificate_code ? 'Cargado' : 'Pendiente' }}
                                @if ($datarole->certificate_uploaded_at)
                                    ({{ $datarole->certificate_uploaded_at->format('Y-m-d H:i') }})
                                @endif
                            </small>

                            @error('certificate_file')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <label for="certificate_pin">PIN del certificado (Obligatorio para Hacienda)</label>
                            <input type="password"
                                form="datarole-form"
                                class="form-control mb-1 @error('certificate_pin') is-invalid @enderror"
                                id="certificate_pin"
                                name="certificate_pin"
                                placeholder="Solo complétalo si deseas registrar o actualizar el certificado">
                            <small class="d-block mb-3 text-{{ $datarole->certificate_pin ? 'muted' : 'danger' }}">
                                Estado actual: {{ filled($datarole->certificate_pin) ? 'Configurado' : 'Pendiente' }}
                            </small>

                            @error('certificate_pin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <label for="hacienda_username">Usuario de Hacienda (Obligatorio para Hacienda)</label>
                            <input type="text"
                                form="datarole-form"
                                class="form-control mb-3 @error('hacienda_username') is-invalid @enderror"
                                id="hacienda_username"
                                name="hacienda_username"
                                value="{{ old('hacienda_username', $datarole->hacienda_username ?? '') }}">

                            @error('hacienda_username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <label for="hacienda_password">Contraseña de Hacienda (Obligatorio para Hacienda)</label>
                            <input type="password"
                                form="datarole-form"
                                class="form-control mb-1 @error('hacienda_password') is-invalid @enderror"
                                id="hacienda_password"
                                name="hacienda_password"
                                placeholder="Solo complétala si deseas registrarla o actualizarla">
                            <small class="d-block mb-3 text-{{ $datarole->hacienda_password ? 'muted' : 'danger' }}">
                                Estado actual: {{ filled($datarole->hacienda_password) ? 'Configurada' : 'Pendiente' }}
                            </small>

                            @error('hacienda_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <button type="submit" form="datarole-form" class="btn btn-primary me-1 mb-1">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Autocompletar nombre completo (solo lectura) a partir del tipo y número
            // de identificación, para arrendador e inquilino ---
            const idNumberField = document.getElementById('id_number');
            const legalNameField = document.getElementById('legal_name');
            const identificationTypeField = document.getElementById('identification_type');
            const idNumberHint = document.getElementById('id_number_hint');
            const legalNameHint = document.getElementById('legal_name_hint');
            const activitySuggestions = document.getElementById('activity_suggestions');
            const economicActivityField = document.getElementById('economic_activity_code');
            const lookupUrl = @json($user->isLessor() ? route('admin.configuration.lessor.lookup-identification') : route('tenant.configuration.roomer.lookup-identification'));

            function renderActivitySuggestions(activities) {
                if (!activitySuggestions) return;
                activitySuggestions.innerHTML = '';

                (activities || []).forEach(function(activity) {
                    if (!activity.code) return;
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm btn-outline-info me-1 mb-1';
                    button.textContent = `${activity.code} — ${activity.description || ''}`;
                    button.addEventListener('click', function() {
                        if (economicActivityField) economicActivityField.value = activity.code;
                    });
                    activitySuggestions.appendChild(button);
                });
            }

            if (idNumberField && legalNameField) {
                let lookupTimer = null;

                idNumberField.addEventListener('input', function() {
                    clearTimeout(lookupTimer);
                    const digits = idNumberField.value.replace(/\D+/g, '');

                    if (digits.length < 9) {
                        if (idNumberHint) idNumberHint.textContent = '';
                        return;
                    }

                    if (idNumberHint) idNumberHint.textContent = 'Verificando identificación...';

                    lookupTimer = setTimeout(function() {
                        fetch(`${lookupUrl}?identification=${encodeURIComponent(digits)}`, {
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                        })
                            .then(function(response) { return response.json(); })
                            .then(function(data) {
                                if (data.found && data.name) {
                                    legalNameField.value = data.name;
                                    if (identificationTypeField && data.identification_type) {
                                        identificationTypeField.value = data.identification_type;
                                    }
                                    if (idNumberHint) idNumberHint.textContent = 'Nombre y tipo de identificación cargados automáticamente.';
                                    if (legalNameHint) legalNameHint.textContent = 'Cargado automáticamente desde la identificación — no puede editarse manualmente.';
                                    renderActivitySuggestions(data.activities);
                                } else {
                                    legalNameField.value = '';
                                    if (idNumberHint) idNumberHint.textContent = data.message || 'No se encontró un registro para esa identificación.';
                                    if (legalNameHint) legalNameHint.textContent = 'No se encontró un nombre para esa identificación — se dejó en blanco.';
                                }
                            })
                            .catch(function() {
                                legalNameField.value = '';
                                if (idNumberHint) idNumberHint.textContent = 'No se pudo verificar la identificación en este momento.';
                            });
                    }, 500);
                });
            }

            // --- Selects encadenados de provincia / cantón / distrito ---
            const provinceSelect = document.getElementById('province');
            const cantonSelect = document.getElementById('canton');
            const districtSelect = document.getElementById('district');

            if (provinceSelect && cantonSelect && districtSelect) {
                const cantonsUrl = @json(route('locations.cantons'));
                const districtsUrl = @json(route('locations.districts'));

                function resetSelect(select, placeholder) {
                    select.innerHTML = `<option value="">${placeholder}</option>`;
                    select.disabled = true;
                }

                function loadCantons(provinceCode, preselect) {
                    if (!provinceCode) {
                        resetSelect(cantonSelect, 'Selecciona la provincia primero');
                        resetSelect(districtSelect, 'Selecciona el cantón primero');
                        return;
                    }

                    fetch(`${cantonsUrl}?province=${encodeURIComponent(provinceCode)}`, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                    })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            const results = data.results || [];
                            cantonSelect.innerHTML = '<option value="">Selecciona...</option>';
                            results.forEach(function(canton) {
                                const option = document.createElement('option');
                                option.value = canton.code;
                                option.textContent = canton.name;
                                if (preselect && preselect === canton.code) option.selected = true;
                                cantonSelect.appendChild(option);
                            });
                            cantonSelect.disabled = results.length === 0;

                            if (results.length === 0) {
                                cantonSelect.innerHTML = '<option value="">Aún no hay cantones cargados para esta provincia</option>';
                            }

                            if (preselect) {
                                loadDistricts(provinceCode, preselect, districtSelect.dataset.selected);
                            } else {
                                resetSelect(districtSelect, 'Selecciona el cantón primero');
                            }
                        })
                        .catch(function() {
                            resetSelect(cantonSelect, 'No se pudo cargar el catálogo');
                        });
                }

                function loadDistricts(provinceCode, cantonCode, preselect) {
                    if (!provinceCode || !cantonCode) {
                        resetSelect(districtSelect, 'Selecciona el cantón primero');
                        return;
                    }

                    fetch(`${districtsUrl}?province=${encodeURIComponent(provinceCode)}&canton=${encodeURIComponent(cantonCode)}`, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                    })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            const results = data.results || [];
                            districtSelect.innerHTML = '<option value="">Selecciona...</option>';
                            results.forEach(function(district) {
                                const option = document.createElement('option');
                                option.value = district.code;
                                option.textContent = district.name;
                                if (preselect && preselect === district.code) option.selected = true;
                                districtSelect.appendChild(option);
                            });
                            districtSelect.disabled = results.length === 0;

                            if (results.length === 0) {
                                districtSelect.innerHTML = '<option value="">Aún no hay distritos cargados para este cantón</option>';
                            }
                        })
                        .catch(function() {
                            resetSelect(districtSelect, 'No se pudo cargar el catálogo');
                        });
                }

                provinceSelect.addEventListener('change', function() {
                    loadCantons(provinceSelect.value, null);
                });

                cantonSelect.addEventListener('change', function() {
                    loadDistricts(provinceSelect.value, cantonSelect.value, null);
                });

                // Precarga si ya hay provincia/cantón/distrito guardados (edición o error de validación).
                if (provinceSelect.value) {
                    loadCantons(provinceSelect.value, cantonSelect.dataset.selected);
                }
            }
        });
    </script>
@endsection
