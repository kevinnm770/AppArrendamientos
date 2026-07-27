@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Contrato {{ $agreement->contract_number }}</h3>
                <p class="text-subtitle text-muted">Este contrato es de solo lectura.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Detalle del contrato</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                    <div class="col-md-4"><strong>Inicio:</strong> {{ optional($agreement->start_at)->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Fin:</strong> {{ optional($agreement->end_at)->format('d/m/Y') ?? 'Sin fin' }}</div>
                    <div class="col-md-4"><strong>Emitido:</strong> {{ optional($agreement->created_at)->format('d/m/Y') }}</div>
                    <div class="col-md-4">
                        <strong>Documento oficial firmado:</strong>
                        @if ($agreement->signedDoc)
                            <a href="{{ route('admin.agreements.signed-doc.download', $agreement->id) }}" class="btn btn-sm btn-light-primary ms-2">Descargar</a>
                        @else
                            No disponible
                        @endif
                    </div>
                </div>

                <hr>

                <h5>Detalles de pago</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Frecuencia de pago:</strong> {{ \App\Models\Agreement::FREQUENCY_PAY_OPTIONS[$agreement->frequency_pay] ?? $agreement->frequency_pay }}</div>
                    <div class="col-md-4">
                        <strong>Día de pago:</strong> {{ $agreement->payment_date }}
                        @if ($agreement->payment_month)
                            de {{ \App\Models\Agreement::MONTHS[$agreement->payment_month] ?? $agreement->payment_month }}
                        @endif
                    </div>
                    <div class="col-md-4"><strong>Monto:</strong> {{ $agreement->currencySymbol() }}{{ number_format((float) $agreement->amount, 2) }}</div>
                    <div class="col-md-4"><strong>Depósito:</strong> {{ $agreement->deposit !== null ? $agreement->currencySymbol() . number_format((float) $agreement->deposit, 2) : 'No aplica' }}</div>
                    <div class="col-md-4"><strong>Días de gracia:</strong> {{ $agreement->deadline_pay }}</div>
                </div>

                <hr>

                <h5>Política de morosidad</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Tipo de sanción:</strong> {{ \App\Models\Agreement::TYPE_SANCTION_OPTIONS[$agreement->type_sanction] ?? $agreement->type_sanction }}</div>
                    @if ($agreement->type_sanction === 'percent')
                        <div class="col-md-4"><strong>Porcentaje de recargo:</strong> {{ $agreement->surcharge_delay }}%</div>
                        <div class="col-md-4"><strong>Base de cálculo:</strong> {{ \App\Models\Agreement::BASE_OPTIONS[$agreement->base] ?? $agreement->base }}</div>
                    @elseif ($agreement->type_sanction === 'amount_fix')
                        <div class="col-md-4"><strong>Monto fijo de recargo:</strong> {{ $agreement->currencySymbol() }}{{ number_format((float) $agreement->amount_delay, 2) }}</div>
                    @endif
                    @if ($agreement->type_sanction !== 'none')
                        <div class="col-md-4"><strong>Frecuencia de aplicación:</strong> {{ \App\Models\Agreement::FREQUENCY_SANCTION_OPTIONS[$agreement->frequency_sanction] ?? $agreement->frequency_sanction }}</div>
                        <div class="col-md-4"><strong>Días máximos de acumulación:</strong> {{ $agreement->max_days_unlimited ? 'Sin límite' : $agreement->max_days }}</div>
                    @endif
                </div>

                <hr>

                @if ($agreement->status === 'canceling')
                    <form method="POST" action="{{ route('admin.agreements.canceling-response', $agreement->id) }}" id="agreement-canceling-response-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" id="agreement-canceling-decision">
                        <div class="alert alert-warning mt-3" role="alert">
                            <h4>Cancelación de contrato (en 24h se efectuará automáticamente)</h4>
                            <p>Motivo de cancelación de contrato:</p>
                            <p>{{ $agreement->canceled_by }}</p>
                            <hr>
                            <button type="button" class="btn btn-outline-dark" id="reject-canceling-button">Cancelar</button>
                            <p class="mt-2" style="float: right;">Emitido: {{ $agreement->cancelled_date }}</p>
                        </div>
                    </form>
                @endif

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="mb-0">Lista de adendums</h5>
                    @if ($agreement->status === 'accepted')
                        <a href="{{ route('admin.ademdums.index', ['agreementId' => $agreement->id]) }}" class="btn btn-sm btn-outline-primary">Crear adendum</a>
                    @endif
                </div>

                @forelse ($agreement->ademdums as $ademdum)
                    <div class="border rounded p-3 mb-3">
                        <p class="mb-2"><strong>Estado:</strong> <span class="badge bg-light-{{$ademdum->status==='accepted'?'success':($ademdum->status==='cancelled'?'danger':'secondary')}}">{{ $ademdum->status==='accepted'?'VIGENT':strtoupper($ademdum->status)}}</span></p>
                        <p class="mb-2"><strong>Inicio:</strong> {{ optional($ademdum->start_at)->format('d/m/Y') }}</p>
                        <p class="mb-3"><strong>Fin:</strong> {{ optional($ademdum->end_at)->format('d/m/Y') ?? 'Sin fin' }}</p>
                        <div class="d-flex gap-2">
                            @if ($ademdum->status === 'sent')
                                <a href="{{ route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" class="btn btn-sm btn-primary" style="height: max-content;">Editar</a>
                                <form class="m-0" method="POST" action="{{ route('admin.ademdums.delete', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" onsubmit="return confirm('¿Seguro que deseas eliminar este ademdum?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            @else
                                <a href="{{ route('admin.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" class="btn btn-sm btn-light-secondary">Ver adendum</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-light-secondary" role="alert">
                            Este contrato no tiene adendums registrados todavía.
                        </div>
                    </div>
                @endforelse

                <div class="mt-4 text-end">
                    <a href="{{ route('admin.agreements.index') }}" class="btn btn-light-secondary">Volver</a>
                </div>
            </div>
        </div>
    </section>

    @if ($agreement->status === 'canceling')
        <script>
            window.addEventListener('load', () => {
                const form = document.getElementById('agreement-canceling-response-form');
                const decisionInput = document.getElementById('agreement-canceling-decision');
                const rejectButton = document.getElementById('reject-canceling-button');

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
