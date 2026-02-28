@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Editar contrato #{{ $agreement->id }}</h3>
                <p class="text-subtitle text-muted">Solo los contratos en estado <strong>sent</strong> se pueden editar o eliminar.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.agreements.index') }}">Agreements</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
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
                <h4 class="card-title">Datos del contrato</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                </div>

                <form id="agreement-form" method="POST" action="{{ route('admin.agreements.edit.update', $agreement->id) }}" class="row g-3" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    <div class="col-md-6">
                        <label for="start_at" class="form-label">Inicio</label>
                        <input id="start_at" type="datetime-local" name="start_at" class="form-control"
                            value="{{ old('start_at', optional($agreement->start_at)->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label for="end_at" class="form-label">Fin</label>
                        <input id="end_at" type="datetime-local" name="end_at" class="form-control"
                            value="{{ old('end_at', optional($agreement->end_at)->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div class="col-md-12">
                        <label for="signed_doc_file" class="form-label">Respaldo físico (opcional)</label>
                        <input id="signed_doc_file" type="file" name="signed_doc_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.bmp,.tiff">
                        <small class="text-muted">Formatos permitidos: PDF, JPG, PNG, WEBP, BMP o TIFF (máx. 10 MB).</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Detalles del contrato</label>
                        <div id="snow" style="height: 500px;">{!! old('terms', $agreement->terms) !!}</div>
                        <input id="terms" name="terms" type="hidden" required>
                    </div>
            </div>
                    <div class="card-footer my-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-danger" id="delete-agreement-button">
                            <i class="fa-solid fa-trash"></i> Eliminar
                        </button>
                        <div class="d-flex justify-content-end gap-2 ms-auto">
                            <a href="{{ route('admin.agreements.index') }}" class="btn btn-light-secondary">Volver</a>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </div>
                </form>

                <div class="d-flex gap-2 justify-content-start">
                    <form method="POST" action="{{ route('admin.agreements.delete', $agreement->id) }}" id="delete-agreement-form">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="token" id="delete-token-input" value="" required>
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

            const deleteButton = document.getElementById('delete-agreement-button');
            const deleteForm = document.getElementById('delete-agreement-form');
            const deleteTokenInput = document.getElementById('delete-token-input');

            if (deleteButton && deleteForm && deleteTokenInput && typeof Swal !== 'undefined') {
                deleteButton.addEventListener('click', async function() {
                    const result = await Swal.fire({
                        title: 'Eliminar contrato',
                        text: 'Para confirmar, ingresa el token de verificación enviado a tu correo.',
                        input: 'text',
                        inputPlaceholder: 'Ingresa el token',
                        inputAttributes: { maxlength: 4, autocapitalize: 'off', autocorrect: 'off' },
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc3545',
                        preConfirm: (value) => {
                            if (!value) {
                                return 'Debes ingresar el token para continuar.';
                            }
                        }
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    deleteTokenInput.value = (result.value || '').trim();
                    deleteForm.submit();
                });
            }
        });
    </script>
@endsection
