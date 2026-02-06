@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Propiedades</h3>
                <p class="text-subtitle text-muted">En esta sección puedes ver y adminitrar tus propiedades a arrendar.</p>
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
            <div class="col-xl-4 col-md-6 col-sm-12">
                <a href="">
                    <div class="card" style="cursor: pointer;">
                        <div class="card-content">
                            <div class="card-body">
                                <h4 class="card-title">Propiedad XXXX</h4>
                                <span class="badge bg-success">Alquilada</span>
                            </div>
                            <div id="carouselExampleSlidesOnly" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <div class="carousel-item active">
                                        <img src="{{asset('/assets/compiled/jpg/architecture1.jpg')}}" class="d-block w-100"
                                            alt="Image Architecture">
                                    </div>
                                    <div class="carousel-item">
                                        <img src="{{asset('/assets/compiled/jpg/bg-mountain.jpg')}}" class="d-block w-100"
                                            alt="Image Mountain">
                                    </div>
                                    <div class="carousel-item">
                                        <img src="{{asset('/assets/compiled/jpg/jump.jpg')}}" class="d-block w-100" alt="Image Jump">
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <i class="bi bi-geo-alt-fill"></i> La lima, Cartago, Costa Rica
                                <br>
                                <i class="bi bi-house-door"></i> Tipo (Evento, Hogar, Lodging)
                                <br>
                                <div class="row mt-3">
                                    <div class="col-3"><i class="fa-solid fa-bed" style="font-size: 9.5pt;" title="Habitaciones o camas"></i> 3</div>
                                    <div class="col-3"><i class="fa-solid fa-couch" style="font-size: 9.5pt;" title="Salas comunes"></i> 0</div>
                                    <div class="col-3"><i class="fa-solid fa-kitchen-set" style="font-size: 9.5pt;" title="Cocinas"></i> 0</div>
                                    <div class="col-3"><i class="fa-solid fa-bath" style="font-size: 9.5pt;" title="Baños"></i> 0</div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-3"><i class="fa-solid fa-car" style="font-size: 9.5pt;" title="Capacidad de vehículos"></i> 0</div>
                                    <div class="col-3"><i class="fa-solid fa-jug-detergent" style="font-size: 9.5pt;" title="Zonas de lavado"></i> 0</div>
                                    <div class="col-3"><i class="fa-solid fa-tree" style="font-size: 9.5pt;" title="Patios y/o zonas verdes"></i> 0</div>
                                    <div class="col-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </section>
@endsection
