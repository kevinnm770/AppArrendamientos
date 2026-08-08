@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Comprobantes electrónicos</h3>
                <p class="text-subtitle text-muted">Consulta y da seguimiento a los comprobantes electrónicas emitidas a tus arrendatarios ante Hacienda.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="d-flex flex-column align-items-start align-items-md-end gap-2">
                    <a href="{{ route('admin.invoices.create') }}" class="btn-brand-action">
                        <i class="bi bi-plus-lg"></i>
                        <span>Nuevo registro</span>
                    </a>
                    <nav aria-label="breadcrumb" class="breadcrumb-header">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Electronic-invoice</li>
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

                <form method="GET" action="{{ route('admin.invoices.index') }}" class="row g-2 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" class="form-control" placeholder="N° comprobante o cliente" value="{{ $filters['search'] ?? '' }}">
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $invoice)
                                <tr class="invoice-row" role="button" data-invoice-detail="invoice-detail-{{ $invoice->id }}" style="cursor:pointer;">
                                    <td>{{ $invoice->invoice_number }}</td>
                                    <td>{{ $invoice->roomer->legal_name ?? '-' }}</td>
                                    <td>{{ optional($invoice->date)->format('Y-m-d') }}</td>
                                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                    <td>{{ $haciendaStatusOptions[$invoice->electronicDetail->electronic_status ?? 'pending'] ?? 'Pendiente' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No se encontraron comprobantes.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $invoices->links() }}
            </div>
        </div>

        {{-- Plantillas ocultas con el detalle completo de cada comprobante. Al hacer clic en una
        fila, su contenido se copia dentro del modal compartido. --}}
        @foreach ($invoices as $invoice)
            <template id="invoice-detail-{{ $invoice->id }}">
                <div class="row mb-3">
                    <div class="col-4">
                        <strong class="d-block">Contrato</strong>
                        <span>#{{ $invoice->agreement_id }} - {{ $invoice->agreement->property->name ?? 'Sin propiedad' }}</span>
                    </div>
                    <div class="col-4">
                        <strong class="d-block">Cliente</strong>
                        <span>{{ $invoice->roomer->legal_name ?? '-' }}</span>
                    </div>
                    <div class="col-4">
                        <strong class="d-block">Fecha</strong>
                        <span>{{ optional($invoice->date)->format('Y-m-d') }}</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-4">
                        <strong class="d-block">Estado comprobante</strong>
                        <span>{{ $statusOptions[$invoice->status] ?? $invoice->status }}</span>
                    </div>
                    <div class="col-4">
                        <strong class="d-block">Estado Hacienda</strong>
                        <span>{{ $haciendaStatusOptions[$invoice->electronicDetail->electronic_status ?? 'pending'] ?? 'Pendiente' }}</span>
                    </div>
                    <div class="col-4">
                        <strong class="d-block">Condición venta</strong>
                        <span>{{ $saleConditionOptions[$invoice->sale_condition] ?? $invoice->sale_condition ?? '-' }}</span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <strong class="d-block">Métodos de pago</strong>
                        <span>
                            {{ collect($invoice->payment_methods ?? [])->map(fn ($method) => $paymentMethodOptions[$method] ?? $method)->implode(', ') ?: '-' }}
                            @if (in_array('other', $invoice->payment_methods ?? [], true) && $invoice->payment_method_other_description)
                                ({{ $invoice->payment_method_other_description }})
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
                                @forelse ($invoice->items as $item)
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
                    <strong class="d-block col-8" style="text-align: right;">Subtotal</strong>
                    <span class="col-4" style="text-align: right;">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</span>

                    @if ((float) $invoice->discount_total > 0)
                        <strong class="d-block col-8" style="text-align: right;">Descuento</strong>
                        <span class="col-4" style="text-align: right;">{{ $invoice->currency }} {{ number_format((float) $invoice->discount_total, 2) }}</span>
                    @endif

                    @if ((float) $invoice->tax_total > 0)
                        <strong class="d-block col-8" style="text-align: right;">Impuesto</strong>
                        <span class="col-4" style="text-align: right;">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</span>
                    @endif

                    @if ((float) $invoice->late_fee_total > 0)
                        <strong class="d-block col-8" style="text-align: right;">Mora</strong>
                        <span class="col-4" style="text-align: right;">{{ $invoice->currency }} {{ number_format((float) $invoice->late_fee_total, 2) }}</span>
                    @endif

                    <strong class="d-block col-8" style="text-align: right;">Total</strong>
                    <span class="col-4" style="text-align: right;" class="fw-bold">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</span>
                </div>

                @if ($invoice->reference_code || $invoice->notes)
                    <div class="row mb-3">
                        @if ($invoice->reference_code)
                            <div class="col-6">
                                <strong class="d-block">Referencia</strong>
                                <span>{{ $invoice->reference_code }}</span>
                            </div>
                        @endif
                        @if ($invoice->notes)
                            <div class="col-6">
                                <strong class="d-block">Notas</strong>
                                <span>{{ $invoice->notes }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <hr>

                @php $feStatus = $invoice->electronicDetail->electronic_status; @endphp

                <div class="mb-3">
                    <strong class="d-block">Último mensaje</strong>
                    <span>{{ $invoice->electronicDetail->last_transition_message ?? '-' }}</span>
                </div>

                <div class="mb-3">
                    <strong class="d-block mb-1">Trazabilidad</strong>
                    <div class="row row-cols-2 row-cols-md-5 g-2">
                        <div class="col">
                            <div class="small text-muted">Cola</div>
                            <div>{{ optional($invoice->electronicDetail->queued_at)->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="col">
                            <div class="small text-muted">Enviado</div>
                            <div>{{ optional($invoice->electronicDetail->sent_at)->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="col">
                            <div class="small text-muted">Aceptado</div>
                            <div>{{ optional($invoice->electronicDetail->accepted_at)->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="col">
                            <div class="small text-muted">Rechazado</div>
                            <div>{{ optional($invoice->electronicDetail->rejected_at)->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="col">
                            <div class="small text-muted">Error</div>
                            <div>{{ optional($invoice->electronicDetail->error_at)->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>
                    </div>
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
                        <a href="{{ route('admin.invoices.electronic.response', $invoice->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Descargar respuesta comprobante electrónico</a>
                    @endif

                    @if ($invoice->electronicDetail->xml_content)
                        <a href="{{ route('admin.invoices.electronic.xml', $invoice->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Descargar comprobante electrónico enviado</a>
                    @endif

                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Aún no disponible">Descargar documento PDF</button>
                </div>
            </template>
        @endforeach

        <div id="invoice-detail-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 820px; max-height: 90vh; overflow-y: auto;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">Detalle del comprobante</h4>
                        <button type="button" class="btn-close" id="invoice-detail-modal-close" aria-label="Cerrar"></button>
                    </div>
                    <div id="invoice-detail-modal-body"></div>
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
            });
        </script>
    </section>
@endsection
