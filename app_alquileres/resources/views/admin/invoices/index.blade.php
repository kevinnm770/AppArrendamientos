@extends('layouts.admin')

@section('content')
    <style>
        .note-cyan {
            color: #00e5ff;
        }
    </style>

    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Facturas</h3>
                <p class="text-subtitle text-muted">Consulta y da seguimiento a las facturas emitidas a tus arrendatarios.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                    <button type="button" class="btn-brand-action" id="new-invoice-btn">
                        <i class="bi bi-plus-lg"></i>
                        <span>Nueva factura</span>
                    </button>
                    <nav aria-label="breadcrumb" class="breadcrumb-header">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Invoices</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Facturas registradas</h4>
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

                <form method="GET" action="{{ route('admin.invoices.index') }}" class="row g-2 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" class="form-control" placeholder="N° factura o cliente" value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
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
                        <label class="form-label">Estado Hacienda</label>
                        <select name="hacienda_status" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($haciendaStatusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['hacienda_status'] ?? null) === $value)>{{ $label }}</option>
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
                        <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
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
                                <th>Estado Hacienda</th>
                                <th>Trazabilidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $invoice)
                                <tr class="invoice-row" role="button" data-invoice-detail="invoice-detail-{{ $invoice->id }}" style="cursor:pointer;">
                                    <td>{{ $invoice->invoice_number }}</td>
                                    <td>{{ $invoice->roomer->legal_name ?? '-' }}</td>
                                    <td>{{ optional($invoice->date)->format('Y-m-d') }}</td>
                                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                    <td>
                                        @if ($invoice->electronicDetail)
                                            {{ $haciendaStatusOptions[$invoice->electronicDetail->electronic_status ?? 'pending'] ?? 'Pendiente' }}
                                        @else
                                            No aplica
                                        @endif
                                    </td>
                                    <td>
                                        @if ($invoice->electronicDetail)
                                            <small class="d-block text-muted">Cola: {{ optional($invoice->electronicDetail->queued_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                            <small class="d-block text-muted">Enviado: {{ optional($invoice->electronicDetail->sent_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                            <small class="d-block text-muted">Aceptado: {{ optional($invoice->electronicDetail->accepted_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                            <small class="d-block text-muted">Rechazado: {{ optional($invoice->electronicDetail->rejected_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                            <small class="d-block text-muted">Error: {{ optional($invoice->electronicDetail->error_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No se encontraron facturas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $invoices->links() }}
            </div>
        </div>

        {{-- Plantillas ocultas con el detalle completo de cada factura. Al hacer clic en una
        fila, su contenido se copia dentro del modal compartido. --}}
        @foreach ($invoices as $invoice)
            <template id="invoice-detail-{{ $invoice->id }}">
                <div class="mb-3">
                    <strong class="d-block">Contrato</strong>
                    <span>#{{ $invoice->agreement_id }}</span>
                </div>
                <div class="mb-3">
                    <strong class="d-block">Cliente</strong>
                    <span>{{ $invoice->roomer->legal_name ?? '-' }}</span>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <strong class="d-block">Fecha</strong>
                        <span>{{ optional($invoice->date)->format('Y-m-d') }}</span>
                    </div>
                    <div class="col-6">
                        <strong class="d-block">Total</strong>
                        <span>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <strong class="d-block">Estado factura</strong>
                        <span>{{ $statusOptions[$invoice->status] ?? $invoice->status }}</span>
                    </div>
                    <div class="col-6">
                        <strong class="d-block">Estado Hacienda</strong>
                        <span>
                            @if ($invoice->electronicDetail)
                                {{ $haciendaStatusOptions[$invoice->electronicDetail->electronic_status ?? 'pending'] ?? 'Pendiente' }}
                            @else
                                No aplica
                            @endif
                        </span>
                    </div>
                </div>

                @if ($invoice->electronicDetail)
                    @php $feStatus = $invoice->electronicDetail->electronic_status; @endphp

                    <div class="mb-3">
                        <strong class="d-block">Último mensaje</strong>
                        <span>{{ $invoice->electronicDetail->last_transition_message ?? '-' }}</span>
                    </div>

                    <div class="mb-3">
                        <strong class="d-block mb-1">Acciones</strong>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($feStatus === 'pending')
                                <form method="POST" action="{{ route('admin.invoices.electronic.send', $invoice->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Enviar</button>
                                </form>
                            @endif

                            @if (in_array($feStatus, ['rejected', 'error'], true))
                                <form method="POST" action="{{ route('admin.invoices.electronic.retry', $invoice->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning">Reintentar</button>
                                </form>
                            @endif

                            @if (in_array($feStatus, ['queued', 'sent', 'rejected', 'error'], true))
                                <form method="POST" action="{{ route('admin.invoices.electronic.check-status', $invoice->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info">Consultar estado</button>
                                </form>
                            @endif

                            @if ($feStatus === 'accepted')
                                <span class="text-success small align-self-center">Aceptada, nada pendiente.</span>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex flex-wrap gap-2">
                        @if ($invoice->electronicDetail->ptec_response)
                            <a href="{{ route('admin.invoices.electronic.response', $invoice->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Descargar respuesta factura electrónica</a>
                        @endif

                        @if ($invoice->electronicDetail->xml_content)
                            <a href="{{ route('admin.invoices.electronic.xml', $invoice->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Descargar factura electrónica enviada</a>
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Aún no disponible">Descargar documento PDF</button>
                    </div>
                @endif
            </template>
        @endforeach

        <div id="invoice-detail-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">Detalle de la factura</h4>
                        <button type="button" class="btn-close" id="invoice-detail-modal-close" aria-label="Cerrar"></button>
                    </div>
                    <div id="invoice-detail-modal-body"></div>
                </div>
            </div>
        </div>

        <div id="new-invoice-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 480px;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">Nueva factura</h4>
                        <button type="button" class="btn-close" id="new-invoice-modal-close" aria-label="Cerrar"></button>
                    </div>
                    <p class="text-muted small mb-3">¿Qué tipo de comprobante querés crear?</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary text-start">
                            <strong class="d-block">Comprobante electrónico</strong>
                            <small class="d-block fw-normal">Factura o nota de crédito con validez tributaria, se envía a Hacienda.</small>
                        </a>
                        <a href="{{ route('admin.invoices.payment-receipt.create') }}" class="btn btn-outline-secondary text-start">
                            <strong class="d-block">Comprobante de pago</strong>
                            <small class="d-block fw-normal">Constancia de un pago del inquilino, solo queda registrada en el sistema.</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const detailBackdrop = document.getElementById('invoice-detail-modal-backdrop');
                const detailBody = document.getElementById('invoice-detail-modal-body');

                document.querySelectorAll('.invoice-row').forEach(function(row) {
                    row.addEventListener('click', function() {
                        const template = document.getElementById(row.dataset.invoiceDetail);
                        if (!template) return;

                        detailBody.innerHTML = '';
                        detailBody.appendChild(template.content.cloneNode(true));
                        detailBackdrop.style.display = 'flex';
                    });
                });

                document.getElementById('invoice-detail-modal-close').addEventListener('click', function() {
                    detailBackdrop.style.display = 'none';
                });

                detailBackdrop.addEventListener('click', function(event) {
                    if (event.target === detailBackdrop) detailBackdrop.style.display = 'none';
                });

                const newInvoiceBackdrop = document.getElementById('new-invoice-modal-backdrop');

                document.getElementById('new-invoice-btn').addEventListener('click', function() {
                    newInvoiceBackdrop.style.display = 'flex';
                });

                document.getElementById('new-invoice-modal-close').addEventListener('click', function() {
                    newInvoiceBackdrop.style.display = 'none';
                });

                newInvoiceBackdrop.addEventListener('click', function(event) {
                    if (event.target === newInvoiceBackdrop) newInvoiceBackdrop.style.display = 'none';
                });
            });
        </script>
    </section>
@endsection
