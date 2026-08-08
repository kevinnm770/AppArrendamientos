@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Comprobantes de pago</h3>
                <p class="text-subtitle text-muted">Constancia de los pagos que tus arrendatarios ya realizaron.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                    <a href="{{ route('admin.payment-receipts.create') }}" class="btn-brand-action">
                        <i class="bi bi-plus-lg"></i>
                        <span>Nuevo registro</span>
                    </a>
                    <nav aria-label="breadcrumb" class="breadcrumb-header">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Payment-receipt</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Comprobantes registrados</h4>
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

                <form method="GET" action="{{ route('admin.payment-receipts.index') }}" class="row g-2 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" class="form-control" placeholder="N° comprobante o cliente" value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-md-4">
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
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                        <a href="{{ route('admin.payment-receipts.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Métodos de pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receipts as $receipt)
                                <tr class="receipt-row" role="button" data-receipt-detail="receipt-detail-{{ $receipt->id }}" style="cursor:pointer;">
                                    <td>{{ $receipt->receipt_number }}</td>
                                    <td>{{ $receipt->roomer->legal_name ?? '-' }}</td>
                                    <td>{{ optional($receipt->date)->format('Y-m-d') }}</td>
                                    <td>{{ $receipt->currency }} {{ number_format((float) $receipt->total, 2) }}</td>
                                    <td>{{ collect($receipt->payment_methods ?? [])->map(fn ($method) => $paymentMethodOptions[$method] ?? $method)->implode(', ') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No se encontraron comprobantes de pago.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $receipts->links() }}
            </div>
        </div>

        {{-- Plantillas ocultas con el detalle completo de cada comprobante. Al hacer clic en
        una fila, su contenido se copia dentro del modal compartido. --}}
        @foreach ($receipts as $receipt)
            <template id="receipt-detail-{{ $receipt->id }}">
                <div class="row mb-3">
                    <div class="col-4">
                        <strong class="d-block">Contrato</strong>
                        <span>#{{ $receipt->agreement_id }} - {{ $receipt->agreement->property->name ?? 'Sin propiedad' }}</span>
                    </div>
                    <div class="col-4">
                        <strong class="d-block">Cliente</strong>
                        <span>{{ $receipt->roomer->legal_name ?? '-' }}</span>
                    </div>
                    <div class="col-4">
                        <strong class="d-block">Fecha</strong>
                        <span>{{ optional($receipt->date)->format('Y-m-d') }}</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <strong class="d-block">Métodos de pago</strong>
                        <span>
                            {{ collect($receipt->payment_methods ?? [])->map(fn ($method) => $paymentMethodOptions[$method] ?? $method)->implode(', ') ?: '-' }}
                            @if (in_array('other', $receipt->payment_methods ?? [], true) && $receipt->payment_method_other_description)
                                ({{ $receipt->payment_method_other_description }})
                            @endif
                        </span>
                    </div>
                </div>

                <hr>

                <div class="mb-3">
                    <strong class="d-block mb-0">Líneas del comprobante</strong>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Descripción</th>
                                    <th>Concepto</th>
                                    <th class="text-end">Cant.</th>
                                    <th class="text-end">Precio unit.</th>
                                    <th class="text-end">Total línea</th>
                                    <th class="text-end">Saldo pendiente</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($receipt->items as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td>
                                            {{ $conceptOptions[$item->concept] ?? '-' }}
                                            @if ($item->is_return)
                                                <span class="badge bg-warning text-dark">Retorno</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->line_total, 2) }}</td>
                                        <td class="text-end">{{ $item->balance_pending !== null ? number_format((float) $item->balance_pending, 2) : '-' }}</td>
                                        <td>
                                            @if ($item->filePayment)
                                                <a href="{{ $item->filePayment->url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Ver archivo</a>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Sin líneas registradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <strong class="d-block col-8" style="text-align: right;">Total</strong>
                    <span class="col-4" style="text-align: right;" class="fw-bold">{{ $receipt->currency }} {{ number_format((float) $receipt->total, 2) }}</span>
                </div>

                @if ($receipt->reference_code || $receipt->notes)
                    <div class="row mb-3 mt-2">
                        @if ($receipt->reference_code)
                            <div class="col-6">
                                <strong class="d-block">Referencia</strong>
                                <span>{{ $receipt->reference_code }}</span>
                            </div>
                        @endif
                        @if ($receipt->notes)
                            <div class="col-6">
                                <strong class="d-block">Notas</strong>
                                <span>{{ $receipt->notes }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <hr>

                <div class="mb-1">
                    @if ($receipt->canEditOrDelete())
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.payment-receipts.edit', $receipt->id) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form method="POST" class="mb-0" action="{{ route('admin.payment-receipts.delete', $receipt->id) }}" onsubmit="return confirm('¿Eliminar este comprobante de pago? Se notificará al inquilino y esta acción no se puede deshacer.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </div>
                    @else
                        <span class="text-muted small">Ya pasaron más de 24 horas desde su creación: no se puede editar ni eliminar.</span>
                    @endif
                </div>
            </template>
        @endforeach

        <div id="receipt-detail-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 820px; max-height: 90vh; overflow-y: auto;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">Detalle del comprobante</h4>
                        <button type="button" class="btn-close" id="receipt-detail-modal-close" aria-label="Cerrar"></button>
                    </div>
                    <div id="receipt-detail-modal-body"></div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const detailBackdrop = document.getElementById('receipt-detail-modal-backdrop');
                const detailBody = document.getElementById('receipt-detail-modal-body');

                document.querySelectorAll('.receipt-row').forEach(function(row) {
                    row.addEventListener('click', function() {
                        const template = document.getElementById(row.dataset.receiptDetail);
                        if (!template) return;

                        detailBody.innerHTML = '';
                        detailBody.appendChild(template.content.cloneNode(true));
                        detailBackdrop.style.display = 'flex';
                    });
                });

                document.getElementById('receipt-detail-modal-close').addEventListener('click', function() {
                    detailBackdrop.style.display = 'none';
                });

                detailBackdrop.addEventListener('click', function(event) {
                    if (event.target === detailBackdrop) detailBackdrop.style.display = 'none';
                });
            });
        </script>
    </section>
@endsection
