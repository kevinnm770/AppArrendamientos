@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Aplicación de saldo a favor</h3>
                <p class="text-subtitle text-muted">Movimientos de saldo a favor: generado (sobrepago, ajuste manual) y aplicado (consumido contra un concepto pendiente).</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                    <a href="{{ route('admin.credit-balance.create') }}" class="btn-brand-action">
                        <i class="bi bi-plus-lg"></i>
                        <span>Nuevo registro</span>
                    </a>
                    <nav aria-label="breadcrumb" class="breadcrumb-header">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Credit-balance</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Movimientos registrados</h4>
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

                <form method="GET" action="{{ route('admin.credit-balance.index') }}" class="row g-2 mb-4">
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
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                        <a href="{{ route('admin.credit-balance.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Origen</th>
                                <th>Aplicado contra</th>
                                <th>Monto</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                <tr>
                                    <td>{{ $movement->created_at->format('Y-m-d') }}</td>
                                    <td>{{ $movement->roomer->legal_name ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $movement->type === 'generated' ? 'bg-success' : 'bg-info text-dark' }}">
                                            {{ $typeOptions[$movement->type] ?? $movement->type }}
                                        </span>
                                    </td>
                                    <td>{{ $sourceOptions[$movement->source] ?? $movement->source }}</td>
                                    <td>{{ $movement->applied_to_concept ?? '-' }}</td>
                                    <td>{{ $movement->currency }} {{ number_format((float) $movement->amount, 2) }}</td>
                                    <td>{{ $movement->reason ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No se encontraron movimientos de saldo a favor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $movements->links() }}
            </div>
        </div>
    </section>
@endsection
