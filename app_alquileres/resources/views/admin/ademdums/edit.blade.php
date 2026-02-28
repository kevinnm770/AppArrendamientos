@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Editar adendum #{{ $ademdum->id }}</h3>
                <p class="text-subtitle text-muted">El arrendatario aún lo ha aceptado por lo que puedes editarlo o eliminarlo.</p>
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
                <h4 class="card-title">Datos del adendum</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>
                </div>

                <form id="ademdum-form" method="POST" action="{{ route('admin.ademdums.edit.update', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" class="row g-3" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    <div class="col-md-6">
                        <label for="start_at" class="form-label">Inicio</label>
                        <input id="start_at" type="datetime-local" name="start_at" class="form-control"
                            value="{{ old('start_at', optional($ademdum->start_at)->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label for="end_at" class="form-label">Fin</label>
                        <input id="end_at" type="datetime-local" name="end_at" class="form-control"
                            value="{{ old('end_at', optional($ademdum->end_at)->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div class="col-12 mt-0 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="change_agreement_period" name="change_agreement_period"
                                {{ old('change_agreement_period', $ademdum->update_start_date_agreement && $ademdum->update_end_date_agreement ? 1 : 0) ? 'checked' : '' }}>
                            <label class="form-check-label" for="change_agreement_period">
                                Cambiar el periodo de vigencia del contrato
                            </label>
                            <br>
                            <small style="font-size:10pt;color:rgb(67, 94, 190);">Si lo activas, el sistema utilizará las fechas de Inicio/Fin del adendum como nuevo periodo de vigencia del contrato actual.</small>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label for="signed_doc_file" class="form-label">Respaldo físico (opcional)</label>
                        @if ($ademdum->signedDoc)
                            <div class="alert alert-light-primary py-2 mb-2">
                                Archivo actual: <strong>{{ $ademdum->signedDoc->original_name }}</strong>
                                <a href="{{ route('admin.ademdums.signed-doc.download', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]) }}" class="ms-2">Descargar</a>
                            </div>
                        @endif
                        <input id="signed_doc_file" type="file" name="signed_doc_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.bmp,.tiff">
                        <small style="font-size:10pt;color:rgb(67, 94, 190);">Solo se permite un archivo adjunto por adendum. Si cargas uno nuevo, reemplazará el actual.</small>
                        @if ($ademdum->signedDoc)
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="remove_signed_doc" name="remove_signed_doc" {{ old('remove_signed_doc') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remove_signed_doc">
                                    Eliminar archivo adjunto actual
                                </label>
                            </div>
                        @endif
                    </div>

                    <div class="col-12">
                        <label class="form-label">Detalles del adendum</label>
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
