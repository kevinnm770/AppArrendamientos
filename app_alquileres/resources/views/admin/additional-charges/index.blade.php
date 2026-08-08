@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Otros cargos a cobrar</h3>
                <p class="text-subtitle text-muted">Cobros fuera de lo establecido por el contrato. Se suman al saldo pendiente del inquilino.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                    <a href="{{ route('admin.additional-charges.create') }}" class="btn-brand-action">
                        <i class="bi bi-plus-lg"></i>
                        <span>Nuevo registro</span>
                    </a>
                    <nav aria-label="breadcrumb" class="breadcrumb-header">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Additional-charges</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Cargos registrados</h4>
            </div>
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="GET" action="{{ route('admin.additional-charges.index') }}" class="row g-2 mb-4">
                    <div class="col-md-5">
                        <label class="form-label">Contrato</label>
                        <select name="agreement_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($agreementsForFilter as $agreement)
                                <option value="{{ $agreement->id }}" @selected(($filters['agreement_id'] ?? null) == $agreement->id)>
                                    #{{ $agreement->id }} - {{ $agreement->property->name ?? 'Sin propiedad' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                        <a href="{{ route('admin.additional-charges.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Concepto</th>
                                <th>Descripción</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($charges as $charge)
                                <tr>
                                    <td>{{ optional($charge->charge_date)->format('Y-m-d') }}</td>
                                    <td>{{ $charge->roomer->legal_name ?? '-' }}</td>
                                    <td>{{ $conceptOptions[$charge->concept] ?? $charge->concept }}</td>
                                    <td>{{ $charge->description }}</td>
                                    <td>{{ $charge->currency }} {{ number_format((float) $charge->amount, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $charge->status === 'cancelled' ? 'bg-secondary' : 'bg-warning text-dark' }}">
                                            {{ $statusOptions[$charge->status] ?? $charge->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($charge->status === 'pending')
                                            <form method="POST" action="{{ route('admin.additional-charges.cancel', $charge->id) }}" onsubmit="return confirm('¿Cancelar este cargo? Dejará de sumar al saldo pendiente del inquilino.');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No se encontraron cargos adicionales.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $charges->links() }}
            </div>
        </div>
    </section>
@endsection
