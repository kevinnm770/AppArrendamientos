@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Registro de contrato</h3>
                <p class="text-subtitle text-muted">Crea un contrato nuevo con validación de disponibilidad por fechas.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.agreements.index') }}">Agreements</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Register</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <section class="section">
            <div class="alert alert-light-danger">
                <h6 class="alert-heading">No se pudo registrar el contrato</h6>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Datos del contrato</h4>
            </div>
            <div class="card-body">
                @if ($properties->isEmpty())
                    <div class="alert alert-light-warning mb-0">
                        No tienes propiedades registradas para crear contratos.
                    </div>
                @else
                    <form id="agreement-form" method="POST" action="{{ route('admin.agreements.register.store') }}" class="row g-3"
                        data-roomer-lookup-url="{{ route('admin.agreements.roomer-by-id-number', ['idNumber' => '__ID__']) }}">
                        @csrf

                        <div class="col-md-6">
                            <label for="property_id" class="form-label">Propiedad</label>
                            <select id="property_id" name="property_id" class="form-select" required>
                                <option value="">Selecciona una propiedad</option>
                                @foreach ($properties as $property)
                                    <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>
                                        {{ $property->name }} ({{ $serviceTypeLabels[$property->service_type] ?? $property->service_type }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="roomer_id_number" class="form-label">Cédula del arrendatario</label>
                            <input id="roomer_id_number" name="roomer_id_number" type="text" class="form-control"
                                placeholder="Ej. 1234567890" value="{{ old('roomer_id_number', $selectedRoomer?->id_number) }}"
                                required>
                            <input id="roomer_id" name="roomer_id" type="hidden"
                                value="{{ old('roomer_id', $selectedRoomer?->id) }}" required>
                            <p id="roomer_name_preview" class="mt-2 mb-0 text-muted">
                                @if ($selectedRoomer)
                                    Arrendatario encontrado: <strong>{{ $selectedRoomer->legal_name }}</strong>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-4">
                            <label for="service_type" class="form-label">Tipo de servicio</label>
                            <select id="service_type" name="service_type" class="form-select" required>
                                <option value="">Selecciona un tipo</option>
                                @foreach ($serviceTypeLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('service_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="start_at" class="form-label">Inicio</label>
                            <input id="start_at" type="datetime-local" name="start_at" class="form-control"
                                value="{{ old('start_at') }}" required>
                        </div>

                        <div class="col-md-4 mb-4">
                            <label for="end_at" class="form-label">Fin (opcional)</label>
                            <input id="end_at" type="datetime-local" name="end_at" class="form-control"
                                value="{{ old('end_at') }}">
                        </div>

                        <div class="col-md-4 mb-4">
                            <label class="form-label d-block">Estado inicial</label>
                            <div class="alert alert-light-info mb-0 py-2">
                                El sistema asignará automáticamente el estado inicial como
                                <strong>Enviado (Sent)</strong> al registrar el contrato.
                            </div>
                        </div>

                        <hr>

                        <section class="section m-0">
                        <div class="card col-12">
                            <div class="card-header px-0">
                                <h4 class="card-title">Detalles del contrato</h4>
                            </div>
                            <div class="card-body p-0">
                                <p>Redacta el contrato completo.</p>
                                <div id="snow" style="min-height: 320px;">
                                    {!! old('terms', '
                                    <h4>CONTRATO DE ARRENDAMIENTO</h4>
                                    <p><strong>Entre:</strong> [Nombre del arrendador], identificado con cédula No. [Número], en adelante "EL ARRENDADOR".</p>
                                    <p><strong>Y:</strong> [Nombre del arrendatario], identificado con cédula No. [Número], en adelante "EL ARRENDATARIO".</p>
                                    <p><br></p>
                                    <p><strong>PRIMERA - OBJETO:</strong> EL ARRENDADOR entrega en arrendamiento el inmueble [Dirección/Descripción], para uso [residencial/comercial].</p>
                                    <p><strong>SEGUNDA - PLAZO:</strong> El presente contrato inicia el [fecha] y finaliza el [fecha], salvo prórroga o terminación anticipada.</p>
                                    <p><strong>TERCERA - CANON:</strong> EL ARRENDATARIO pagará un canon mensual de [valor], dentro de los primeros [número] días de cada mes.</p>
                                    <p><strong>CUARTA - OBLIGACIONES:</strong> Las partes se comprometen a cumplir con las obligaciones legales y contractuales correspondientes.</p>
                                    <p><strong>QUINTA - TERMINACIÓN:</strong> Cualquiera de las partes podrá terminar este contrato según las causales legales vigentes.</p>
                                    <p><br></p>
                                    <p>En constancia, se firma en [ciudad], a los [día] días del mes de [mes] de [año].</p>
                                    ') !!}
                                </div>
                                <input id="terms" name="terms" type="hidden" required>
                            </div>
                        </div>
                        </section>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.agreements.index') }}" class="btn btn-light-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Registrar contrato</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <script>
        window.addEventListener('load', () => {
            const form = document.getElementById('agreement-form');
            if (!form) {
                return;
            }

            const roomerIdNumberInput = document.getElementById('roomer_id_number');
            const roomerIdInput = document.getElementById('roomer_id');
            const roomerNamePreview = document.getElementById('roomer_name_preview');
            const termsInput = document.getElementById('terms');
            const snowEditorElement = document.getElementById('snow');

            const quillInstance = snowEditorElement.__quill ?? new Quill('#snow', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ align: [] }],
                        ['link', 'blockquote'],
                        ['clean']
                    ]
                }
            });

            const syncTerms = () => {
                termsInput.value = quillInstance.root.innerHTML;
            };

            syncTerms();
            quillInstance.on('text-change', syncTerms);

            const setRoomerFeedback = (message, type = 'muted') => {
                roomerNamePreview.classList.remove('text-muted', 'text-success', 'text-danger');
                roomerNamePreview.classList.add(`text-${type}`);
                roomerNamePreview.innerHTML = message;
            };

            const lookupRoomer = async () => {
                const idNumber = roomerIdNumberInput.value.trim();
                roomerIdInput.value = '';

                if (!idNumber) {
                    setRoomerFeedback('');
                    return;
                }

                setRoomerFeedback('Buscando arrendatario...', 'muted');

                const lookupUrl = form.dataset.roomerLookupUrl.replace('__ID__', encodeURIComponent(idNumber));

                try {
                    const response = await fetch(lookupUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (!response.ok || !data.found) {
                        setRoomerFeedback(data.message ?? 'No se encontró un arrendatario con esa cédula.', 'danger');
                        return;
                    }

                    roomerIdInput.value = data.roomer.id;
                    setRoomerFeedback(`Arrendatario encontrado: <strong>${data.roomer.legal_name}</strong>`, 'success');
                } catch (error) {
                    setRoomerFeedback('No fue posible validar la cédula en este momento.', 'danger');
                }
            };

            roomerIdNumberInput.addEventListener('change', lookupRoomer);

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                syncTerms();

                if (!roomerIdInput.value) {
                    setRoomerFeedback('Debes ingresar una cédula válida de un arrendatario existente.', 'danger');
                    roomerIdNumberInput.focus();
                    return;
                }

                if (typeof Swal === 'undefined') {
                    form.submit();
                    return;
                }

                const result = await Swal.fire({
                    title: 'Enviar contrato al arrendatario?',
                    text: 'Verifica que la información sea correcta antes de continuar. Ten en cuenta que el contrato se pondrá en vigencia cuando el arrendatario compruebe y notifique su aceptación.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, enviar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#435ebe'
                });

                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
@endsection
