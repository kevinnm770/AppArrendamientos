@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Propiedades</h3>
                <p class="text-subtitle text-muted">Completa la información principal de la propiedad y sus fotografías.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item" aria-current="page"><a href="{{ route('admin.properties.index') }}">Properties</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Register</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <form class="card" method="POST" action="#" enctype="multipart/form-data">
            @csrf

            <div class="card-header">
                <h5 class="card-title mb-0">Registrar propiedad</h5>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="name">Nombre de la propiedad *</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Ej: Casa Los Robles" required>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="service_type">Tipo de servicio *</label>
                        <select class="form-select" id="service_type" name="service_type" required>
                            <option value="" selected disabled>Selecciona una opción</option>
                            <option value="home">Hogar</option>
                            <option value="lodging">Hospedaje</option>
                            <option value="event">Evento</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Describe los espacios, reglas y beneficios de la propiedad"></textarea>
                    </div>

                    <div class="col-4">
                        <label class="form-label" for="location_province">Provincia</label>
                        <select class="form-control" name="location_province" id="location_province">
                            <option value="Cartago">Cartago</option>
                            <option value="San José">San José</option>
                            <option value="Alajuela">Alajuela</option>
                            <option value="Heredia">Heredia</option>
                            <option value="Limón">Limón</option>
                            <option value="Puntarenas">Puntarenas</option>
                            <option value="Guanacaste">Guanacaste</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="location_canton">Cantón</label>
                        <select class="form-control" name="location_canton" id="location_canton">
                            <option value="Cartago">Cartago</option>
                            <option value="San José">San José</option>
                            <option value="Alajuela">Alajuela</option>
                            <option value="Heredia">Heredia</option>
                            <option value="Limón">Limón</option>
                            <option value="Puntarenas">Puntarenas</option>
                            <option value="Guanacaste">Guanacaste</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="location_district">Districto</label>
                        <select class="form-control" name="location_district" id="location_district">
                            <option value="Cartago">Cartago</option>
                            <option value="San José">San José</option>
                            <option value="Alajuela">Alajuela</option>
                            <option value="Heredia">Heredia</option>
                            <option value="Limón">Limón</option>
                            <option value="Puntarenas">Puntarenas</option>
                            <option value="Guanacaste">Guanacaste</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="location_text">Diracción exacta *</label>
                        <input type="text" class="form-control" id="location_text" name="location_text"
                            placeholder="Ej: Del boulevard, 300m este, 3ra casa" required>
                    </div>

                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="rooms">Habitaciones</label>
                        <input type="number" class="form-control" id="rooms" name="rooms" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="living_rooms">Salas</label>
                        <input type="number" class="form-control" id="living_rooms" name="living_rooms" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="kitchens">Cocinas</label>
                        <input type="number" class="form-control" id="kitchens" name="kitchens" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="bathrooms">Baños</label>
                        <input type="number" class="form-control" id="bathrooms" name="bathrooms" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="yards">Patios</label>
                        <input type="number" class="form-control" id="yards" name="yards" min="0" value="0">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="garages_capacity">Garajes</label>
                        <input type="number" class="form-control" id="garages_capacity" name="garages_capacity" min="0" value="0">
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="materials_input">Materiales (tags)</label>
                        <input type="text" class="form-control tag-source" id="materials_input"
                            data-target="materials_tags" placeholder="Escribe y presiona Enter (ej: piso cerámica)">
                        <small class="text-muted">Presiona Enter o coma para crear cada material.</small>
                        <input type="hidden" name="materials" id="materials_tags">
                        <div class="tag-list mt-2" data-list-for="materials_tags"></div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="included_objects_input">Objetos incluidos (tags)</label>
                        <input type="text" class="form-control tag-source" id="included_objects_input"
                            data-target="included_objects_tags" placeholder="Escribe y presiona Enter (ej: refrigeradora)">
                        <small class="text-muted">Presiona Enter o coma para crear cada objeto.</small>
                        <input type="hidden" name="included_objects" id="included_objects_tags">
                        <div class="tag-list mt-2" data-list-for="included_objects_tags"></div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label" for="status">Estado *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" selected>Activo</option>
                            <option value="inactive">Inactivo</option>
                            <option value="archived">Archivado</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="mb-0">Fotografías de la propiedad</h5>
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="add-photo-row">
                                <i class="fa-solid fa-plus"></i> Agregar foto
                            </button>
                        </div>
                    </div>

                    <div class="col-12" id="photo-rows"></div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('admin.properties.index') }}" class="btn btn-light-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar propiedad</button>
            </div>
        </form>
    </section>

    <template id="photo-row-template">
        <div class="p-3 mb-3 photo-row">
            <input type="number" style="display:none;" class="form-control photo-position" name="photos[][position]" min="1" readonly>
            <div class="row g-3">
                <div class="col-xl-3 col-12 text-center">
                    <img class="photo_img" src="{{ asset('storage/photos_properties/photoDefault_property.png') }}" alt="" style="width:70%;max-width:250px;max-height:250px;">
                </div>
                <div class="col-xl-9 col-12 photo-row-content">
                    <div class="row" style="height: min-content;">
                        <div class="col-12 col-lg-4">
                            <label class="form-label">Archivo de imagen *</label>
                            <input type="file" class="form-control input_img" name="photos[][file]" accept="image/*" data-photo-number="" onchange="previewPhoto(this,event)" required>
                        </div>
                        <div class="col-12 col-lg-3 mt-xl-0 mt-3">
                            <label class="form-label">Fecha de toma</label>
                            <input type="datetime-local" class="form-control" name="photos[][taken_at]">
                        </div>
                        <div class="col-12 mt-3">
                            <label class="form-label">Descripción / caption</label>
                            <input type="text" class="form-control" name="photos[][caption]" placeholder="Ej: Vista de sala principal">
                        </div>
                        <div class="col-12 mt-3">
                            <button type="button" class="btn btn-outline-danger remove-photo-row w-100" style="max-width:max-content;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <style>
        .tag-list {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .tag-chip {
            background: #dbeafe;
            color: #0b4fd6;
            border-radius: 9999px;
            padding: .35rem .7rem;
            font-size: .85rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .tag-chip button {
            border: 0;
            background: transparent;
            color: inherit;
            padding: 0;
            line-height: 1;
        }

        .photo-row-content{
            display: flex;
            align-items:center;
            justify-content: center;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const parseTags = (value) => {
                try {
                    const parsed = JSON.parse(value || '[]');
                    return Array.isArray(parsed) ? parsed : [];
                } catch {
                    return [];
                }
            };

            const renderTags = (hiddenInput) => {
                const list = document.querySelector(`[data-list-for="${hiddenInput.id}"]`);
                const tags = parseTags(hiddenInput.value);

                list.innerHTML = '';

                tags.forEach((tag, index) => {
                    const chip = document.createElement('span');
                    chip.className = 'tag-chip';
                    chip.innerHTML = `${tag} <button type="button" data-remove-index="${index}">&times;</button>`;
                    list.appendChild(chip);
                });

                list.querySelectorAll('button[data-remove-index]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const newTags = parseTags(hiddenInput.value).filter((_, i) => i !== Number(btn.dataset.removeIndex));
                        hiddenInput.value = JSON.stringify(newTags);
                        renderTags(hiddenInput);
                    });
                });
            };

            document.querySelectorAll('.tag-source').forEach((input) => {
                const hiddenInput = document.getElementById(input.dataset.target);

                renderTags(hiddenInput);

                input.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ',') {
                        return;
                    }

                    event.preventDefault();
                    const value = input.value.trim();
                    if (!value) {
                        return;
                    }

                    const tags = parseTags(hiddenInput.value);
                    if (!tags.includes(value)) {
                        tags.push(value);
                        hiddenInput.value = JSON.stringify(tags);
                        renderTags(hiddenInput);
                    }

                    input.value = '';
                });
            });

            const photoRowsContainer = document.getElementById('photo-rows');
            const photoTemplate = document.getElementById('photo-row-template');

            const updatePhotoPositions = () => {
                photoRowsContainer.querySelectorAll('.photo-row').forEach((row, index) => {
                    row.querySelector('.photo-position').value = index + 1;
                    row.querySelector('.photo_img').id="photo"+(index+1);
                    row.querySelector('.input_img').setAttribute('data-photo-number',index+1);
                });
            };

            const addPhotoRow = () => {
                const row = photoTemplate.content.firstElementChild.cloneNode(true);
                row.querySelector('.remove-photo-row').addEventListener('click', () => {
                    row.remove();
                    updatePhotoPositions();
                });
                photoRowsContainer.appendChild(row);
                updatePhotoPositions();
            };

            document.getElementById('add-photo-row').addEventListener('click', addPhotoRow);
            addPhotoRow();
        });

        function previewPhoto(btn,e) {
            const file = e.target.files?.[0];
            const numPhoto = btn.getAttribute('data-photo-number');
            if (!file) return;
            document.getElementById('photo'+numPhoto).src = URL.createObjectURL(file);
        }
    </script>
@endsection
