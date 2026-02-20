@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Ademdum #{{ $ademdum->id }}</h3>
                <p class="text-subtitle text-muted">Este ademdum es de solo lectura.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Detalle del ademdum</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                    <div class="col-md-4"><strong>Inicio:</strong> {{ optional($ademdum->start_at)->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Fin:</strong> {{ optional($ademdum->end_at)->format('d/m/Y') ?? 'Sin fin' }}</div>
                    <div class="col-md-4"><strong>Emitido:</strong> {{ optional($ademdum->created_at)->format('d/m/Y') }}</div>
                </div>

                <hr>

                <div class="ql-snow">
                    <div class="ql-editor" style="padding: 0;">
                        {!! $ademdum->terms !!}
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <a href="{{ route('tenant.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                </div>
            </div>
        </div>
    </section>
@endsection
