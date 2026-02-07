@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Propiedades</h3>
                <p class="text-subtitle text-muted">En esta sección puedes ver y administrar tus propiedades a arrendar.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Admin</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Properties</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section id="content-types">
        <div class="row">
            @forelse ($properties as $property)
                @php
                    $statusKey = $property->status;
                    $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                    $statusClass = $statusClasses[$statusKey] ?? 'bg-secondary';
                    $serviceLabel = $serviceTypeLabels[$property->service_type] ?? $property->service_type;
                @endphp
                <div class="col-xl-4 col-md-6 col-sm-12">
                    <a href="{{url('admin/properties/edit/'.$property->id)}}">
                        <div class="card" style="cursor: pointer;">
                            <div class="card-content">
                                <div class="card-body">
                                    <h4 class="card-title">{{ $property->name }}</h4>
                                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                </div>
                                <div id="carouselExampleSlidesOnly" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-inner">

                                        @forelse ($property->photos as $photo)
                                            <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                                <img src="{{ asset('storage/' . $photo->path) }}"
                                                    class="d-block w-100"
                                                    alt="{{ $photo->caption }}" title="{{ $photo->caption }}" height="300px">
                                            </div>
                                        @empty
                                            <div class="carousel-item active">
                                                <img src="{{ asset('storage/photos_properties/photoDefault_property.png') }}"
                                                    class="d-block w-100 px-4"
                                                    alt="Imagen por defecto"
                                                    title="Imagen por defecto"
                                                    height="300px">
                                            </div>
                                        @endforelse

                                    </div>

                                </div>
                                <div class="card-body">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    {{ $property->location_district }}, {{ $property->location_canton }}, {{ $property->location_province }}
                                    <br>
                                    <i class="bi bi-house-door"></i> {{ $serviceLabel }}
                                    <br>
                                    <div class="row mt-3">
                                        <div class="col-3"><i class="fa-solid fa-bed" style="font-size: 9.5pt;" title="Habitaciones o camas"></i> {{ $property->rooms }}</div>
                                        <div class="col-3"><i class="fa-solid fa-couch" style="font-size: 9.5pt;" title="Salas comunes"></i> {{ $property->living_rooms }}</div>
                                        <div class="col-3"><i class="fa-solid fa-kitchen-set" style="font-size: 9.5pt;" title="Cocinas"></i> {{ $property->kitchens }}</div>
                                        <div class="col-3"><i class="fa-solid fa-bath" style="font-size: 9.5pt;" title="Baños"></i> {{ $property->bathrooms }}</div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-3"><i class="fa-solid fa-car" style="font-size: 9.5pt;" title="Capacidad de vehículos"></i> {{ $property->garages_capacity }}</div>
                                        <div class="col-3"><i class="fa-solid fa-jug-detergent" style="font-size: 9.5pt;" title="Zonas de lavado"></i> {{ count($property->included_objects ?? []) }}</div>
                                        <div class="col-3"><i class="fa-solid fa-tree" style="font-size: 9.5pt;" title="Patios y/o zonas verdes"></i> {{ $property->yards }}</div>
                                        <div class="col-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light-secondary" role="alert">
                        No tienes propiedades registradas todavía.
                    </div>
                </div>
            @endforelse
        </div>
    </section>
@endsection
