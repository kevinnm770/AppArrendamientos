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

    <section id="content-types">
        <div class="row">
            @forelse ($agreements as $agreement)
                @php
                    $detailsRoute = $agreement->status === 'sent'
                        ? route('admin.agreements.edit', $agreement->id)
                        : route('admin.agreements.view', $agreement->id);
                    $effectiveStartAt = $agreement->AdemdumUpdatePeriod?->update_start_date_agreement ?? $agreement->start_at;
                    $effectiveEndAt = $agreement->AdemdumUpdatePeriod?->update_end_date_agreement ?? $agreement->end_at;
                @endphp
                <div class="col-xl-4 col-md-6 col-sm-12">
                    <a href="{{ $detailsRoute }}" class="text-decoration-none text-body">
                        <div class="card" style="cursor: pointer;">
                            <div class="card-content">
                                <div class="card-body pb-2">
                                    <h4 class="card-title mb-2">{{ $agreement->roomer->legal_name}}</h4>
                                    <p class="mb-2">
                                        <i class="bi bi-calendar-check-fill"></i>
                                        {{ optional($effectiveStartAt)->format('d/m/Y') ?? 'Sin inicio' }} -
                                        {{ optional($effectiveEndAt)->format('d/m/Y') ?? 'Sin fin' }}
                                    </p>
                                    <span class="badge bg-light-{{$agreement->status==='accepted'?'success':($agreement->status==='cancelled'?'danger':'secondary')}}">{{ $agreement->status==='accepted'?'VIGENT':strtoupper($agreement->status) }}</span>
                                </div>

                                <div class="card-body pt-0 text-end">
                                    <small class="text-muted">Emitido: {{ optional($agreement->created_at)->format('d/m/Y') }}</small>
                                </div>

                                @if ($agreement->status === 'accepted')
                                    <div class="card-footer d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.ademdums.index', ['agreementId' => $agreement->id]) }}" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">Adendum</a>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger js-cancel-agreement-button"
                                            data-form-id="cancel-agreement-form-{{ $agreement->id }}"
                                            onclick="event.stopPropagation();"
                                        >
                                            Romper contrato
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>

                    @if ($agreement->status === 'accepted')
                        <form method="POST" action="{{ route('admin.agreements.canceling', $agreement->id) }}" id="cancel-agreement-form-{{ $agreement->id }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="canceled_by" class="js-cancel-reason">
                        </form>
                    @endif
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

    <script>
        window.addEventListener('load', () => {
            const buttons = document.querySelectorAll('.js-cancel-agreement-button');

            buttons.forEach((button) => {
                button.addEventListener('click', async () => {
                    const formId = button.dataset.formId;
                    const form = document.getElementById(formId);
                    const reasonInput = form?.querySelector('.js-cancel-reason');

                    if (!form || !reasonInput) {
                        return;
                    }

                    if (typeof Swal === 'undefined') {
                        const reason = prompt('Indica el motivo de cancelación:');
                        if (!reason) {
                            return;
                        }

                        reasonInput.value = reason;
                        form.submit();
                        return;
                    }

                    const result = await Swal.fire({
                        title: 'Romper contrato',
                        input: 'text',
                        inputLabel: 'Motivo de la cancelación',
                        inputPlaceholder: 'Ej: finalización anticipada del acuerdo',
                        inputValidator: (value) => !value ? 'Debes indicar un motivo.' : null,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Confirmar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#d9534f'
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    reasonInput.value = result.value;
                    form.submit();
                });
            });
        });
    </script>
@endsection
