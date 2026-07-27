@extends('layouts.admin')

@section('content')
    <section class="section">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Nueva factura</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    Emisión de comprobantes para arrendamiento conforme a la práctica de facturación electrónica en Costa Rica.
                    Cada línea requiere su código CABYS; el canon del contrato se puede precargar automáticamente.
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('admin.invoices.store') }}" class="row g-3" id="invoice-form">
                    @csrf

                    <div class="col-12">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">1) Encabezado del comprobante</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tipo de factura</label>
                        <select name="invoice_type" class="form-select" id="invoice_type" required>
                            <option value="electronic" @selected(old('invoice_type', 'electronic') === 'electronic')>Electrónica</option>
                            <option value="simple" @selected(old('invoice_type') === 'simple')>Simple</option>
                        </select>
                        <small class="text-muted">Usa "Electrónica" para envío y trazabilidad con Hacienda.</small>
                    </div>

                    <div class="col-md-3 electronic-only">
                        <label class="form-label">Tipo de documento</label>
                        <select name="document_type" class="form-select" id="document_type">
                            <option value="01" @selected(old('document_type', '01') === '01')>Factura Electrónica</option>
                            <option value="03" @selected(old('document_type') === '03')>Nota de Crédito Electrónica</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha de emisión</label>
                        <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Número factura</label>
                        <input type="text" class="form-control" value="{{ $nextInvoiceNumberPreview }}" disabled>
                        <small class="text-muted">Consecutivo de 20 dígitos, asignado automáticamente al guardar.</small>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Contrato</label>
                        <select name="agreement_id" class="form-select" id="agreement_id" required>
                            <option value="">Seleccione un contrato</option>
                            @foreach ($agreements as $agreement)
                                <option
                                    value="{{ $agreement->id }}"
                                    data-service-type="{{ $agreement->service_type }}"
                                    data-roomer-name="{{ $agreement->roomer->legal_name ?? '' }}"
                                    data-roomer-id-number="{{ $agreement->roomer->id_number ?? '' }}"
                                    data-roomer-phone="{{ $agreement->roomer->phone ?? '' }}"
                                    data-roomer-email="{{ $agreement->roomer->user->email ?? '' }}"
                                    @selected(old('agreement_id') == $agreement->id)
                                >
                                    #{{ $agreement->id }} - {{ $agreement->property->name ?? 'Sin propiedad' }} / {{ $agreement->roomer->legal_name ?? 'Sin arrendatario' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Al elegir el contrato se precarga la línea de canon con el monto vigente.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select" id="currency" required>
                            <option value="CRC" @selected(old('currency', 'CRC') === 'CRC')>CRC</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                        </select>
                    </div>

                    <div class="col-12 credit-note-only d-none">
                        <h6 class="text-uppercase text-muted fw-bold mb-1 mt-2">Datos de la nota de crédito</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-6 credit-note-only d-none">
                        <label class="form-label">Factura a corregir/anular</label>
                        <select name="reference_invoice_id" class="form-select" id="reference_invoice_id">
                            <option value="">Seleccione una factura</option>
                            @foreach ($referenceableInvoices as $refInvoice)
                                <option
                                    value="{{ $refInvoice->id }}"
                                    data-agreement-id="{{ $refInvoice->agreement_id }}"
                                    @selected(old('reference_invoice_id') == $refInvoice->id)
                                >
                                    {{ $refInvoice->invoice_number }} — {{ optional($refInvoice->date)->format('Y-m-d') }} — {{ $refInvoice->currency }} {{ number_format((float) $refInvoice->total, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Solo se muestran facturas ya enviadas a Hacienda del mismo contrato.</small>
                    </div>

                    <div class="col-md-3 credit-note-only d-none">
                        <label class="form-label">Motivo</label>
                        <select name="credit_note_reason_code" class="form-select">
                            @foreach ($creditNoteReasonOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('credit_note_reason_code', '02') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 credit-note-only d-none">
                        <label class="form-label">Detalle del motivo</label>
                        <input type="text" name="credit_note_reason_text" class="form-control" value="{{ old('credit_note_reason_text') }}" placeholder="Ej: corrección del canon de mayo">
                    </div>

                    <div class="col-12 mt-3">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Datos del emisor y del arrendatario</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <strong class="d-block mb-1">Emisor (arrendador)</strong>
                            <div>{{ $lessor->legal_name }}</div>
                            <div class="text-muted small">Cédula: {{ $lessor->id_number ?? 'Sin registrar' }}</div>
                            <div class="text-muted small">{{ $lessor->other_signs ?: $lessor->address ?: 'Dirección sin registrar' }}</div>
                            <div class="text-muted small">{{ $lessor->email ?? '-' }} · {{ $lessor->phone ?? '-' }}</div>
                            <a href="{{ route('admin.configuration.index') }}" class="small">Editar datos del emisor</a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100" id="receptor-display">
                            <strong class="d-block mb-1">Arrendatario (receptor)</strong>
                            <p class="text-muted small mb-0">Selecciona un contrato para ver los datos del inquilino.</p>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">2) Líneas del comprobante</h6>
                        <hr class="mt-1 mb-2">
                        <p class="small text-muted mb-2">
                            Cada línea requiere un código CABYS (catálogo oficial de Hacienda).
                        </p>
                    </div>

                    <div class="col-12">
                        <div id="invoice-items-hidden"></div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="invoice-items-table">
                                <thead>
                                    <tr>
                                        <th>CABYS</th>
                                        <th>Descripción</th>
                                        <th>Cant.</th>
                                        <th>Unidad</th>
                                        <th>Precio unit.</th>
                                        <th>Desc. %</th>
                                        <th>IVA %</th>
                                        <th>Tipo</th>
                                        <th>Total línea</th>
                                        <th style="width: 90px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="invoice-items-body">
                                    <tr id="invoice-items-empty-row">
                                        <td colspan="10" class="text-center text-muted">Aún no hay líneas. Usa "+ Añadir línea".</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8" class="text-end fw-bold">Subtotal</td>
                                        <td id="items-subtotal-display">0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="8" class="text-end fw-bold">Total comprobante</td>
                                        <td id="items-total-display">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item-btn">+ Añadir línea</button>
                    </div>

                    <div class="col-12 mt-3">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">3) Condiciones comerciales</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Mora ₡/$ (opcional)</label>
                        <input type="number" step="0.01" min="0" name="late_fee_total" class="form-control" value="{{ old('late_fee_total', '0') }}">
                        <small class="text-muted">Se agrega como línea propia ("Interés moratorio"), sin IVA.</small>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Condición venta</label>
                        <select name="sale_condition" class="form-select" id="sale_condition" required>
                            @foreach ($saleConditionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('sale_condition', 'cash') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha límite de pago</label>
                        <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Métodos de pago</label>
                        <small class="text-muted d-block mb-1">Puedes marcar más de uno si el pago se dividió (ej. parte transferencia, parte efectivo).</small>
                        @php($selectedPaymentMethods = old('payment_methods', ['transfer']))
                        <div class="row row-cols-2 row-cols-md-4 g-1">
                            @foreach ($paymentMethodOptions as $value => $label)
                                <div class="col">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="payment_methods[]"
                                            value="{{ $value }}"
                                            id="pm-{{ $value }}"
                                            @checked(in_array($value, $selectedPaymentMethods))
                                        >
                                        <label class="form-check-label" for="pm-{{ $value }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Referencia (opcional)</label>
                        <input type="text" name="reference_code" class="form-control" value="{{ old('reference_code') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Guardar factura</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Facturas registradas</h4>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Contrato</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado factura</th>
                            <th>Estado Hacienda</th>
                            <th>Acciones FE</th>
                            <th>Trazabilidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>#{{ $invoice->agreement_id }}</td>
                                <td>{{ $invoice->roomer->legal_name ?? '-' }}</td>
                                <td>{{ optional($invoice->date)->format('Y-m-d') }}</td>
                                <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
                                <td>{{ $statusOptions[$invoice->status] ?? $invoice->status }}</td>
                                <td>
                                    @if ($invoice->electronicDetail)
                                        {{ $haciendaStatusOptions[$invoice->electronicDetail->electronic_status ?? 'pending'] ?? 'Pendiente' }}
                                    @else
                                        No aplica
                                    @endif
                                </td>
                                <td>
                                    @if ($invoice->electronicDetail)
                                        @php($feStatus = $invoice->electronicDetail->electronic_status)
                                        <div class="d-grid gap-1">
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
                                                <span class="text-success small">Aceptada, nada pendiente.</span>
                                            @endif
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($invoice->electronicDetail)
                                        <small class="d-block text-muted">Cola: {{ optional($invoice->electronicDetail->queued_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                        <small class="d-block text-muted">Enviado: {{ optional($invoice->electronicDetail->sent_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                        <small class="d-block text-muted">Aceptado: {{ optional($invoice->electronicDetail->accepted_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                        <small class="d-block text-muted">Rechazado: {{ optional($invoice->electronicDetail->rejected_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                        <small class="d-block text-muted">Error: {{ optional($invoice->electronicDetail->error_at)->format('Y-m-d H:i:s') ?? '-' }}</small>
                                        <small class="d-block"><strong>Último mensaje:</strong> {{ $invoice->electronicDetail->last_transition_message ?? '-' }}</small>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">Aún no tienes facturas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="item-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0" id="item-modal-title">Nueva línea</h4>
                        <button type="button" class="btn-close" id="item-modal-close" aria-label="Cerrar"></button>
                    </div>

                    <div class="mb-3 position-relative">
                        <label class="form-label">Código CABYS</label>
                        <input type="text" class="form-control" id="modal-cabys-input" placeholder="Buscar por código o descripción...">
                        <input type="hidden" id="modal-cabys-code">
                        <div class="list-group position-absolute w-100 shadow-sm" id="modal-cabys-results" style="z-index: 20; max-height: 220px; overflow-y: auto; display: none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción de la línea</label>
                        <input type="text" class="form-control" id="modal-description">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label">Cantidad</label>
                            <input type="number" step="0.001" min="0.001" class="form-control" id="modal-quantity" value="1">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Precio unit.</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="modal-unit-price" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Desc. %</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="modal-discount" value="0">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Tipo código (opcional)</label>
                            <select class="form-select" id="modal-commercial-code-type">
                                <option value="">Sin especificar</option>
                                @foreach (\App\Services\Hacienda\Catalogs::commercialCodeTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Código (opcional)</label>
                            <input type="text" class="form-control" id="modal-commercial-code">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Unidad de medida</label>
                            <input type="text" class="form-control" id="modal-unit" value="Unid" list="unit-of-measure-options">
                            <datalist id="unit-of-measure-options">
                                @foreach (\App\Services\Hacienda\Catalogs::commonUnitsOfMeasure() as $unit)
                                    <option value="{{ $unit }}">
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unidad de medida comercial (opcional)</label>
                            <input type="text" class="form-control" id="modal-commercial-unit">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">IVA %</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="modal-tax-rate" value="13">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tipo de línea (opcional)</label>
                            <select class="form-select" id="modal-item-type">
                                <option value="service">Servicio</option>
                                <option value="goods">Mercancía</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-outline-secondary" id="item-modal-cancel">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="item-modal-save">Guardar línea</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const cabysSearchUrl = @json(route('admin.cabys.search'));
                const billingTermsBaseUrl = @json(route('admin.agreements.billing-terms', ['agreementId' => '__ID__']));
                const agreementSelect = document.getElementById('agreement_id');
                const documentTypeSelect = document.getElementById('document_type');

                let lineItems = @json(old('items', []));
                let editingIndex = null;

                // --- Tabla resumen + inputs ocultos que sí se envían con el formulario ---
                const itemsBody = document.getElementById('invoice-items-body');
                const emptyRow = document.getElementById('invoice-items-empty-row');
                const hiddenContainer = document.getElementById('invoice-items-hidden');
                const subtotalDisplay = document.getElementById('items-subtotal-display');
                const totalDisplay = document.getElementById('items-total-display');
                const itemTypeLabels = {service: 'Servicio', goods: 'Mercancía'};

                function computeLineTotal(item) {
                    const qty = parseFloat(item.quantity) || 0;
                    const price = parseFloat(item.unit_price) || 0;
                    const discountPct = parseFloat(item.discount_percent) || 0;
                    const taxPct = parseFloat(item.tax_rate) || 0;

                    const gross = qty * price;
                    const discountTotal = gross * (discountPct / 100);
                    const lineSubtotal = gross - discountTotal;
                    const taxTotal = lineSubtotal * (taxPct / 100);

                    return {lineSubtotal, lineTotal: lineSubtotal + taxTotal};
                }

                function renderAll() {
                    itemsBody.querySelectorAll('.item-row').forEach(function(row) { row.remove(); });
                    hiddenContainer.innerHTML = '';

                    let subtotal = 0;
                    let total = 0;

                    lineItems.forEach(function(item, idx) {
                        const {lineSubtotal, lineTotal} = computeLineTotal(item);
                        subtotal += lineSubtotal;
                        total += lineTotal;

                        const row = document.createElement('tr');
                        row.className = 'item-row';
                        row.innerHTML = `
                            <td>${item.cabys_code || '-'}</td>
                            <td>${item.description || ''}</td>
                            <td>${item.quantity}</td>
                            <td>${item.unit_of_measure || 'Unid'}</td>
                            <td>${(parseFloat(item.unit_price) || 0).toFixed(2)}</td>
                            <td>${item.discount_percent || 0}</td>
                            <td>${item.tax_rate || 0}</td>
                            <td>${itemTypeLabels[item.item_type] || 'Servicio'}</td>
                            <td>${lineTotal.toFixed(2)}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary edit-item-btn" data-index="${idx}">Editar</button>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" data-index="${idx}">&times;</button>
                            </td>
                        `;
                        itemsBody.appendChild(row);

                        ['cabys_code', 'commercial_code_type', 'commercial_code', 'description', 'quantity', 'unit_of_measure', 'commercial_unit_of_measure', 'item_type', 'unit_price', 'discount_percent', 'tax_rate'].forEach(function(field) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `items[${idx}][${field}]`;
                            input.value = item[field] ?? '';
                            hiddenContainer.appendChild(input);
                        });
                    });

                    emptyRow.style.display = lineItems.length ? 'none' : '';

                    const lateFee = parseFloat(document.querySelector('[name="late_fee_total"]').value) || 0;
                    subtotalDisplay.textContent = subtotal.toFixed(2);
                    totalDisplay.textContent = (total + lateFee).toFixed(2);

                    itemsBody.querySelectorAll('.edit-item-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() { openModal(parseInt(btn.dataset.index, 10)); });
                    });
                    itemsBody.querySelectorAll('.remove-item-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            lineItems.splice(parseInt(btn.dataset.index, 10), 1);
                            renderAll();
                        });
                    });
                }

                document.querySelector('[name="late_fee_total"]').addEventListener('input', renderAll);

                // --- Modal "Nueva línea" ---
                const backdrop = document.getElementById('item-modal-backdrop');
                const modalTitle = document.getElementById('item-modal-title');
                const cabysInput = document.getElementById('modal-cabys-input');
                const cabysCodeField = document.getElementById('modal-cabys-code');
                const cabysResults = document.getElementById('modal-cabys-results');
                const descriptionField = document.getElementById('modal-description');
                const quantityField = document.getElementById('modal-quantity');
                const unitPriceField = document.getElementById('modal-unit-price');
                const discountField = document.getElementById('modal-discount');
                const commercialCodeTypeField = document.getElementById('modal-commercial-code-type');
                const commercialCodeField = document.getElementById('modal-commercial-code');
                const unitField = document.getElementById('modal-unit');
                const commercialUnitField = document.getElementById('modal-commercial-unit');
                const taxRateField = document.getElementById('modal-tax-rate');
                const itemTypeField = document.getElementById('modal-item-type');

                function resetModalFields() {
                    cabysInput.value = '';
                    cabysCodeField.value = '';
                    descriptionField.value = '';
                    quantityField.value = '1';
                    unitPriceField.value = '0';
                    discountField.value = '0';
                    commercialCodeTypeField.value = '';
                    commercialCodeField.value = '';
                    unitField.value = 'Unid';
                    commercialUnitField.value = '';
                    taxRateField.value = '13';
                    itemTypeField.value = 'service';
                }

                function fillModalFields(item) {
                    cabysInput.value = item.cabys_code || '';
                    cabysCodeField.value = item.cabys_code || '';
                    descriptionField.value = item.description || '';
                    quantityField.value = item.quantity ?? 1;
                    unitPriceField.value = item.unit_price ?? 0;
                    discountField.value = item.discount_percent ?? 0;
                    commercialCodeTypeField.value = item.commercial_code_type || '';
                    commercialCodeField.value = item.commercial_code || '';
                    unitField.value = item.unit_of_measure || 'Unid';
                    commercialUnitField.value = item.commercial_unit_of_measure || '';
                    taxRateField.value = item.tax_rate ?? 13;
                    itemTypeField.value = item.item_type || 'service';
                }

                function openModal(index) {
                    editingIndex = index;
                    modalTitle.textContent = index === null ? 'Nueva línea' : 'Editar línea';

                    if (index === null) {
                        resetModalFields();
                    } else {
                        fillModalFields(lineItems[index]);
                    }

                    cabysResults.style.display = 'none';
                    backdrop.style.display = 'flex';
                }

                function closeModal() {
                    backdrop.style.display = 'none';
                    editingIndex = null;
                }

                window.openNewItemModal = function() { openModal(null); };

                document.getElementById('add-item-btn').addEventListener('click', function() { openModal(null); });
                document.getElementById('item-modal-close').addEventListener('click', closeModal);
                document.getElementById('item-modal-cancel').addEventListener('click', closeModal);
                backdrop.addEventListener('click', function(event) {
                    if (event.target === backdrop) closeModal();
                });

                document.getElementById('item-modal-save').addEventListener('click', function() {
                    if (!descriptionField.value.trim()) {
                        descriptionField.focus();
                        return;
                    }

                    const item = {
                        cabys_code: cabysCodeField.value || null,
                        commercial_code_type: commercialCodeTypeField.value || null,
                        commercial_code: commercialCodeField.value || null,
                        description: descriptionField.value.trim(),
                        quantity: parseFloat(quantityField.value) || 1,
                        unit_of_measure: unitField.value || 'Unid',
                        commercial_unit_of_measure: commercialUnitField.value || null,
                        item_type: itemTypeField.value || 'service',
                        unit_price: parseFloat(unitPriceField.value) || 0,
                        discount_percent: parseFloat(discountField.value) || 0,
                        tax_rate: parseFloat(taxRateField.value) || 0,
                    };

                    if (editingIndex === null) {
                        lineItems.push(item);
                    } else {
                        lineItems[editingIndex] = item;
                    }

                    renderAll();
                    closeModal();
                });

                let debounceTimer = null;
                cabysInput.addEventListener('input', function() {
                    cabysCodeField.value = '';
                    clearTimeout(debounceTimer);
                    const term = cabysInput.value.trim();

                    if (term.length < 3) {
                        cabysResults.style.display = 'none';
                        cabysResults.innerHTML = '';
                        return;
                    }

                    debounceTimer = setTimeout(function() {
                        fetch(`${cabysSearchUrl}?q=${encodeURIComponent(term)}`, {
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                        })
                            .then(function(response) { return response.json(); })
                            .then(function(data) {
                                cabysResults.innerHTML = '';
                                (data.results || []).forEach(function(result) {
                                    const option = document.createElement('button');
                                    option.type = 'button';
                                    option.className = 'list-group-item list-group-item-action small';
                                    option.textContent = `${result.code} — ${result.description}`;
                                    option.addEventListener('click', function() {
                                        cabysInput.value = `${result.code} — ${result.description}`;
                                        cabysCodeField.value = result.code;
                                        descriptionField.value = descriptionField.value || result.description;
                                        taxRateField.value = result.tax_rate ?? 13;
                                        cabysResults.style.display = 'none';
                                    });
                                    cabysResults.appendChild(option);
                                });
                                cabysResults.style.display = (data.results || []).length ? 'block' : 'none';
                            })
                            .catch(function() { cabysResults.style.display = 'none'; });
                    }, 250);
                });

                // --- Tipo de factura / tipo de documento / nota de crédito ---
                const invoiceTypeSelect = document.getElementById('invoice_type');
                const referenceInvoiceSelect = document.getElementById('reference_invoice_id');
                const electronicOnlyFields = document.querySelectorAll('.electronic-only');
                const creditNoteOnlyFields = document.querySelectorAll('.credit-note-only');

                function toggleElectronicFields() {
                    const isElectronic = invoiceTypeSelect.value === 'electronic';
                    electronicOnlyFields.forEach(function(field) {
                        field.style.display = isElectronic ? '' : 'none';
                    });

                    if (!isElectronic) {
                        documentTypeSelect.value = '01';
                        toggleCreditNoteFields();
                    }
                }

                function toggleCreditNoteFields() {
                    const isCreditNote = invoiceTypeSelect.value === 'electronic' && documentTypeSelect.value === '03';

                    creditNoteOnlyFields.forEach(function(field) {
                        field.classList.toggle('d-none', !isCreditNote);
                    });

                    if (referenceInvoiceSelect) {
                        referenceInvoiceSelect.required = isCreditNote;
                    }

                    filterReferenceInvoicesByAgreement();
                }

                function filterReferenceInvoicesByAgreement() {
                    if (!referenceInvoiceSelect) return;

                    const agreementId = agreementSelect.value;

                    referenceInvoiceSelect.querySelectorAll('option[value]').forEach(function(option) {
                        if (!option.value) return;

                        const matches = !agreementId || option.dataset.agreementId === agreementId;
                        option.hidden = !matches;

                        if (!matches && option.selected) {
                            referenceInvoiceSelect.value = '';
                        }
                    });
                }

                invoiceTypeSelect.addEventListener('change', toggleElectronicFields);
                documentTypeSelect.addEventListener('change', toggleCreditNoteFields);
                toggleElectronicFields();
                toggleCreditNoteFields();

                // --- Datos del receptor (inquilino) + precarga de contrato ---
                const receptorDisplay = document.getElementById('receptor-display');

                function updateReceptorDisplay() {
                    const option = agreementSelect.selectedOptions[0];

                    if (!option || !option.value) {
                        receptorDisplay.innerHTML = '<strong class="d-block mb-1">Arrendatario (receptor)</strong><p class="text-muted small mb-0">Selecciona un contrato para ver los datos del inquilino.</p>';
                        return;
                    }

                    receptorDisplay.innerHTML = `
                        <strong class="d-block mb-1">Arrendatario (receptor)</strong>
                        <div>${option.dataset.roomerName || 'Sin nombre'}</div>
                        <div class="text-muted small">Cédula: ${option.dataset.roomerIdNumber || 'Sin registrar'}</div>
                        <div class="text-muted small">${option.dataset.roomerEmail || '-'} · ${option.dataset.roomerPhone || '-'}</div>
                    `;
                }

                agreementSelect.addEventListener('change', function() {
                    updateReceptorDisplay();
                    filterReferenceInvoicesByAgreement();

                    const agreementId = agreementSelect.value;

                    // La precarga del canon solo aplica a Factura Electrónica; una Nota de
                    // Crédito describe una corrección, no un cargo nuevo del contrato.
                    if (!agreementId || documentTypeSelect.value === '03') {
                        return;
                    }

                    fetch(billingTermsBaseUrl.replace('__ID__', agreementId), {
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                    })
                        .then(function(response) { return response.ok ? response.json() : null; })
                        .then(function(data) {
                            if (!data) return;

                            document.getElementById('currency').value = data.currency;

                            if (lineItems.length === 0) {
                                lineItems.push({
                                    cabys_code: null,
                                    commercial_code_type: null,
                                    commercial_code: null,
                                    description: data.suggested_description,
                                    quantity: 1,
                                    unit_of_measure: 'Unid',
                                    commercial_unit_of_measure: null,
                                    item_type: 'service',
                                    unit_price: data.amount,
                                    discount_percent: 0,
                                    tax_rate: data.suggested_tax_rate,
                                });
                                renderAll();
                            }
                        })
                        .catch(function() {});
                });

                updateReceptorDisplay();
                renderAll();
            });
        </script>
    </section>
@endsection
