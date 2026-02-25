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

    <style>
        .var{color: green;}
    </style>

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
                        No tienes propiedades que no estén ocupadas para crear un nuevo contrato.
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
                            <label for="end_at" class="form-label">Fin</label>
                            <input id="end_at" type="datetime-local" name="end_at" class="form-control"
                                value="{{ old('end_at') }}">
                        </div>

                        <hr>

                        <section class="section m-0">
                        <div class="card col-12">
                            <div class="card-header px-0">
                                <h4 class="card-title">Detalles del contrato</h4>
                            </div>
                            <div class="card-body p-0">
                                <p>Redacta el contrato completo.</p>
                                <div id="snow" style="height: 500px;max-height: 600px;overflow:auto;">
                                    {!! old('terms', '
                                    <h1 class="contract-title">CONTRATO DE ARRENDAMIENTO / HOSPEDAJE / USO DE SALÓN</h1>

                                    <br>

                                    <p><strong>Entre:</strong> <span class="var">[Nombre del arrendador / proveedor]</span>, identificado con cédula No. <span class="var">[Número]</span>, en adelante <strong>"EL ARRENDADOR"</strong> o <strong>"EL PROVEEDOR"</strong>.</p>

                                    <p><strong>Y:</strong> <span class="var">[Nombre del arrendatario / huésped / cliente]</span>, identificado con cédula No. <span class="var">[Número]</span>, en adelante <strong>"EL ARRENDATARIO"</strong> o <strong>"EL USUARIO"</strong>.</p>

                                    <p><strong>Tipo de servicio:</strong> <span class="var">[Casa / Hospedaje / Salón de eventos]</span></p>

                                    <br>

                                    <p><strong>PRIMERA - OBJETO:</strong> EL ARRENDADOR/PROVEEDOR otorga a EL ARRENDATARIO/USUARIO el uso del bien/espacio descrito como: <span class="var">[Dirección y descripción completa]</span>, incluyendo (si aplica) mobiliario/equipamiento: <span class="var">[Listado]</span>. El destino autorizado será: <span class="var">[Residencial / Hospedaje temporal / Evento]</span>. Queda prohibido variar el destino sin autorización escrita.</p>

                                    <p><strong>SEGUNDA - PLAZO, FECHAS Y HORARIOS:</strong>
                                    El servicio regirá desde <span class="var">[Fecha y hora de inicio]</span> hasta <span class="var">[Fecha y hora de finalización]</span>.
                                    <span class="var">[Para vivienda: Plazo fijo en meses / Para hospedaje: Check-in y check-out / Para salón: horario de montaje, evento y desmontaje]</span>.
                                    Cualquier prórroga o extensión deberá constar por escrito (adenda o confirmación formal).</p>

                                    <p><strong>TERCERA - PRECIO/CANON, FORMA DE PAGO Y RECARGOS:</strong>
                                    EL ARRENDATARIO/USUARIO pagará la suma de <span class="var">[Monto y moneda]</span> por concepto de <span class="var">[canon mensual / tarifa por noche / tarifa por evento]</span>.
                                    El pago deberá realizarse a más tardar el día <span class="var">[día]</span> de cada <span class="var">[mes / periodo]</span> mediante <span class="var">[transferencia / SINPE / efectivo / otro]</span>.
                                    En caso de atraso, se aplicará <span class="var">[recargo fijo / porcentaje / interés moratorio]</span> a partir de <span class="var">[día]</span>, sin perjuicio de otras acciones contractuales.</p>

                                    <p><strong>CUARTA - DEPÓSITO/GARANTÍA (SI APLICA):</strong>
                                    EL ARRENDATARIO/USUARIO entrega un depósito por la suma de <span class="var">[Monto]</span>, destinado a cubrir daños, faltantes, limpieza extraordinaria, multas o servicios pendientes. El depósito será devuelto dentro de <span class="var">[plazo]</span> tras la entrega y verificación del estado, descontando lo que corresponda con respaldo de evidencias y detalle.</p>

                                    <p><strong>QUINTA - SERVICIOS, GASTOS Y REGLAS DE USO:</strong>
                                    <span class="var">[Indicar qué incluye: agua, luz, internet, limpieza, seguridad, parqueo, etc.]</span>.
                                    EL ARRENDATARIO/USUARIO se obliga a:
                                    (a) usar el bien con diligencia y buen comportamiento; (b) respetar normas internas, aforo y horarios; (c) no realizar actos ilícitos;
                                    (d) no subarrendar ni ceder sin autorización escrita; (e) mantener orden y limpieza.
                                    <span class="var">[Para evento: niveles de sonido, uso de cocina, decoración permitida, pólvora prohibida, etc.]</span></p>

                                    <p><strong>SEXTA - INVENTARIO Y ESTADO DE ENTREGA:</strong>
                                    Las partes reconocen el estado del inmueble/espacio y bienes conforme al inventario/acta de entrega: <span class="var">[Adjunto/Link/Detalle]</span>.
                                    Cualquier daño no reportado dentro de <span class="var">[24/48]</span> horas (o antes del evento) se considerará ocurrido durante el uso, salvo prueba en contrario.</p>

                                    <p><strong>SÉTIMA - MANTENIMIENTO, DAÑOS Y RESPONSABILIDAD:</strong>
                                    EL ARRENDATARIO/USUARIO responderá por daños ocasionados por su culpa o la de sus acompañantes, visitantes, proveedores o invitados.
                                    EL ARRENDADOR/PROVEEDOR atenderá el mantenimiento correctivo que le corresponda, salvo daños imputables al uso indebido.
                                    Queda prohibido realizar modificaciones, perforaciones o instalaciones sin autorización escrita.</p>

                                    <p><strong>OCTAVA - VISITAS, OCUPACIÓN Y AFORO:</strong>
                                    La ocupación máxima será de <span class="var">[número]</span> personas.
                                    <span class="var">[Para vivienda/hospedaje: reglas de visitas, horarios, registro de huéspedes. Para salón: aforo, ingreso de proveedores, seguridad.]</span></p>

                                    <p><strong>NOVENA - POLÍTICA DE CANCELACIÓN Y REPROGRAMACIÓN (HOSPEDAJE / SALÓN):</strong>
                                    Si EL ARRENDATARIO/USUARIO cancela:
                                    - con <span class="var">[X]</span> días de anticipación: <span class="var">[reembolso total/parcial]</span>;
                                    - con menos de <span class="var">[X]</span> días: <span class="var">[no reembolsable / retención de reserva]</span>.
                                    La reprogramación estará sujeta a disponibilidad y podrá generar cargos administrativos de <span class="var">[monto]</span>.
                                    <span class="var">[Para vivienda, puedes omitir esta cláusula o adaptarla a preaviso.]</span></p>

                                    <p><strong>DÉCIMA - TERMINACIÓN / RESOLUCIÓN:</strong>
                                    El contrato podrá darse por terminado por:
                                    (a) incumplimiento de pago; (b) uso distinto al autorizado; (c) daños graves; (d) exceder aforo o perturbar la convivencia; (e) cualquier otra causal pactada o legal aplicable.
                                    En caso de terminación por incumplimiento, EL ARRENDADOR/PROVEEDOR podrá retener montos adeudados y/o el depósito en lo que corresponda, sin perjuicio de cobro de daños adicionales.</p>

                                    <p><strong>DÉCIMA PRIMERA - NOTIFICACIONES:</strong>
                                    Las comunicaciones se tendrán por válidas si se envían a:
                                    Correo EL ARRENDADOR/PROVEEDOR: <span class="var">[correo]</span> — Tel.: <span class="var">[tel]</span><br>
                                    Correo EL ARRENDATARIO/USUARIO: <span class="var">[correo]</span> — Tel.: <span class="var">[tel]</span></p>

                                    <p><strong>DÉCIMA SEGUNDA - ACUERDO INTEGRAL Y ADENDAS:</strong>
                                    Este documento contiene el acuerdo completo entre las partes. Cualquier modificación deberá constar por escrito mediante <strong>adenda</strong> firmada por ambas partes.</p>

                                    <p><strong>DÉCIMA TERCERA - FIRMA:</strong> En constancia, se firma en <span class="var">[Ciudad]</span>, a los <span class="var">[día]</span> días del mes de <span class="var">[mes]</span> del <span class="var">[año]</span>.</p>

                                    <table class="signatures" style="width:100%; margin-top:18px; border-collapse:collapse;">
                                    <tr>
                                        <td style="width:50%; padding-top:22px;">
                                        _______________________________<br>
                                        <strong>EL ARRENDADOR / PROVEEDOR</strong><br>
                                        <span class="var">[Nombre]</span> — Cédula: <span class="var">[Número]</span>
                                        </td>
                                        <td style="width:50%; padding-top:22px;">
                                        <br>_______________________________<br>
                                        <strong>EL ARRENDATARIO / USUARIO</strong><br>
                                        <span class="var">[Nombre]</span> — Cédula: <span class="var">[Número]</span>
                                        </td>
                                    </tr>
                                    </table>
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
