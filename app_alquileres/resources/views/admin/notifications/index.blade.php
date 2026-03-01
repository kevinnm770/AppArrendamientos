@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Bandeja de notificaciones</h3>
                <p class="text-subtitle text-muted">Revisa todas tus notificaciones pendientes.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active"><a href="#">Notifications</a></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <h4 class="card-title">Nuevas noticias</h4>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive m-0 ">
                    <table class="table table-lg">
                        <thead>
                            <tr>
                                <th>Titulo</th>
                                <th>Prioridad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="cursor: pointer;" onclick="window.location.href='{{$notification->link}}'">
                                <td class="text-bold-500">{{$notification->title}}</td>
                                <td><span class="alert alert-light-{{$notification->priority}} p-1">{{strtoupper($notification->priority)}}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
