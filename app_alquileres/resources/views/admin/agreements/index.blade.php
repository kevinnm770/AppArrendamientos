@extends('layouts.admin')

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
                    $detailsRoute = $agreement->status === 'sent'
                        ? route('admin.agreements.edit', $agreement->id)
                        : route('admin.agreements.view', $agreement->id);
                    $effectiveStartAt = $agreement->latestAdemdum?->start_at ?? $agreement->start_at;
                    $effectiveEndAt = $agreement->latestAdemdum?->end_at ?? $agreement->end_at;
                @endphp
                <div class="col-xl-4 col-md-6 col-sm-12">
                    <a href="{{ $detailsRoute }}" class="text-decoration-none text-body">
                        <div class="card" style="cursor: pointer;">
                            <div class="card-content">
                                <div class="card-body pb-2">
                                    <h4 class="card-title mb-2">{{ $agreement->roomer->legal_name ?? 'Sin arrendatario' }}</h4>
                                    <p class="mb-2">
                                        <i class="bi bi-calendar-check-fill"></i>
                                        {{ optional($effectiveStartAt)->format('d/m/Y') ?? 'Sin inicio' }} -
                                        {{ optional($effectiveEndAt)->format('d/m/Y') ?? 'Sin fin' }}
                                    </p>
                                    <span class="badge bg-light-secondary">{{ strtoupper($agreement->status) }}</span>
                                </div>

                                <div class="card-body pt-0 text-end">
                                    <small class="text-muted">Emitido: {{ optional($agreement->created_at)->format('d/m/Y') }}</small>
                                </div>

                                @if ($agreement->status === 'accepted')
                                    <div class="card-footer d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.ademdums.index', ['agreementId' => $agreement->id]) }}" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">Ademdum</a>
                                        <button type="button" class="btn btn-sm btn-outline-danger">Romper contrato</button>
                                    </div>
                                @endif
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
