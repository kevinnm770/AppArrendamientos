@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Adendum #{{ $ademdum->id }}</h3>
                <p class="text-subtitle text-muted">Este adendum es de solo lectura.</p>
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
                <h4 class="card-title">Detalle del adendum</h4>
            </div>
            <div class="card-body">
                @if ($ademdum->status === 'sent')
                    <div class="alert alert-warning">
                        Por favor lee con cuidado los términos y el contrato adjunto, ante cualquier duda consulta a el arrendador(a), no aceptes sin estar completamente seguro(a).
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                    <div class="col-md-4"><strong>Estado:</strong> {{ strtoupper($ademdum->status) }}</div>
                    <div class="col-md-4"><strong>Inicio:</strong> {{ optional($ademdum->start_at)->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Fin:</strong> {{ optional($ademdum->end_at)->format('d/m/Y') ?? 'Sin fin' }}</div>
                    <div class="col-md-4"><strong>Emitido:</strong> {{ optional($ademdum->created_at)->format('d/m/Y') }}</div>
                    <div class="col-md-4">
                        <strong>Documento oficial firmado:</strong>
                        @if ($ademdum->signedDoc)
                            <a href="{{ route('tenant.ademdums.signed-doc.download', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" class="btn btn-sm btn-light-primary ms-2">Descargar</a>
                        @else
                            No disponible
                        @endif
                    </div>
                </div>

                <hr>

                @php
                    $overridden = $ademdum->overriddenFields();
                    $badge = fn (string $field) => in_array($field, $overridden, true)
                        ? '<span class="badge bg-light-primary">Modificado por este adendum</span>'
                        : '<span class="badge bg-light-secondary">Hereda del contrato</span>';
                    $val = fn (string $field) => $ademdum->{$field} ?? $effectiveTerms[$field];
                @endphp

                <h5>Detalles de pago</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:35%;">Campo</th>
                                <th>Valor</th>
                                <th style="width:180px;">Origen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Frecuencia de pago</td>
                                <td>{{ \App\Models\Agreement::FREQUENCY_PAY_OPTIONS[$val('frequency_pay')] ?? $val('frequency_pay') }}</td>
                                <td>{!! $badge('frequency_pay') !!}</td>
                            </tr>
                            <tr>
                                <td>Día de pago</td>
                                <td>
                                    {{ $val('payment_date') }}
                                    @if ($val('payment_month'))
                                        de {{ \App\Models\Agreement::MONTHS[$val('payment_month')] ?? $val('payment_month') }}
                                    @endif
                                </td>
                                <td>{!! $badge('payment_date') !!}</td>
                            </tr>
                            <tr>
                                <td>Monto</td>
                                <td>{{ $val('currency') }} {{ number_format((float) $val('amount'), 2) }}</td>
                                <td>{!! $badge('amount') !!}</td>
                            </tr>
                            <tr>
                                <td>Depósito</td>
                                <td>{{ number_format((float) $val('deposit'), 2) }}</td>
                                <td>{!! $badge('deposit') !!}</td>
                            </tr>
                            <tr>
                                <td>Días de gracia</td>
                                <td>{{ $val('deadline_pay') }}</td>
                                <td>{!! $badge('deadline_pay') !!}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <hr>

                <h5>Política de morosidad</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:35%;">Campo</th>
                                <th>Valor</th>
                                <th style="width:180px;">Origen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Tipo de sanción</td>
                                <td>{{ \App\Models\Agreement::TYPE_SANCTION_OPTIONS[$val('type_sanction')] ?? $val('type_sanction') }}</td>
                                <td>{!! $badge('type_sanction') !!}</td>
                            </tr>
                            @if ($val('type_sanction') === 'percent')
                                <tr>
                                    <td>Porcentaje de recargo</td>
                                    <td colspan="2">{{ $val('surcharge_delay') }}%</td>
                                </tr>
                                <tr>
                                    <td>Base de cálculo</td>
                                    <td colspan="2">{{ \App\Models\Agreement::BASE_OPTIONS[$val('base')] ?? $val('base') }}</td>
                                </tr>
                            @elseif ($val('type_sanction') === 'amount_fix')
                                <tr>
                                    <td>Monto fijo de recargo</td>
                                    <td colspan="2">{{ number_format((float) $val('amount_delay'), 2) }}</td>
                                </tr>
                            @endif
                            @if ($val('type_sanction') !== 'none')
                                <tr>
                                    <td>Frecuencia de aplicación</td>
                                    <td colspan="2">{{ \App\Models\Agreement::FREQUENCY_SANCTION_OPTIONS[$val('frequency_sanction')] ?? $val('frequency_sanction') }}</td>
                                </tr>
                                <tr>
                                    <td>Días máximos de acumulación</td>
                                    <td colspan="2">{{ $val('max_days_unlimited') ? 'Sin límite' : $val('max_days') }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <hr>

                 @if ($ademdum->status === 'canceling')
                    <form method="POST" action="{{ route('tenant.ademdums.canceling-response', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" id="accept-rejection-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" id="ademdum-canceling-decision">
                        <div class="alert alert-warning mt-3" role="alert">
                            <h4>Desestimación de adendum (en 24h se efectuará automáticamente)</h4>
                            <p>El arrendador desea desestimar este adendum por la siguiente razon:</p>
                            <p>{{ $ademdum->cancelled_by }}</p>
                            <hr>
                            <button type="button" class="btn btn-dark" id="accept-rejection-button">Aceptar</button>
                            <button type="button" class="btn btn-outline-dark" id="reject-rejection-button">Rechazar</button>
                            <p class="mt-2" style="float: right;">Emitido: {{ $ademdum->cancelled_at }}</p>
                        </div>
                    </form>
                @endif

                <div class="mt-4 d-flex justify-content-end gap-2">
                    @if ($ademdum->status === 'sent')
                        <button type="button" class="btn btn-primary" id="accept-ademdum-button">Aceptar</button>
                    @endif
                    <a href="{{ route('tenant.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                </div>

                @if ($ademdum->status === 'sent')
                    <form method="POST" action="{{ route('tenant.ademdums.accept', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" id="accept-ademdum-form">
                        @csrf
                        @method('PATCH')
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if ($ademdum->status === 'sent')
        <script>
            window.addEventListener('load', () => {
                const acceptButton = document.getElementById('accept-ademdum-button');
                const acceptForm = document.getElementById('accept-ademdum-form');

                if (!acceptButton || !acceptForm) {
                    return;
                }

                acceptButton.addEventListener('click', async () => {
                    if (typeof Swal === 'undefined') {
                        if (confirm('¿Seguro que deseas aceptar este adendum?')) {
                            acceptForm.submit();
                        }
                        return;
                    }

                    const result = await Swal.fire({
                        title: 'Aceptar adendum',
                        text: 'Esta acción confirmará el adendum y no se podrá revertir.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, aceptar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#435ebe'
                    });

                    if (result.isConfirmed) {
                        acceptForm.submit();
                    }
                });
            });
        </script>
    @endif

    @if ($ademdum->status === 'canceling')
        <script>
            window.addEventListener('load', () => {
                const form = document.getElementById('accept-rejection-form');
                const decisionInput = document.getElementById('ademdum-canceling-decision');
                const acceptButton = document.getElementById('accept-rejection-button');
                const rejectButton = document.getElementById('reject-rejection-button');

                if (!form || !decisionInput || !acceptButton || !rejectButton) {
                    return;
                }

                const submitDecision = async (decision) => {
                    const decisionTitle = decision === 'accept' ? 'Aceptar desestimación' : 'Rechazar desestimación';
                    const decisionText = decision === 'accept'
                        ? 'El adendum quedará desestimado.'
                        : 'El adendum volverá a estado aceptado.';
                    const decisionConfirmButtonText = decision === 'accept' ? 'Sí, aceptar' : 'Sí, rechazar';

                    if (typeof Swal === 'undefined') {
                        if (confirm(`¿${decisionText}`)) {
                            decisionInput.value = decision;
                            form.submit();
                        }
                        return;
                    }

                    const result = await Swal.fire({
                        title: decisionTitle,
                        text: decisionText,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: decisionConfirmButtonText,
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#435ebe'
                    });

                    if (result.isConfirmed) {
                        decisionInput.value = decision;
                        form.submit();
                    }
                };

                acceptButton.addEventListener('click', () => submitDecision('accept'));
                rejectButton.addEventListener('click', () => submitDecision('reject'));
            });
        </script>
    @endif
@endsection
