@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Adendums del contrato #{{ $agreement->id }}</h3>
                <p class="text-subtitle text-muted">Gestiona los adendums vinculados a este contrato aceptado.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.agreements.index') }}">Agreements</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Adendum</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <section class="section">
            <div class="alert alert-light-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if (session('success'))
        <div class="alert alert-light-success">{{ session('success') }}</div>
    @endif

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Datos base del contrato</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                </div>

                <form id="ademdum-form" method="POST" action="{{ route('admin.ademdums.store', ['agreementId' => $agreement->id]) }}" class="row g-3">
                    @csrf

                    <div class="col-md-6">
                        <label for="start_at" class="form-label">Inicio</label>
                        <input id="start_at" type="datetime-local" name="start_at" class="form-control"
                            value="{{ old('start_at', optional($defaultData->start_at)->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label for="end_at" class="form-label">Fin</label>
                        <input id="end_at" type="datetime-local" name="end_at" class="form-control"
                            value="{{ old('end_at', optional($defaultData->end_at)->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div class="col-12 mt-0 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="change_agreement_period" name="change_agreement_period"
                                {{ old('change_agreement_period') ? 'checked' : '' }}>
                            <label class="form-check-label" for="change_agreement_period">
                                Cambiar el periodo de vigencia del contrato
                            </label>
                        </div>
                        <small class="text-muted">Si lo activas, el sistema utilizará las fechas de Inicio/Fin del adendum como nuevo periodo de vigencia del contrato actual.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Detalles del ademdum</label>
                        <div id="snow" style="height: 500px;">{!! old('terms',
                        '
                        <h1 class="contract-title">ADENDA AL CONTRATO DE ARRENDAMIENTO / HOSPEDAJE / USO DE SALÓN</h1>

                        <br>

                        <p><strong>Entre:</strong> <span class="var">[Nombre del arrendador / proveedor]</span>, cédula No. <span class="var">[Número]</span>, en adelante <strong>"EL ARRENDADOR"</strong> o <strong>"EL PROVEEDOR"</strong>.</p>

                        <p><strong>Y:</strong> <span class="var">[Nombre del arrendatario / usuario]</span>, cédula No. <span class="var">[Número]</span>, en adelante <strong>"EL ARRENDATARIO"</strong> o <strong>"EL USUARIO"</strong>.</p>

                        <p><strong>Contrato original:</strong> Suscrito el <span class="var">[fecha]</span>, sobre el bien ubicado en <span class="var">[dirección / descripción]</span>.</p>

                        <br>

                        <p><strong>PRIMERA - OBJETO DE LA ADENDA:</strong>
                        Las partes acuerdan modificar parcialmente el contrato original indicado, en los términos que se detallan a continuación:</p>

                        <br>

                        <p><strong>SEGUNDA - MODIFICACIÓN DE PLAZO:</strong>
                        Se modifica la cláusula de plazo, estableciendo que el contrato se extenderá desde <span class="var">[fecha inicio]</span> hasta <span class="var">[fecha final]</span>.
                        <span class="var">[Opcional: indicar si sustituye totalmente o amplía el plazo original]</span></p>

                        <p><strong>TERCERA - MODIFICACIÓN DE PRECIO / CANON:</strong>
                        Se modifica el monto a pagar, estableciendo un nuevo canon/tarifa de <span class="var">[monto]</span>, bajo las mismas condiciones de pago originalmente pactadas / o nuevas condiciones:
                        <span class="var">[detalle si cambia forma o fecha de pago]</span>.</p>

                        <p><strong>CUARTA - CONDICIONES ESPECIALES:</strong>
                        Se incorporan las siguientes condiciones adicionales:
                        <ul>
                            <li><span class="var">[Condición 1]</span></li>
                            <li><span class="var">[Condición 2]</span></li>
                            <li><span class="var">[Condición 3]</span></li>
                        </ul>
                        </p>

                        <p><strong>QUINTA - DEPÓSITO / GARANTÍA (SI APLICA):</strong>
                        El depósito se ajusta a <span class="var">[nuevo monto]</span> / se mantiene sin cambios / se elimina.
                        <span class="var">[detalle adicional si aplica]</span></p>

                        <p><strong>SEXTA - EFECTOS DE ESTA ADENDA:</strong>
                        La presente adenda forma parte integral del contrato original. Todas las cláusulas no modificadas expresamente en este documento continúan vigentes en todos sus extremos.</p>

                        <p><strong>SÉTIMA - INCUMPLIMIENTO:</strong>
                        El incumplimiento de las condiciones establecidas en esta adenda dará lugar a:
                        <span class="var">[opción A: dejar sin efecto la adenda y volver a condiciones originales / opción B: causal de terminación del contrato]</span>.</p>

                        <p><strong>OCTAVA - VIGENCIA:</strong>
                        La presente adenda entra en vigor a partir de <span class="var">[fecha]</span>.</p>

                        <br>

                        <p><strong>En constancia, se firma en:</strong> <span class="var">[Ciudad]</span>, a los <span class="var">[día]</span> días del mes de <span class="var">[mes]</span> del <span class="var">[año]</span>.</p>

                        <table class="signatures" style="width:100%; margin-top:18px;">
                        <tr>
                            <td style="width:50%; padding-top:20px;">
                            ___________________________<br>
                            <strong>EL ARRENDADOR / PROVEEDOR</strong><br>
                            <span class="var">[Nombre]</span>
                            </td>
                            <td style="width:50%; padding-top:20px;">
                            <br>___________________________<br>
                            <strong>EL ARRENDATARIO / USUARIO</strong><br>
                            <span class="var">[Nombre]</span>
                            </td>
                        </tr>
                        </table>
                        '
                        ) !!}</div>
                        <input id="terms" name="terms" type="hidden" required>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">Registrar ademdum</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        window.addEventListener('load', () => {
            const form = document.getElementById('ademdum-form');
            if (!form) {
                return;
            }

            const termsInput = document.getElementById('terms');
            const quillInstance = document.getElementById('snow').__quill ?? new Quill('#snow', {
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
        });
    </script>
@endsection
