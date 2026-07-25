@extends('layouts.tenant')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Todas las notificaciones</h3>
                <p class="text-subtitle text-muted">Historial completo de notificaciones, 20 por página.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                    <a href="{{ route('tenant.notifications.index') }}" class="btn-brand-action">
                        <i class="bi bi-arrow-left"></i>
                        <span>Volver a la bandeja</span>
                    </a>
                    <nav aria-label="breadcrumb" class="breadcrumb-header">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{route('tenant.index')}}">Tenant</a></li>
                            <li class="breadcrumb-item"><a href="{{route('tenant.notifications.index')}}">Notifications</a></li>
                            <li class="breadcrumb-item active">All</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

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
                <h4 class="card-title">Historial de notificaciones</h4>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive m-0 ">
                    <table class="table table-lg">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Fecha</th>
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
                                    <td>
                                        @if ($notification->status === 'sent')
                                            <span class="alert alert-light-info p-1">NUEVA</span>
                                        @else
                                            <span class="alert alert-light-secondary p-1">VISTA</span>
                                        @endif
                                    </td>
                                    <td>{{ $notification->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No hay notificaciones disponibles.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-body">
                {{ $notifications->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
