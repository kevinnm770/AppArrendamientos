@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Contrato #{{ $agreement->id }}</h3>
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
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                    <div class="col-md-4">
                        <strong>Inicio:</strong> {{ optional($agreement->start_at)->format('d/m/Y') }}
                        @if ($agreement->AdemdumUpdatePeriod)
                            <p style="font-size:10pt;color:rgb(67, 94, 190);">Por adendum, <strong>{{$agreement->AdemdumUpdatePeriod->update_start_date_agreement->format('d/m/Y')}}</strong><p>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>Fin:</strong> {{ optional($agreement->end_at)->format('d/m/Y') ?? 'Sin fin' }}
                        @if ($agreement->AdemdumUpdatePeriod)
                            <p style="font-size:10pt;color:rgb(67, 94, 190);">Por adendum, <strong>{{$agreement->AdemdumUpdatePeriod->update_end_date_agreement->format('d/m/Y')}}</strong><p>
                        @endif
                    </div>
                    <div class="col-md-4"><strong>Emitido:</strong> {{ optional($agreement->created_at)->format('d/m/Y') }}</div>
                </div>

                <hr>

                @if ($agreement->status === 'canceling')
                    <form method="POST" action="{{ route('tenant.agreements.canceling-response', $agreement->id) }}" id="agreement-canceling-response-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" id="agreement-canceling-decision">

                        <div class="alert alert-warning mt-3" role="alert">
                            <h4>Cancelación de contrato</h4>
                            <p>El arrendador desea romper este contrato por la siguiente razón:</p>
                            <p>{{ $agreement->canceled_by }}</p>
                            <hr>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-dark" id="accept-canceling-button">Aceptar</button>
                                <button type="button" class="btn btn-outline-dark" id="reject-canceling-button">Rechazar</button>
                            </div>
                        </div>
                    </form>
                @endif

                <div class="ql-snow">
                    <div class="ql-editor" style="padding: 30px 0 0 0;height: 500px;max-height: 600px;overflow:auto;">
                        {!! $agreement->terms !!}
                    </div>
                </div>

                <hr>

                <h5>Lista de adendums</h5>
                @forelse ($agreement->ademdums as $ademdum)
                    <div class="border rounded p-3 mb-3">
                        <p class="mb-2"><strong>Estado:</strong> {{ strtoupper($ademdum->status) }}</p>
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
                        <form method="POST" action="{{ route('tenant.agreements.accept', $agreement->id) }}" onsubmit="return confirm('¿Seguro que deseas aceptar este contrato?');">
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
