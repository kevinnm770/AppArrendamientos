@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Ademdum #{{ $ademdum->id }}</h3>
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
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                    <div class="col-md-4"><strong>Estado:</strong> {{ strtoupper($ademdum->status) }}</div>
                    <div class="col-md-4"><strong>Inicio:</strong> {{ optional($ademdum->start_at)->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Fin:</strong> {{ optional($ademdum->end_at)->format('d/m/Y') ?? 'Sin fin' }}</div>
                    <div class="col-md-4"><strong>Cancelado en:</strong> {{ optional($ademdum->cancelled_at)->format('d/m/Y H:i') ?? 'No' }}</div>
                    <div class="col-md-8"><strong>Motivo cancelación:</strong> {{ $ademdum->cancelled_by ?? 'No' }}</div>
                    <div class="col-md-4"><strong>Emitido:</strong> {{ optional($ademdum->created_at)->format('d/m/Y') }}</div>
                    <div class="col-md-4">
                        <strong>Documento oficial firmado:</strong>
                        @if ($ademdum->signedDoc)
                            <a href="{{ route('admin.ademdums.signed-doc.download', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" class="btn btn-sm btn-light-primary ms-2">Descargar</a>
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
                    <form method="POST" action="{{ route('admin.ademdums.canceling-response', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" id="ademdum-canceling-response-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" id="ademdum-canceling-decision">
                        <div class="alert alert-warning mt-3" role="alert">
                            <h4>Desestimación de adendum (en 24h se efectuará automáticamente)</h4>
                            <p>Motivo de cancelación del adendum:</p>
                            <p>{{ $ademdum->cancelled_by }}</p>
                            <hr>
                            <button type="button" class="btn btn-outline-dark" id="reject-rejection-button">Cancelar</button>
                            <p class="mt-2" style="float: right;">Emitido: {{ $ademdum->cancelled_at }}</p>
                        </div>
                    </form>
                @endif

                <div class="mt-4 d-flex justify-content-end gap-2">
                    @if ($ademdum->status === 'accepted')
                        <button type="button" class="btn btn-warning" id="canceling-ademdum-button">Dejar sin efecto</button>
                    @endif
                    <a href="{{ route('admin.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                </div>

                @if ($ademdum->status === 'accepted')
                    <form method="POST" action="{{ route('admin.ademdums.canceling', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" id="canceling-ademdum-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="cancelled_by" id="cancelled_by">
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if ($ademdum->status === 'accepted')
        <script>
            window.addEventListener('load', () => {
                const cancelingButton = document.getElementById('canceling-ademdum-button');
                const cancelingForm = document.getElementById('canceling-ademdum-form');
                const cancelledByInput = document.getElementById('cancelled_by');

                if (!cancelingButton || !cancelingForm || !cancelledByInput) {
                    return;
                }

                cancelingButton.addEventListener('click', async () => {
                    if (typeof Swal === 'undefined') {
                        const reason = prompt('Indica el motivo de cancelación:');
                        if (!reason) {
                            return;
                        }

                        cancelledByInput.value = reason;
                        cancelingForm.submit();
                        return;
                    }

                    const result = await Swal.fire({
                        title: 'Dejar sin efecto ademdum',
                        input: 'text',
                        inputLabel: 'Motivo de cancelación',
                        inputPlaceholder: 'Ej: decisión del arrendador',
                        inputValidator: (value) => !value ? 'Debes indicar un motivo.' : null,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Confirmar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#f39c12'
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    cancelledByInput.value = result.value;
                    cancelingForm.submit();
                });
            });
        </script>
    @endif

    @if ($ademdum->status === 'canceling')
        <script>
            window.addEventListener('load', () => {
                const form = document.getElementById('ademdum-canceling-response-form');
                const decisionInput = document.getElementById('ademdum-canceling-decision');
                const rejectButton = document.getElementById('reject-rejection-button');

                if (!form || !decisionInput || !rejectButton) {
                    return;
                }

                rejectButton.addEventListener('click', () => {
                    decisionInput.value = 'reject';
                    form.submit();
                });
            });
        </script>
    @endif

@endsection
