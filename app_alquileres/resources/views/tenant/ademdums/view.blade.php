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
                <h4 class="card-title">Detalle del ademdum</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                    <div class="col-md-4"><strong>Estado:</strong> {{ strtoupper($ademdum->status) }}</div>
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

                <div class="mt-4 d-flex justify-content-end gap-2">
                    @if ($ademdum->status === 'sent')
                        <button type="button" class="btn btn-primary" id="accept-ademdum-button">Aceptar</button>
                    @endif
                    <a href="{{ route('tenant.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                </div>

                @if ($ademdum->status === 'sent')
                    <form method="POST" action="{{ route('tenant.ademdums.accept', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" id="accept-ademdum-form">
                        @csrf
                        @method('PATCH')
                    </form>
                @endif
            </div>
        </div>
    </section>

    @if ($ademdum->status === 'sent')
        <script>
            window.addEventListener('load', () => {
                const acceptButton = document.getElementById('accept-ademdum-button');
                const acceptForm = document.getElementById('accept-ademdum-form');

                if (!acceptButton || !acceptForm) {
                    return;
                }

                acceptButton.addEventListener('click', async () => {
                    if (typeof Swal === 'undefined') {
                        if (confirm('¿Seguro que deseas aceptar este ademdum?')) {
                            acceptForm.submit();
                        }
                        return;
                    }

                    const result = await Swal.fire({
                        title: 'Aceptar ademdum',
                        text: 'Esta acción confirmará y bloqueará el ademdum.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, aceptar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#435ebe'
                    });

                    if (result.isConfirmed) {
                        acceptForm.submit();
                    }
                });
            });
        </script>
    @endif
@endsection
