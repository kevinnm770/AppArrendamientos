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
                </div>

                <hr>

                <div class="ql-snow">
                    <div class="ql-editor" style="padding: 0;">
                        {!! $agreement->terms !!}
                    </div>
                </div>

                <hr>

                @if ($agreement->latestAdemdum) <!-- Cambiar -->
                    <div class="border rounded p-3">
                        <p class="mb-2"><strong>Estado:</strong> {{ strtoupper($agreement->latestAdemdum->status) }}</p>
                        <p class="mb-2"><strong>Inicio:</strong> {{ optional($agreement->latestAdemdum->start_at)->format('d/m/Y') }}</p>
                        <p class="mb-3"><strong>Fin:</strong> {{ optional($agreement->latestAdemdum->end_at)->format('d/m/Y') ?? 'Sin fin' }}</p>
                        <div class="d-flex gap-2">
                            <a href="{{ route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $agreement->latestAdemdum->id]) }}"
                                class="btn btn-sm btn-light-secondary">Ver adendum</a>
                        </div>
                    </div>
                @else
                    <div class="alert alert-light-secondary mb-0">No existe un adendum creado para este contrato.</div>
                @endif

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
@endsection
