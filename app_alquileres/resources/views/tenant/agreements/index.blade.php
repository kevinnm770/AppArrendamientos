@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Contratos</h3>
                <p class="text-subtitle text-muted">En esta sección puedes ver y fiscalizar tus contratos con tus arrendatarios.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Agreements</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section id="content-types">
        <div class="row">
            @forelse ($agreements as $agreement)
                @php
                    $detailsRoute = route('tenant.agreements.view', $agreement->id);
                @endphp
                <div class="col-xl-4 col-md-6 col-sm-12">
                    <a href="{{ $detailsRoute }}" class="text-decoration-none text-body">
                        <div class="card position-relative" style="cursor: pointer;">
                            @if ($agreement->pending_ademdums_count > 0)
                                <span class="badge rounded-pill position-absolute" style="top:-8px; right:-8px; z-index:1; background-color:#4CD2D9; color:#212C4C;" title="Adendums pendientes de aceptar">
                                    {{ $agreement->pending_ademdums_count }}
                                </span>
                            @endif
                            <div class="card-content">
                                <div class="card-body pb-2">
                                    <h6 class="text-muted mb-1">{{ $agreement->contract_number }}</h6>
                                    <h4 class="card-title mb-2">{{ $agreement->roomer->legal_name ?? 'Sin arrendatario' }}</h4>
                                    <p class="mb-2">
                                        <i class="bi bi-calendar-check-fill"></i>
                                        {{ optional($agreement->start_at)->format('d/m/Y') ?? 'Sin inicio' }} -
                                        {{ optional($agreement->end_at)->format('d/m/Y') ?? 'Sin fin' }}
                                    </p>
                                    <span class="badge bg-light-{{$agreement->status==='accepted'?'success':($agreement->status==='cancelled'?'danger':'secondary')}}">{{ $agreement->status==='accepted'?'VIGENT':strtoupper($agreement->status)}}</span>
                                </div>

                                <div class="card-body pt-0 text-end">
                                    <small class="text-muted">Emitido: {{ optional($agreement->created_at)->format('d/m/Y') }}</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light-secondary" role="alert">
                        No tienes contratos registrados todavía.
                    </div>
                </div>
            @endforelse
        </div>
    </section>
@endsection
