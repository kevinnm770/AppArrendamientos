@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Contrato #{{ $agreement->id }}</h3>
                <p class="text-subtitle text-muted">Este contrato es de solo lectura porque su estado es <strong>{{ $agreement->status }}</strong>.</p>
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

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="mb-0">Último ademdum</h5>
                    @if ($agreement->status === 'accepted')
                        <a href="{{ route('admin.ademdums.index', ['agreementId' => $agreement->id]) }}" class="btn btn-sm btn-outline-primary">Gestionar ademdum</a>
                    @endif
                </div>

                @if ($agreement->latestAdemdum)
                    <div class="border rounded p-3">
                        <p class="mb-2"><strong>Estado:</strong> {{ strtoupper($agreement->latestAdemdum->status) }}</p>
                        <p class="mb-2"><strong>Inicio:</strong> {{ optional($agreement->latestAdemdum->start_at)->format('d/m/Y') }}</p>
                        <p class="mb-3"><strong>Fin:</strong> {{ optional($agreement->latestAdemdum->end_at)->format('d/m/Y') ?? 'Sin fin' }}</p>
                        <div class="d-flex gap-2">
                            @if ($agreement->latestAdemdum->status === 'sent')
                                <a href="{{ route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $agreement->latestAdemdum->id]) }}" class="btn btn-sm btn-primary">Editar</a>
                                <form method="POST" action="{{ route('admin.ademdums.delete', ['agreementId' => $agreement->id, 'ademdumId' => $agreement->latestAdemdum->id]) }}" onsubmit="return confirm('¿Seguro que deseas eliminar este ademdum?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            @else
                                <a href="{{ route('admin.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $agreement->latestAdemdum->id]) }}" class="btn btn-sm btn-light-secondary">Ver ademdum</a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="alert alert-light-secondary mb-0">No existe un ademdum creado para este contrato.</div>
                @endif

                <div class="mt-4 text-end">
                    <a href="{{ route('admin.agreements.index') }}" class="btn btn-light-secondary">Volver</a>
                </div>
            </div>
        </div>
    </section>
@endsection
