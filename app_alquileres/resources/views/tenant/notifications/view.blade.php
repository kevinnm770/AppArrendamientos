@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Detalles de la notificación</h3>
            </div>
        </div>
        <div class="col-12 col-md-6 order-md-2 order-first">
            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{route('tenant.index')}}">Tenant</a></li>
                    <li class="breadcrumb-item "><a href="{{route('admin.notifications.index')}}">Notifications</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </nav>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{$notification->title}}</h4>
            </div>
            <div class="card-body">
                {{$notification->body}}
            </div>
        </div>
    </section>

@endsection
