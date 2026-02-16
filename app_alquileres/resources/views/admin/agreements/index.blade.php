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
