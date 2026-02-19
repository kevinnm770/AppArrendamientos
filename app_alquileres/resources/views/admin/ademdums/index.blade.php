@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Ademdum del contrato #{{ $agreement->id }}</h3>
                <p class="text-subtitle text-muted">Gestiona el ademdum vinculado a este contrato aceptado.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.agreements.index') }}">Agreements</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ademdum</li>
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

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Datos base del contrato</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                </div>

                @if ($ademdum)
                    <div class="alert alert-light-info d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Ya existe un ademdum para este contrato (estado: <strong>{{ strtoupper($ademdum->status) }}</strong>).</span>
                        <a href="{{ $ademdum->status === 'sent' ? route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) : route('admin.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}"
                            class="btn btn-primary btn-sm">Abrir ademdum</a>
                    </div>
                @else
                    <form id="ademdum-form" method="POST" action="{{ route('admin.ademdums.store', ['agreementId' => $agreement->id]) }}" class="row g-3">
                        @csrf

                        <div class="col-md-6">
                            <label for="start_at" class="form-label">Inicio</label>
                            <input id="start_at" type="datetime-local" name="start_at" class="form-control" value="{{ old('start_at') }}" required>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="end_at" class="form-label">Fin</label>
                            <input id="end_at" type="datetime-local" name="end_at" class="form-control" value="{{ old('end_at') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Detalles del ademdum</label>
                            <div id="snow" style="height: 500px;">{!! old('terms', $agreement->terms) !!}</div>
                            <input id="terms" name="terms" type="hidden" required>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                            <button type="submit" class="btn btn-primary">Registrar ademdum</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <script>
        window.addEventListener('load', () => {
            const form = document.getElementById('ademdum-form');
            if (!form) {
                return;
            }

            const termsInput = document.getElementById('terms');
            const quillInstance = document.getElementById('snow').__quill ?? new Quill('#snow', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ align: [] }],
                        ['link', 'blockquote'],
                        ['clean']
                    ]
                }
            });

            const syncTerms = () => {
                termsInput.value = quillInstance.root.innerHTML;
            };

            syncTerms();
            quillInstance.on('text-change', syncTerms);
        });
    </script>
@endsection
