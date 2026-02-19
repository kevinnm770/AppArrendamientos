@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Editar ademdum #{{ $ademdum->id }}</h3>
                <p class="text-subtitle text-muted">Solo se puede editar o eliminar mientras esté en estado <strong>sent</strong>.</p>
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
                <h4 class="card-title">Datos del ademdum</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                </div>

                <form id="ademdum-form" method="POST" action="{{ route('admin.ademdums.edit.update', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" class="row g-3">
                    @csrf
                    @method('PATCH')

                    <div class="col-md-6">
                        <label for="start_at" class="form-label">Inicio</label>
                        <input id="start_at" type="datetime-local" name="start_at" class="form-control"
                            value="{{ old('start_at', optional($ademdum->start_at)->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label for="end_at" class="form-label">Fin</label>
                        <input id="end_at" type="datetime-local" name="end_at" class="form-control"
                            value="{{ old('end_at', optional($ademdum->end_at)->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Detalles del ademdum</label>
                        <div id="snow" style="height: 500px;">{!! old('terms', $ademdum->terms) !!}</div>
                        <input id="terms" name="terms" type="hidden" required>
                    </div>

                    <div class="card-footer my-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-danger" id="delete-ademdum-button">
                            <i class="fa-solid fa-trash"></i> Eliminar
                        </button>
                        <div class="d-flex justify-content-end gap-2 ms-auto">
                            <a href="{{ route('admin.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.ademdums.delete', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" id="delete-ademdum-form">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>
    </section>

    <script>
        window.addEventListener('load', () => {
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

            const deleteButton = document.getElementById('delete-ademdum-button');
            const deleteForm = document.getElementById('delete-ademdum-form');

            if (deleteButton && deleteForm && typeof Swal !== 'undefined') {
                deleteButton.addEventListener('click', async function() {
                    const result = await Swal.fire({
                        title: 'Eliminar ademdum',
                        text: 'Esta acción no se puede deshacer.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc3545'
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    deleteForm.submit();
                });
            }
        });
    </script>
@endsection
