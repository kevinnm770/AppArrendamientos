@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Bandeja de notificaciones</h3>
                <p class="text-subtitle text-muted">Revisa todas tus notificaciones pendientes.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                    <div class="d-flex flex-wrap justify-content-md-end gap-2">
                        <a href="{{ route('tenant.notifications.all') }}" class="btn-brand-action">
                            <i class="bi bi-list-ul"></i>
                            <span>Ver todos</span>
                        </a>
                        <form method="POST" action="{{ route('tenant.notifications.mark-all-seen') }}" class="d-inline-flex m-0">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-brand-action">
                                <i class="bi bi-check2-all"></i>
                                <span>Dar por vistas</span>
                            </button>
                        </form>
                    </div>
                    <nav aria-label="breadcrumb" class="breadcrumb-header">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{route('tenant.index')}}">Tenant</a></li>
                            <li class="breadcrumb-item active">Notifications</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-light-success">{{ session('success') }}</div>
    @endif

    @php
        $priorityClasses = [
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'success',
        ];
    @endphp

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
                                <th>Título</th>
                                <th>Prioridad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notifications as $notification)
                                <tr style="cursor: pointer;"
                                    onclick="window.location.href='{{ route('tenant.notifications.view', $notification->id) }}'">
                                    <td class="text-bold-500">{{ $notification->title }}</td>
                                    <td>
                                        <span
                                            class="alert alert-light-{{ $priorityClasses[$notification->priority] ?? 'secondary' }} p-1">
                                            {{ strtoupper($notification->priority) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No hay notificaciones disponibles.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
