@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Registro de contrato</h3>
                <p class="text-subtitle text-muted">Crea un contrato nuevo con validación de disponibilidad por fechas.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.agreements.index') }}">Contratos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Registrar</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <section class="section">
            <div class="alert alert-light-danger">
                <h6 class="alert-heading">No se pudo registrar el contrato</h6>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Datos del contrato</h4>
            </div>
            <div class="card-body">
                @if ($properties->isEmpty())
                    <div class="alert alert-light-warning mb-0">
                        No tienes propiedades registradas para crear contratos.
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.agreements.register.store') }}" class="row g-3">
                        @csrf

                        <div class="col-md-6">
                            <label for="property_id" class="form-label">Propiedad</label>
                            <select id="property_id" name="property_id" class="form-select" required>
                                <option value="">Selecciona una propiedad</option>
                                @foreach ($properties as $property)
                                    <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>
                                        {{ $property->name }} ({{ $serviceTypeLabels[$property->service_type] ?? $property->service_type }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="roomer_id" class="form-label">Arrendatario</label>
                            <select id="roomer_id" name="roomer_id" class="form-select" required>
                                <option value="">Selecciona un arrendatario</option>
                                @foreach ($roomers as $roomer)
                                    <option value="{{ $roomer->id }}" @selected(old('roomer_id') == $roomer->id)>
                                        {{ $roomer->legal_name }} - {{ $roomer->id_number }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="service_type" class="form-label">Tipo de servicio</label>
                            <select id="service_type" name="service_type" class="form-select" required>
                                <option value="">Selecciona un tipo</option>
                                @foreach ($serviceTypeLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('service_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="start_at" class="form-label">Inicio</label>
                            <input id="start_at" type="datetime-local" name="start_at" class="form-control"
                                value="{{ old('start_at') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label for="end_at" class="form-label">Fin (opcional)</label>
                            <input id="end_at" type="datetime-local" name="end_at" class="form-control"
                                value="{{ old('end_at') }}">
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label">Estado inicial</label>
                            <select id="status" name="status" class="form-select" required>
                                @foreach ($statusLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="terms" class="form-label">Términos del contrato</label>
                            <textarea id="terms" name="terms" class="form-control" rows="8"
                                placeholder="Escribe aquí las cláusulas del contrato" required>{{ old('terms') }}</textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.agreements.index') }}" class="btn btn-light-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Registrar contrato</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
