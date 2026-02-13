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
                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <form class="card" method="POST" action="{{ route('admin.properties.edit.update', $property->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <div class="card-header">
                <h5 class="card-title mb-0">Registrar propiedad</h5>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="name">Nombre de la propiedad *</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $property->name) }}" placeholder="Ej: Casa Los Robles" required>
                        @error('name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="service_type">Tipo de servicio *</label>
                        <select class="form-select @error('service_type') is-invalid @enderror" id="service_type" name="service_type" required>
                            <option value="" selected disabled>Selecciona una opción</option>
                            <option value="home" @selected(old('service_type', $property->service_type) === 'home')>Hogar</option>
                            <option value="lodging" @selected(old('service_type', $property->service_type) === 'lodging')>Hospedaje</option>
                            <option value="event" @selected(old('service_type', $property->service_type) === 'event')>Evento</option>
                        </select>
                        @error('service_type')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">Descripción</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3"
                            placeholder="Describe los espacios, reglas y beneficios de la propiedad">{{ old('description', $property->description) }}</textarea>
                        @error('description')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-4">
                        <label class="form-label" for="location_province">Provincia *</label>
                        <select class="form-select @error('location_province') is-invalid @enderror" name="location_province" id="location_province" required>
                            <option value="" selected disabled>Selecciona una provincia</option>
                            <option value="Cartago" @selected(old('location_province', $property->location_province) === 'Cartago')>Cartago</option>
                            <option value="San José" @selected(old('location_province', $property->location_province) === 'San José')>San José</option>
                            <option value="Alajuela" @selected(old('location_province', $property->location_province) === 'Alajuela')>Alajuela</option>
                            <option value="Heredia" @selected(old('location_province', $property->location_province) === 'Heredia')>Heredia</option>
                            <option value="Limón" @selected(old('location_province', $property->location_province) === 'Limón')>Limón</option>
                            <option value="Puntarenas" @selected(old('location_province', $property->location_province) === 'Puntarenas')>Puntarenas</option>
                            <option value="Guanacaste" @selected(old('location_province', $property->location_province) === 'Guanacaste')>Guanacaste</option>
                        </select>
                        @error('location_province')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="location_canton">Cantón *</label>
                        <select class="form-select @error('location_canton') is-invalid @enderror" name="location_canton" id="location_canton" data-old="{{ old('location_canton', $property->location_canton) }}" required>
                            <option value="" selected disabled>Selecciona un cantón</option>
                        </select>
                        @error('location_canton')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="location_district">Distrito *</label>
                        <select class="form-select @error('location_district') is-invalid @enderror" name="location_district" id="location_district" data-old="{{ old('location_district', $property->location_district) }}" required>
                            <option value="" selected disabled>Selecciona un distrito</option>
                        </select>
                        @error('location_district')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="location_text">Dirección exacta *</label>
                        <input type="text" class="form-control @error('location_text') is-invalid @enderror" id="location_text" name="location_text"
                            value="{{ old('location_text', $property->location_text) }}" placeholder="Ej: Del boulevard, 300m este, 3ra casa" required>
                        @error('location_text')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="rooms">Habitaciones</label>
                        <input type="number" class="form-control @error('rooms') is-invalid @enderror" id="rooms" name="rooms" min="0" value="{{ old('rooms', $property->rooms ?? 0) }}">
                        @error('rooms')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="living_rooms">Salas</label>
                        <input type="number" class="form-control @error('living_rooms') is-invalid @enderror" id="living_rooms" name="living_rooms" min="0" value="{{ old('living_rooms', $property->living_rooms ?? 0) }}">
                        @error('living_rooms')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="kitchens">Cocinas</label>
                        <input type="number" class="form-control @error('kitchens') is-invalid @enderror" id="kitchens" name="kitchens" min="0" value="{{ old('kitchens', $property->kitchens ?? 0) }}">
                        @error('kitchens')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="bathrooms">Baños</label>
                        <input type="number" class="form-control @error('bathrooms') is-invalid @enderror" id="bathrooms" name="bathrooms" min="0" value="{{ old('bathrooms', $property->bathrooms ?? 0) }}">
                        @error('bathrooms')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="yards">Patios</label>
                        <input type="number" class="form-control @error('yards') is-invalid @enderror" id="yards" name="yards" min="0" value="{{ old('yards', $property->yards ?? 0) }}">
                        @error('yards')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="garages_capacity">Garajes</label>
                        <input type="number" class="form-control @error('garages_capacity') is-invalid @enderror" id="garages_capacity" name="garages_capacity" min="0" value="{{ old('garages_capacity', $property->garages_capacity ?? 0) }}">
                        @error('garages_capacity')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="materials_input">Materiales (tags)</label>
                        <input type="text" class="form-control tag-source @error('materials') is-invalid @enderror" id="materials_input"
                            data-target="materials_tags" placeholder="Escribe y presiona Enter (ej: piso cerámica)">
                        <small class="text-muted">Presiona Enter o coma para crear cada material.</small>
                        <input type="hidden" name="materials" id="materials_tags" value="{{ old('materials', json_encode($property->materials ?? [])) }}">
                        @error('materials')
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                        <div class="tag-list mt-2" data-list-for="materials_tags"></div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="included_objects_input">Objetos incluidos (tags)</label>
                        <input type="text" class="form-control tag-source @error('included_objects') is-invalid @enderror" id="included_objects_input"
                            data-target="included_objects_tags" placeholder="Escribe y presiona Enter (ej: refrigeradora)">
                        <small class="text-muted">Presiona Enter o coma para crear cada objeto.</small>
                        <input type="hidden" name="included_objects" id="included_objects_tags" value="{{ old('included_objects', json_encode($property->included_objects ?? [])) }}">
                        @error('included_objects')
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                        <div class="tag-list mt-2" data-list-for="included_objects_tags"></div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label" for="status">Estado *</label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                            <option value="available" @selected(old('status', $property->status ?? 'available') === 'available')>Disponible</option>
                            <option value="occupied" @selected(old('status', $property->status ?? 'available') === 'occupied')>Ocupada</option>
                            <option value="disabled" @selected(old('status', $property->status ?? 'available') === 'disabled')>Deshabilitada</option>
                        </select>
                        @error('status')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label d-block" for="is_public">Publicar propiedad</label>
                        <input type="hidden" name="is_public" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input @error('is_public') is-invalid @enderror" type="checkbox" role="switch" id="is_public" name="is_public" value="1" @checked(old('is_public', ($property->is_public ?? false) ? '1' : '0') === '1')>
                            <label class="form-check-label" for="is_public">Visible para arrendatarios</label>
                        </div>
                        <small class="text-muted">Solo se puede publicar cuando el estado está en Disponible.</small>
                        @error('is_public')
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
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
                        @error('photos')
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                        @error('photos.*.file')
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="col-12" id="photo-rows"></div>
                </div>
            </div>

            @error('delete')
                <div class="px-4 pb-0">
                    <div class="alert alert-danger mb-0" role="alert">{{ $message }}</div>
                </div>
            @enderror
            @error('token')
                <div class="px-4 pb-0">
                    <div class="alert alert-danger mb-0" role="alert">{{ $message }}</div>
                </div>
            @enderror

            <div class="card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-danger" id="delete-property-button">
                    <i class="fa-solid fa-trash"></i> Eliminar
                </button>

                <div class="d-flex justify-content-end gap-2 ms-auto">
                <a href="{{ route('admin.properties.index') }}" class="btn btn-light-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar propiedad</button>
                </div>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.properties.edit.delete', $property->id) }}" id="delete-property-form">
            @csrf
            @method('PATCH')
            <input type="hidden" name="token" id="delete-token-input" value="">
        </form>
    </section>

    <template id="photo-row-template">
        <div class="p-3 mb-3 photo-row">
            <input type="hidden" class="photo-id" name="photos[][id]">
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
                            <input type="text" class="form-control" name="photos[][caption]" placeholder="Ej: Vista de sala principal" required>
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

    @php
    $existingPhotos = $property->photos->map(function ($photo) {
        return [
            'id' => $photo->id,
            'path' => asset('storage/' . $photo->path),
            'caption' => $photo->caption,
            'taken_at' => optional($photo->taken_at)->format('Y-m-d\TH:i'),
        ];
    })->values();
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locationData = @json($locationData ?? []);
            const existingPhotos = @json($existingPhotos);
            const provinceSelect = document.getElementById('location_province');
            const cantonSelect = document.getElementById('location_canton');
            const districtSelect = document.getElementById('location_district');
            const oldCanton = cantonSelect.dataset.old;
            const oldDistrict = districtSelect.dataset.old;

            const resetSelect = (select, placeholder) => {
                select.innerHTML = '';
                const option = document.createElement('option');
                option.value = '';
                option.textContent = placeholder;
                option.disabled = true;
                option.selected = true;
                select.appendChild(option);
            };

            const populateSelect = (select, items, placeholder) => {
                resetSelect(select, placeholder);
                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item;
                    option.textContent = item;
                    select.appendChild(option);
                });
            };

            const updateCantons = () => {
                const province = provinceSelect.value;
                const cantons = Object.keys(locationData[province] || {});
                populateSelect(cantonSelect, cantons, 'Selecciona un cantón');
                resetSelect(districtSelect, 'Selecciona un distrito');
                if (oldCanton) {
                    cantonSelect.value = oldCanton;
                }
            };

            const updateDistricts = () => {
                const province = provinceSelect.value;
                const canton = cantonSelect.value;
                const districts = (locationData[province] && locationData[province][canton]) || [];
                populateSelect(districtSelect, districts, 'Selecciona un distrito');
                if (oldDistrict) {
                    districtSelect.value = oldDistrict;
                }
            };

            provinceSelect.addEventListener('change', updateCantons);
            cantonSelect.addEventListener('change', updateDistricts);

            if (provinceSelect.value) {
                updateCantons();
                if (cantonSelect.value) {
                    updateDistricts();
                }
            }

            const statusSelect = document.getElementById('status');
            const isPublicCheckbox = document.getElementById('is_public');

            const syncPublicVisibility = () => {
                const canBePublic = statusSelect.value === 'available';
                isPublicCheckbox.disabled = !canBePublic;

                if (!canBePublic) {
                    isPublicCheckbox.checked = false;
                }
            };

            statusSelect.addEventListener('change', syncPublicVisibility);
            syncPublicVisibility();

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

                photoRowsContainer.querySelectorAll('.photo-row')
                    .forEach((row, index) => {

                        const i = index; // índice para Laravel

                        // -------- id --------
                        const idInput = row.querySelector('.photo-id');
                        if (idInput) {
                            idInput.name = `photos[${i}][id]`;
                        }

                        // -------- posición visual --------
                        row.querySelector('.photo-position').value = index + 1;
                        row.querySelector('.photo-position').name =
                            `photos[${i}][position]`;

                        // -------- preview img --------
                        row.querySelector('.photo_img').id =
                            "photo" + (index + 1);

                        // -------- input file --------
                        const fileInput = row.querySelector('.input_img');
                        fileInput.setAttribute('data-photo-number', index + 1);
                        fileInput.name = `photos[${i}][file]`;

                        // -------- caption --------
                        const captionInput =
                            row.querySelector('input[name*="[caption]"]');
                        if (captionInput) {
                            captionInput.name = `photos[${i}][caption]`;
                        }

                        // -------- fecha --------
                        const takenInput =
                            row.querySelector('input[name*="[taken_at]"]');
                        if (takenInput) {
                            takenInput.name = `photos[${i}][taken_at]`;
                        }

                        // -------- botón agregar --------
                        if (index + 1 >= 5) {
                            document.getElementById('add-photo-row').style.display = "none";
                        } else {
                            document.getElementById('add-photo-row').style.display = "";
                        }
                    });
            };


            const addPhotoRow = () => {
                cantPhotos = photoRowsContainer.querySelectorAll('.photo-row').length;

                if(cantPhotos<5){
                    const row = photoTemplate.content.firstElementChild.cloneNode(true);
                    row.querySelector('.remove-photo-row').addEventListener('click', () => {
                        row.remove();
                        updatePhotoPositions();
                    });
                    photoRowsContainer.appendChild(row);
                    updatePhotoPositions();
                }
            };

            const addExistingPhotoRow = (photo) => {
                const row = photoTemplate.content.firstElementChild.cloneNode(true);
                const image = row.querySelector('.photo_img');
                const captionInput = row.querySelector('input[name*="[caption]"]');
                const takenInput = row.querySelector('input[name*="[taken_at]"]');
                const fileInput = row.querySelector('.input_img');
                const idInput = row.querySelector('.photo-id');

                if (image) {
                    image.src = photo.path;
                }
                if (captionInput) {
                    captionInput.value = photo.caption ?? '';
                }
                if (takenInput && photo.taken_at) {
                    takenInput.value = photo.taken_at;
                }
                if (fileInput) {
                    fileInput.removeAttribute('required');
                }
                if (idInput) {
                    idInput.value = photo.id;
                }

                row.querySelector('.remove-photo-row').addEventListener('click', () => {
                    row.remove();
                    updatePhotoPositions();
                });

                photoRowsContainer.appendChild(row);
            };

            document.getElementById('add-photo-row').addEventListener('click', addPhotoRow);

            if (existingPhotos.length) {
                existingPhotos.forEach((photo) => addExistingPhotoRow(photo));
                updatePhotoPositions();
            } else {
                addPhotoRow();
            }

            const deleteButton = document.getElementById('delete-property-button');
            const deleteForm = document.getElementById('delete-property-form');
            const deleteTokenInput = document.getElementById('delete-token-input');

            if (deleteButton && deleteForm && deleteTokenInput && typeof Swal !== 'undefined') {
                deleteButton.addEventListener('click', async function() {
                    const result = await Swal.fire({
                        title: 'Eliminar propiedad',
                        text: 'Para confirmar, ingresa el token de verificación.',
                        input: 'text',
                        inputLabel: 'Token',
                        inputPlaceholder: 'Ingresa el token',
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc3545',
                        inputValidator: (value) => {
                            if (!value) {
                                return 'Debes ingresar el token para continuar.';
                            }
                        },
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    deleteTokenInput.value = (result.value || '').trim();
                    deleteForm.submit();
                });
            }
        });

        function previewPhoto(btn,e) {
            const file = e.target.files?.[0];
            const numPhoto = btn.getAttribute('data-photo-number');
            if (!file) return;
            document.getElementById('photo'+numPhoto).src = URL.createObjectURL(file);
        }
    </script>
@endsection
