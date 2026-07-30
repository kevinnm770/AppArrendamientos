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
                <h3>Nueva factura</h3>
                <p class="text-subtitle text-muted">Emite un comprobante electrónico con validez tributaria ante Hacienda.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index') }}">Invoices</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Electronic</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">Nueva factura</h4>
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Volver a facturas registradas</a>
            </div>
            <div class="card-body">
                <p class="note-cyan mb-4">
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

                <form method="POST" action="{{ route('admin.invoices.store') }}" class="row g-3" id="invoice-form">
                    @csrf
                    <input type="hidden" name="invoice_type" value="electronic">

                    <div class="col-12">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">1) Encabezado del comprobante</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tipo de documento</label>
                        <select name="document_type" class="form-select" id="document_type">
                            <option value="01" @selected(old('document_type', '01') === '01')>Factura Electrónica</option>
                            <option value="03" @selected(old('document_type') === '03')>Nota de Crédito Electrónica</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha de emisión</label>
                        <input type="date" name="date" id="invoice-date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Número factura</label>
                        <input type="text" class="form-control" value="{{ $nextInvoiceNumberPreview }}" disabled>
                        <small class="note-cyan">Consecutivo de 20 dígitos, asignado automáticamente al guardar.</small>
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
                        <small class="note-cyan">Al elegir el contrato se precarga la línea de canon, la fecha límite de pago y la mora sugerida.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select" id="currency" required>
                            <option value="CRC" @selected(old('currency', 'CRC') === 'CRC')>CRC</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                        </select>
                    </div>

                    <div class="col-12 credit-note-only d-none">
                        <h6 class="text-uppercase text-muted fw-bold mb-1 mt-5">Datos de la nota de crédito</h6>
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
                        <small class="note-cyan">Solo se muestran facturas ya enviadas a Hacienda del mismo contrato.</small>
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

                    <div class="col-12 mt-5">
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

                    <div class="col-12 mt-5">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">2) Líneas del comprobante</h6>
                        <hr class="mt-1 mb-2">
                        <p class="small note-cyan mb-2">
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
                                        <th>Total línea</th>
                                        <th style="width: 90px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="invoice-items-body">
                                    <tr id="invoice-items-empty-row">
                                        <td colspan="9" class="text-center text-muted">Aún no hay líneas. Usa "+ Añadir línea".</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7" class="text-end fw-bold">Subtotal</td>
                                        <td id="items-subtotal-display">0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="text-end fw-bold">Total comprobante</td>
                                        <td id="items-total-display">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item-btn">+ Añadir línea</button>
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">3) Condiciones comerciales</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Mora ₡/$ (opcional)</label>
                        <input type="number" step="0.01" min="0" name="late_fee_total" id="late-fee-total" class="form-control" value="{{ old('late_fee_total', '0') }}">
                        <small class="note-cyan">Se calcula sola según la política de mora del contrato; se agrega como línea propia ("Interés moratorio"), sin IVA. Puedes ajustarla a mano.</small>
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
                        <input type="date" name="due_date" id="due-date" class="form-control" value="{{ old('due_date') }}">
                        <small class="note-cyan">Se calcula sola desde el día de pago y los días de gracia del contrato.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Métodos de pago</label>
                        <small class="note-cyan d-block mb-1">Puedes marcar más de uno si el pago se dividió (ej. parte transferencia, parte efectivo).</small>
                        @php
                            $selectedPaymentMethods = old('payment_methods', ['transfer']);
                        @endphp
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
                        <input type="text" name="payment_method_other_description" id="payment-method-other-description" class="form-control mt-2 d-none" placeholder="Describe el método de pago" value="{{ old('payment_method_other_description') }}">
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
                        <button type="button" class="btn btn-primary" id="save-invoice-btn">Guardar factura</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="confirm-send-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1060; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 420px;">
                <div class="card-body">
                    <h5 class="mb-3">¿Confirmas guardar y enviar esta factura a Hacienda?</h5>
                    <p class="text-muted small mb-4">Una vez que Hacienda la acepte, el comprobante electrónico queda con validez tributaria y no se puede deshacer (para corregirla necesitarás una nota de crédito).</p>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="confirm-send-cancel">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="confirm-send-accept">Sí, guardar y enviar</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="item-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1050; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0" id="item-modal-title">Nueva línea</h4>
                        <button type="button" class="btn-close" id="item-modal-close" aria-label="Cerrar"></button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Código CABYS <span class="text-danger">*</span> <small class="note-cyan">(obligatorio en facturas electrónicas)</small></label>
                        <select class="form-select mb-2" id="modal-cabys-select">
                            @foreach (\App\Services\Hacienda\Catalogs::leaseCabysOptions() as $code => $label)
                                <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                            @endforeach
                            <option value="__other__">Otro código (buscar)...</option>
                        </select>
                        <div class="position-relative d-none" id="modal-cabys-search-wrapper">
                            <input type="text" class="form-control" id="modal-cabys-input" placeholder="Buscar por código o descripción y haz clic en una opción de la lista...">
                            <input type="hidden" id="modal-cabys-code">
                            <div class="list-group position-absolute w-100 shadow-sm" id="modal-cabys-results" style="z-index: 20; max-height: 220px; overflow-y: auto; display: none;"></div>
                        </div>
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
                            <select class="form-select" id="modal-unit">
                                @foreach (\App\Services\Hacienda\Catalogs::commonUnitsOfMeasure() as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unidad de medida comercial (opcional)</label>
                            <input type="text" class="form-control" id="modal-commercial-unit">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Condición IVA</label>
                            <select class="form-select" id="modal-tax-condition">
                                <option value="gravado">Gravado</option>
                                <option value="exento">Exento</option>
                                <option value="no_sujeto">No sujeto</option>
                            </select>
                            <small class="note-cyan">Gravado/Exento/No sujeto son categorías legales distintas en Costa Rica.</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label">IVA %</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="modal-tax-rate" value="13">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tipo transacciones (opcional)</label>
                        <select class="form-select" id="modal-transaction-type">
                            <option value="">Sin especificar</option>
                            @foreach (\App\Services\Hacienda\Catalogs::transactionTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $value }} - {{ $label }}</option>
                            @endforeach
                        </select>
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
                // CABYS que se precarga siempre en una línea nueva (primera opción curada).
                const defaultCabys = { code: '7211100000100', description: '', tax_rate: 13 };
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
                            <td>${lineTotal.toFixed(2)}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary edit-item-btn" data-index="${idx}">Editar</button>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" data-index="${idx}">&times;</button>
                            </td>
                        `;
                        itemsBody.appendChild(row);

                        ['cabys_code', 'commercial_code_type', 'commercial_code', 'description', 'quantity', 'unit_of_measure', 'transaction_type', 'commercial_unit_of_measure', 'item_type', 'unit_price', 'discount_percent', 'tax_condition', 'tax_rate'].forEach(function(field) {
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
                const cabysSelect = document.getElementById('modal-cabys-select');
                const cabysSearchWrapper = document.getElementById('modal-cabys-search-wrapper');
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
                const taxConditionField = document.getElementById('modal-tax-condition');
                const transactionTypeField = document.getElementById('modal-transaction-type');

                function toggleTaxRateField() {
                    const isGravado = taxConditionField.value === 'gravado';
                    taxRateField.disabled = !isGravado;
                    if (!isGravado) taxRateField.value = '0';
                }

                taxConditionField.addEventListener('change', toggleTaxRateField);

                function setCabysValue(code, description) {
                    const isCurated = code && Array.from(cabysSelect.options).some(function(opt) { return opt.value === code; });

                    if (isCurated) {
                        cabysSelect.value = code;
                        cabysSearchWrapper.classList.add('d-none');
                        cabysCodeField.value = code;
                        cabysInput.value = '';
                    } else if (code) {
                        cabysSelect.value = '__other__';
                        cabysSearchWrapper.classList.remove('d-none');
                        cabysCodeField.value = code;
                        cabysInput.value = description ? `${code} — ${description}` : code;
                    } else {
                        cabysSelect.value = defaultCabys.code;
                        cabysSearchWrapper.classList.add('d-none');
                        cabysCodeField.value = '';
                        cabysInput.value = '';
                    }
                }

                cabysSelect.addEventListener('change', function() {
                    if (cabysSelect.value === '__other__') {
                        cabysSearchWrapper.classList.remove('d-none');
                        cabysCodeField.value = '';
                        cabysInput.value = '';
                        cabysInput.focus();
                    } else {
                        cabysSearchWrapper.classList.add('d-none');
                        cabysCodeField.value = cabysSelect.value;
                    }
                });

                function resetModalFields() {
                    setCabysValue(defaultCabys.code, defaultCabys.description);
                    descriptionField.value = defaultCabys.description || '';
                    taxRateField.value = defaultCabys.tax_rate ?? 13;
                    quantityField.value = '1';
                    unitPriceField.value = '0';
                    discountField.value = '0';
                    commercialCodeTypeField.value = '';
                    commercialCodeField.value = '';
                    unitField.value = 'Unid';
                    commercialUnitField.value = '';
                    taxConditionField.value = 'gravado';
                    transactionTypeField.value = '';
                    toggleTaxRateField();
                }

                function fillModalFields(item) {
                    setCabysValue(item.cabys_code || '', item.description || '');
                    descriptionField.value = item.description || '';
                    quantityField.value = item.quantity ?? 1;
                    unitPriceField.value = item.unit_price ?? 0;
                    discountField.value = item.discount_percent ?? 0;
                    commercialCodeTypeField.value = item.commercial_code_type || '';
                    commercialCodeField.value = item.commercial_code || '';
                    unitField.value = item.unit_of_measure || 'Unid';
                    commercialUnitField.value = item.commercial_unit_of_measure || '';
                    taxConditionField.value = item.tax_condition || 'gravado';
                    taxRateField.value = item.tax_rate ?? 13;
                    transactionTypeField.value = item.transaction_type || '';
                    toggleTaxRateField();
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

                    // Si el usuario escribió el código a mano y nunca hizo clic en una
                    // sugerencia del listado, cabysCodeField queda vacío aunque el texto esté
                    // ahí — como respaldo se usa lo escrito (solo dígitos) en vez de perderlo.
                    let cabysCode = cabysCodeField.value;
                    if (!cabysCode && cabysInput.value.trim()) {
                        const typed = cabysInput.value.split('—')[0].trim();
                        cabysCode = typed.replace(/\D+/g, '') || null;
                    }

                    const item = {
                        cabys_code: cabysCode || null,
                        commercial_code_type: commercialCodeTypeField.value || null,
                        commercial_code: commercialCodeField.value || null,
                        description: descriptionField.value.trim(),
                        quantity: parseFloat(quantityField.value) || 1,
                        unit_of_measure: unitField.value || 'Unid',
                        transaction_type: transactionTypeField.value || null,
                        commercial_unit_of_measure: commercialUnitField.value || null,
                        item_type: 'service',
                        unit_price: parseFloat(unitPriceField.value) || 0,
                        discount_percent: parseFloat(discountField.value) || 0,
                        tax_condition: taxConditionField.value || 'gravado',
                        tax_rate: taxConditionField.value === 'gravado' ? (parseFloat(taxRateField.value) || 0) : 0,
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

                // --- Tipo de documento / nota de crédito ---
                const referenceInvoiceSelect = document.getElementById('reference_invoice_id');
                const creditNoteOnlyFields = document.querySelectorAll('.credit-note-only');

                function toggleCreditNoteFields() {
                    const isCreditNote = documentTypeSelect.value === '03';

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

                documentTypeSelect.addEventListener('change', toggleCreditNoteFields);
                toggleCreditNoteFields();

                // --- Datos del receptor (inquilino) + precarga de contrato ---
                const receptorDisplay = document.getElementById('receptor-display');
                const dueDateField = document.getElementById('due-date');
                const lateFeeField = document.getElementById('late-fee-total');
                const invoiceDateField = document.getElementById('invoice-date');

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

                function refreshBillingTerms(preloadCanon) {
                    const agreementId = agreementSelect.value;

                    if (!agreementId) return;

                    const url = billingTermsBaseUrl.replace('__ID__', agreementId) + `?date=${encodeURIComponent(invoiceDateField.value)}`;

                    fetch(url, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                    })
                        .then(function(response) { return response.ok ? response.json() : null; })
                        .then(function(data) {
                            if (!data) return;

                            document.getElementById('currency').value = data.currency;
                            dueDateField.value = data.suggested_due_date || '';
                            lateFeeField.value = data.suggested_late_fee || 0;
                            renderAll();

                            // La precarga del canon solo aplica a Factura Electrónica; una Nota
                            // de Crédito describe una corrección, no un cargo nuevo del contrato.
                            if (preloadCanon && documentTypeSelect.value !== '03' && lineItems.length === 0) {
                                lineItems.push({
                                    cabys_code: null,
                                    commercial_code_type: null,
                                    commercial_code: null,
                                    description: data.suggested_description,
                                    quantity: 1,
                                    unit_of_measure: 'Unid',
                                    transaction_type: null,
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
                }

                agreementSelect.addEventListener('change', function() {
                    updateReceptorDisplay();
                    filterReferenceInvoicesByAgreement();
                    refreshBillingTerms(true);
                });

                invoiceDateField.addEventListener('change', function() {
                    refreshBillingTerms(false);
                });

                // --- "Otros" en métodos de pago: pide describirlo ---
                const otherPaymentCheckbox = document.getElementById('pm-other');
                const otherPaymentDescription = document.getElementById('payment-method-other-description');

                if (otherPaymentCheckbox && otherPaymentDescription) {
                    function toggleOtherPaymentDescription() {
                        otherPaymentDescription.classList.toggle('d-none', !otherPaymentCheckbox.checked);
                        if (!otherPaymentCheckbox.checked) otherPaymentDescription.value = '';
                    }

                    otherPaymentCheckbox.addEventListener('change', toggleOtherPaymentDescription);
                    toggleOtherPaymentDescription();
                }

                // --- Confirmar antes de guardar y enviar a Hacienda ---
                const invoiceForm = document.getElementById('invoice-form');
                const saveInvoiceBtn = document.getElementById('save-invoice-btn');
                const confirmSendBackdrop = document.getElementById('confirm-send-modal-backdrop');

                saveInvoiceBtn.addEventListener('click', function() {
                    if (!invoiceForm.reportValidity()) return;
                    confirmSendBackdrop.style.display = 'flex';
                });

                document.getElementById('confirm-send-cancel').addEventListener('click', function() {
                    confirmSendBackdrop.style.display = 'none';
                });

                document.getElementById('confirm-send-accept').addEventListener('click', function() {
                    confirmSendBackdrop.style.display = 'none';
                    invoiceForm.submit();
                });

                confirmSendBackdrop.addEventListener('click', function(event) {
                    if (event.target === confirmSendBackdrop) confirmSendBackdrop.style.display = 'none';
                });

                updateReceptorDisplay();
                renderAll();
            });
        </script>
    </section>
@endsection
