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
                        <li class="breadcrumb-item"><a href="{{route('admin.index')}}">Admin</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Agreements</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section id="content-types">
        <div class="row">
            @forelse ($agreements as $agreement)
                <div class="col-xl-4 col-md-6 col-sm-12">
                    <a href="#">
                        <div class="card" style="cursor: pointer;">
                            <div class="card-content">
                                <div class="card-body">
                                    <h4 class="card-title">{{ $agreement->property_id }}</h4>
                                    <span class="badge bg-success"></span>
                                </div>

                                <div class="card-body">
                                    <i class="bi bi-calendar-check-fill"></i> {{ $agreement->start_at }} - {{ $agreement->end_at }}
                                </div>

                                <div class="card-footer">
                                    {{ $agreement->created_at }}
                                </div>
                            </div>
                        </div>
                    </a>
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
@endsection
