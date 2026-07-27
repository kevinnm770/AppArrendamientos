@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Contrato {{ $agreement->contract_number }}</h3>
                <p class="text-subtitle text-muted">Este contrato es de solo lectura.</p>
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
                <h4 class="card-title">Detalle del contrato</h4>
            </div>
            <div class="card-body">
                @if ($agreement->status === 'sent')
                    <div class="alert alert-warning">
                        Por favor lee con cuidado los términos y el contrato adjunto, ante cualquier duda consulta a el arrendador(a), no aceptes sin estar completamente seguro(a).
                    </div>
                @endif

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
                            <a href="{{ route('tenant.agreements.signed-doc.download', $agreement->id) }}" class="btn btn-sm btn-light-primary ms-2">Descargar</a>
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
                    <form method="POST" action="{{ route('tenant.agreements.canceling-response', $agreement->id) }}" id="agreement-canceling-response-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" id="agreement-canceling-decision">

                        <div class="alert alert-warning mt-3" role="alert">
                            <h4>Cancelación de contrato (en 24h se efectuará automáticamente)</h4>
                            <p>El arrendador desea romper este contrato por la siguiente razón:</p>
                            <p>{{ $agreement->canceled_by }}</p>
                            <hr>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-dark" id="accept-canceling-button">Aceptar</button>
                                <button type="button" class="btn btn-outline-dark" id="reject-canceling-button">Rechazar</button>
                            </div>
                            <p class="mt-2" style="float: right;">Emitido: {{ $agreement->cancelled_date }}</p>
                        </div>
                    </form>
                @endif


                <hr>

                <h5>Lista de adendums</h5>
                @forelse ($agreement->ademdums as $ademdum)
                    <div class="border rounded p-3 mb-3">
                        <p class="mb-2"><strong>Estado:</strong> <span class="badge bg-light-{{$ademdum->status==='accepted'?'success':($ademdum->status==='cancelled'?'danger':'secondary')}}">{{ $ademdum->status==='accepted'?'VIGENT':strtoupper($ademdum->status)}}</span></p>
                        <p class="mb-2"><strong>Inicio:</strong> {{ optional($ademdum->start_at)->format('d/m/Y') }}</p>
                        <p class="mb-3"><strong>Fin:</strong> {{ optional($ademdum->end_at)->format('d/m/Y') ?? 'Sin fin' }}</p>
                        <div class="d-flex gap-2">
                            <a href="{{ route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}"
                                class="btn btn-sm btn-light-secondary">Ver adendum</a>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-light-secondary" role="alert">
                            Este contrato no tiene adendums registrados todavía.
                        </div>
                    </div>
                @endforelse

                <div class="mt-4 d-flex justify-content-end gap-2">
                    @if ($agreement->status === 'sent')
                        <form method="POST" class="m-0" action="{{ route('tenant.agreements.accept', $agreement->id) }}" onsubmit="return confirm('¿Seguro que deseas aceptar este contrato?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-primary">Aceptar</button>
                        </form>
                    @endif
                    <a href="{{ route('tenant.agreements.index') }}" class="btn btn-light-secondary">Volver</a>
                </div>
            </div>
        </div>
    </section>

    @if ($agreement->status === 'canceling')
        <script>
            window.addEventListener('load', () => {
                const form = document.getElementById('agreement-canceling-response-form');
                const decisionInput = document.getElementById('agreement-canceling-decision');
                const acceptButton = document.getElementById('accept-canceling-button');
                const rejectButton = document.getElementById('reject-canceling-button');

                if (!form || !decisionInput || !acceptButton || !rejectButton) {
                    return;
                }

                const submitDecision = async (decision) => {
                    const labels = {
                        accept: {
                            title: 'Aceptar cancelación',
                            text: 'El contrato se marcará como cancelado.',
                            confirmButtonText: 'Sí, aceptar',
                        },
                        reject: {
                            title: 'Rechazar cancelación',
                            text: 'El contrato volverá a estado accepted.',
                            confirmButtonText: 'Sí, rechazar',
                        },
                    };

                    const selected = labels[decision];
                    if (!selected) {
                        return;
                    }

                    if (typeof Swal === 'undefined') {
                        if (confirm(selected.text)) {
                            decisionInput.value = decision;
                            form.submit();
                        }
                        return;
                    }

                    const result = await Swal.fire({
                        title: selected.title,
                        text: selected.text,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: selected.confirmButtonText,
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#435ebe',
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    decisionInput.value = decision;
                    form.submit();
                };

                acceptButton.addEventListener('click', () => submitDecision('accept'));
                rejectButton.addEventListener('click', () => submitDecision('reject'));
            });
        </script>
    @endif
@endsection
