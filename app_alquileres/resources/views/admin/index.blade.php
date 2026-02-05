@extends('layouts.admin')

@section('content')
    <section class="row">
        <div class="col-12 col-lg-9">
            <div class="row">
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon purple mb-2">
                                        <i class="bi bi-houses-fill"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">Propiedades activas</h6>
                                    <h6 class="font-extrabold mb-0">0</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon blue mb-2">
                                        <i class="bi bi-file-earmark-text-fill"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">Contratos vigentes</h6>
                                    <h6 class="font-extrabold mb-0">0</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon red mb-2">
                                        <i class="bi bi-exclamation-diamond-fill"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">Pagos pendientes</h6>
                                    <h6 class="font-extrabold mb-0">0</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon green mb-2">
                                        <i class="bi bi-cash-stack"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">Ingreso mensual</h6>
                                    <h6 class="font-extrabold mb-0">0</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                Contratos próximos a vencer
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped" id="table1">
                                <thead>
                                    <tr>
                                        <th>Propiedad</th>
                                        <th>Arrendatario</th>
                                        <th>Vence en</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Prop 1</td>
                                        <td>Nombre 1</td>
                                        <td>1/1/2026</td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Prop 2</td>
                                        <td>Nombre 2</td>
                                        <td>1/1/2026</td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Prop 3</td>
                                        <td>Nombre 3</td>
                                        <td>1/1/2026</td>
                                        <td>
                                            <span class="badge bg-danger">Inactive</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                Pagos pendientes
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped" id="table1">
                                <thead>
                                    <tr>
                                        <th>Inquilino</th>
                                        <th>Descripción</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Nombre 1</td>
                                        <td>descript</td>
                                        <td>$ 300</td>
                                    </tr>
                                    <tr>
                                        <td>Nombre 2</td>
                                        <td>descript</td>
                                        <td>$ 300</td>
                                    </tr>
                                    <tr>
                                        <td>Nombre 3</td>
                                        <td>descript</td>
                                        <td>$ 300</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h4>Inquilinos</h4>
                </div>
                <div class="card-content pb-4">
                    <div class="recent-message d-flex px-4 py-3">
                        <div class="avatar avatar-lg">
                            <img src="{{asset('/assets/compiled/jpg/4.jpg')}}">
                        </div>
                        <div class="name ms-4">
                            <h5 class="mb-1">Hank Schrader</h5>
                            <h6 class="text-muted mb-0">@johnducky</h6>
                        </div>
                    </div>
                    <div class="recent-message d-flex px-4 py-3">
                        <div class="avatar avatar-lg">
                            <img src="{{asset('/assets/compiled/jpg/5.jpg')}}">
                        </div>
                        <div class="name ms-4">
                            <h5 class="mb-1">Dean Winchester</h5>
                            <h6 class="text-muted mb-0">@imdean</h6>
                        </div>
                    </div>
                    <div class="recent-message d-flex px-4 py-3">
                        <div class="avatar avatar-lg">
                            <img src="{{asset('/assets/compiled/jpg/1.jpg')}}">
                        </div>
                        <div class="name ms-4">
                            <h5 class="mb-1">John Dodol</h5>
                            <h6 class="text-muted mb-0">@dodoljohn</h6>
                        </div>
                    </div>
                    <div class="px-4">
                        <button class='btn btn-block btn-xl btn-outline-primary font-bold mt-3'>Start Conversation</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-header">
                    <h4>Tus propiedades</h4>
                </div>
                <div class="card-body">
                    <p>No hay propiedades registradas</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h4>Mensajes recientes</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-lg">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Mensaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="col-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-md">
                                                <img src="{{asset('/assets/compiled/jpg/5.jpg')}}">
                                            </div>
                                            <p class="font-bold ms-3 mb-0">Si Cantik</p>
                                        </div>
                                    </td>
                                    <td class="col-auto">
                                        <p class=" mb-0">Congratulations on your graduation!</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="col-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-md">
                                                <img src="{{asset('/assets/compiled/jpg/2.jpg')}}">
                                            </div>
                                            <p class="font-bold ms-3 mb-0">Si Ganteng</p>
                                        </div>
                                    </td>
                                    <td class="col-auto">
                                        <p class=" mb-0">Wow amazing design! Can you make another tutorial for
                                            this design?</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
