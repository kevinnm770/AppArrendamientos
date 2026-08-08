@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Nuevo comprobante electrónico</h3>
                <p class="text-subtitle text-muted">Emite un comprobante electrónico con validez tributaria ante Hacienda.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index') }}">Electronic-invoice</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Register</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card mb-4">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.invoices.store') }}" enctype="multipart/form-data" class="row g-3" id="invoice-form">
                    @csrf
                    <input type="hidden" name="invoice_type" value="electronic">

                    <div class="col-12">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Encabezado del comprobante</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo de documento</label>
                        <select name="document_type" class="form-select" id="document_type">
                            <option value="01" @selected(old('document_type', '01') === '01')>Factura Electrónica</option>
                            <option value="03" @selected(old('document_type') === '03')>Nota de Crédito Electrónica</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha de emisión</label>
                        <input type="date" name="date" id="invoice-date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Número del comprobante</label>
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

                    <div class="col-12 mt-4">
                        <div class="border rounded p-3" id="tenant-balance-panel">
                            <strong class="d-block mb-2">Saldo pendiente del inquilino</strong>
                            <p class="text-muted small mb-0" id="tenant-balance-placeholder">Selecciona un contrato para ver el saldo pendiente.</p>
                            <div class="row row-cols-2 row-cols-md-5 g-2 d-none" id="tenant-balance-figures">
                                <div class="col">
                                    <div class="small text-muted">Alquiler</div>
                                    <div class="fw-bold" id="tb-rent">0.00</div>
                                </div>
                                <div class="col">
                                    <div class="small text-muted">Depósito</div>
                                    <div class="fw-bold" id="tb-deposit">0.00</div>
                                </div>
                                <div class="col">
                                    <div class="small text-muted">Morosidad alquiler</div>
                                    <div class="fw-bold" id="tb-late-fee-rent">0.00</div>
                                </div>
                                <div class="col">
                                    <div class="small text-muted">Morosidad depósito</div>
                                    <div class="fw-bold" id="tb-late-fee-deposit">0.00</div>
                                </div>
                                <div class="col">
                                    <div class="small text-muted">Saldo a favor disp.</div>
                                    <div class="fw-bold text-success" id="tb-credit-available">0.00</div>
                                </div>
                            </div>
                            <div class="mt-2 d-none" id="tenant-balance-total-wrap">
                                <strong>Total pendiente: <span id="tb-total">0.00</span> <span id="tb-currency"></span></strong>
                            </div>
                            <small class="note-cyan d-block mt-2 d-none" id="tenant-balance-note">Calculado a la fecha del comprobante, según los comprobantes previos no anulados (no incluye las líneas de este comprobante).</small>
                        </div>
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Líneas del comprobante</h6>
                        <hr class="mt-1 mb-2">
                        <p class="small note-cyan mb-2">
                            Desglosa cada concepto que se factura. El total del comprobante se calcula automáticamente.
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
                                        <th>Concepto</th>
                                        <th>Precio</th>
                                        <th>Desc. %</th>
                                        <th>IVA %</th>
                                        <th>Total</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="invoice-items-body">
                                    <tr id="invoice-items-empty-row">
                                        <td colspan="8" class="text-center text-muted">Aún no hay líneas. Usa "+ Añadir línea".</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3"></td>
                                        <td id="items-subtotal-display">0.00</td>
                                        <td></td>
                                        <td></td>
                                        <td class="fw-bold" id="items-total-display" title="Total comprobante, ya con el saldo a favor aplicado descontado">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item-btn">+ Añadir línea</button>
                    </div>

                    <div class="col-12 mt-5">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Condiciones comerciales</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Mora ₡/$ (opcional)</label>
                        <input type="number" step="0.01" min="0" name="late_fee_total" id="late-fee-total" class="form-control" value="{{ old('late_fee_total', '0') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Condición venta</label>
                        <select name="sale_condition" class="form-select" id="sale_condition" required>
                            @foreach ($saleConditionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('sale_condition', 'cash') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha límite de pago</label>
                        <input type="date" name="due_date" id="due-date" class="form-control" value="{{ old('due_date') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block mb-0">Métodos de pago</label>
                        <small class="note-cyan d-block mb-2">Puedes marcar más de uno si el pago se dividió (ej. parte transferencia, parte efectivo).</small>
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

                    <div class="col-12 invoice-payment-only">
                        <div id="payment-receipt-hidden"></div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="create_payment_receipt" value="1" id="create-payment-receipt-toggle" @checked(old('create_payment_receipt'))>
                            <label class="form-check-label" for="create-payment-receipt-toggle">El arrendatario(a) ya realizó un pago</label>
                        </div>
                        <small class="note-cyan d-block" id="payment-receipt-default-note">Al marcarlo se abre un formulario de comprobante de pago precargado con los datos de esta factura (editable). Es un registro interno, no se envía a Hacienda.</small>
                        <small class="note-cyan d-block d-none" id="payment-receipt-summary-note">
                            <span id="payment-receipt-summary-text"></span> <a href="#" id="payment-receipt-edit-link">Editar</a>
                        </small>
                    </div>

                    <div class="col-12 mt-5" style="text-align: right;">
                        <a href="{{ route('admin.invoices.index') }}" class="btn btn-light-secondary">Cancelar</a>
                        <button type="button" class="btn btn-primary" id="save-invoice-btn">Guardar comprobante</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="confirm-send-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1060; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 860px; max-height: 90vh; overflow-y: auto;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">¿Confirmas guardar y enviar este comprobante a Hacienda?</h4>
                        <button type="button" class="btn-close" id="confirm-send-close" aria-label="Cerrar"></button>
                    </div>
                    <p class="text-muted small mb-4">Una vez que Hacienda la acepte, el comprobante electrónico queda con validez tributaria y no se puede deshacer (para corregirla necesitarás una nota de crédito). Revisa el resumen y el XML antes de confirmar.</p>

                    <div id="confirm-summary"></div>

                    <hr>

                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <strong class="d-block">XML del comprobante <br> <small class="note-cyan">Vista previa sin firmar (la firma y el envío ocurren al confirmar)</small></strong>
                        <span class="badge bg-secondary" id="confirm-xml-status">Generando...</span>
                    </div>
                    <pre id="confirm-xml-output" class="small p-2 border rounded" style="max-height: 260px; overflow: auto; white-space: pre-wrap; word-break: break-all; background: rgba(127,127,127,.08);"></pre>
                    <div class="alert alert-danger d-none" id="confirm-xml-error"></div>
                    <small class="note-cyan d-block mb-4">El número de consecutivo que aparece en el XML es una vista previa; el definitivo se asigna justo al confirmar el envío.</small>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="confirm-send-cancel">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="confirm-send-accept" disabled>Sí, guardar y enviar</button>
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

                    <div class="mb-3">
                        <label class="form-label">Concepto <span class="text-danger">*</span> <small class="note-cyan">(uso interno, no se envía a Hacienda)</small></label>
                        <select class="form-select" id="modal-concept" required>
                            <option value="">Seleccione...</option>
                            @foreach ($conceptOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Precio unit.</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="modal-unit-price" value="0">
                        </div>
                        <div class="col-6">
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

        <div id="payment-receipt-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1055; align-items:center; justify-content:center;">
            <div class="card" style="width: 100%; max-width: 860px; max-height: 90vh; overflow-y: auto;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h4 class="mb-0">Comprobante de pago para esta factura</h4>
                        <button type="button" class="btn-close" id="pr-modal-close" aria-label="Cerrar"></button>
                    </div>
                    <p class="text-muted small mb-4">Precargado con los datos de esta factura (ajústalo si hace falta). Es un registro interno (comprobante de pago), no se envía a Hacienda.</p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="pr-date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Moneda</label>
                            <select class="form-select" id="pr-currency" required>
                                <option value="CRC">CRC</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 mb-2">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Líneas del comprobante</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="pr-items-table">
                            <thead>
                                <tr>
                                    <th>Descripción</th>
                                    <th style="width:170px;">Concepto</th>
                                    <th style="width:150px;">Precio unit.</th>
                                    <th style="width:120px;">Total línea</th>
                                    <th style="width:190px;">Evidencia</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="pr-items-body"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Total</td>
                                    <td class="fw-bold" id="pr-total-display">0.00</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pr-add-item-btn">+ Añadir línea</button>
                    <button type="button" class="btn btn-sm btn-outline-info" id="pr-add-credit-application-btn">Aplicar saldo a favor</button>
                    <small class="note-cyan d-block mt-1">"Aplicar saldo a favor" agrega una línea que consume saldo a favor ya disponible (no dinero nuevo) — queda registrada también como su propio movimiento en Aplicación de saldo a favor.</small>

                    <div class="mt-4">
                        <label class="form-label d-block mb-0">Métodos de pago</label>
                        <small class="note-cyan d-block mb-2">Puedes marcar más de uno si el pago se dividió.</small>
                        <div class="row row-cols-2 row-cols-md-4 g-1" id="pr-payment-methods">
                            @foreach ($paymentMethodOptions as $value => $label)
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $value }}" id="pr-pm-{{ $value }}">
                                        <label class="form-check-label" for="pr-pm-{{ $value }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <input type="text" class="form-control mt-2 d-none" id="pr-payment-method-other-description" placeholder="Describe el método de pago">
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" class="form-control" id="pr-notes">
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary" id="pr-modal-cancel">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="pr-modal-save">Guardar comprobante</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const cabysSearchUrl = @json(route('admin.cabys.search'));
                // CABYS que se precarga siempre en una línea nueva: "Servicios de
                // arrendamiento o alquiler de bienes, n.c.p." — genérico, sirve tanto para
                // vivienda como para local comercial (a diferencia de los códigos 7211*,
                // que además traen condición de tope salarial que no siempre aplica).
                const defaultCabys = { code: '7329000000000', description: '', tax_rate: 13 };
                const lineConceptOptions = @json($conceptOptions);
                const appliableConceptOptions = @json($appliableConceptOptions);
                const billingTermsBaseUrl = @json(route('admin.agreements.billing-terms', ['agreementId' => '__ID__']));
                const tenantBalanceBaseUrl = @json(route('admin.agreements.tenant-balance', ['agreementId' => '__ID__']));
                const agreementSelect = document.getElementById('agreement_id');
                const documentTypeSelect = document.getElementById('document_type');

                let lineItems = @json(old('items', []));
                let editingIndex = null;
                // Unidad de medida sugerida según el tipo de servicio del contrato elegido
                // (Catalogs::commonUnitsOfMeasure() — 'Al' habitacional / 'Alc' comercial),
                // actualizada por refreshBillingTerms() y usada como default de línea nueva.
                let suggestedUnitOfMeasure = 'Unid';

                // --- Tabla resumen + inputs ocultos que sí se envían con el formulario ---
                const itemsBody = document.getElementById('invoice-items-body');
                const emptyRow = document.getElementById('invoice-items-empty-row');
                const hiddenContainer = document.getElementById('invoice-items-hidden');
                const subtotalDisplay = document.getElementById('items-subtotal-display');
                const totalDisplay = document.getElementById('items-total-display');

                function updateFooterTotals() {
                    let subtotal = 0;
                    let total = 0;

                    lineItems.forEach(function(item) {
                        const {lineSubtotal, lineTotal} = computeLineTotal(item);
                        subtotal += lineSubtotal;
                        total += lineTotal;
                    });

                    const lateFee = parseFloat(document.querySelector('[name="late_fee_total"]').value) || 0;
                    subtotalDisplay.textContent = subtotal.toFixed(2);
                    totalDisplay.textContent = (total + lateFee).toFixed(2);
                }

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

                    lineItems.forEach(function(item, idx) {
                        const {lineTotal} = computeLineTotal(item);

                        const row = document.createElement('tr');
                        row.className = 'item-row';
                        row.dataset.index = idx;
                        row.setAttribute('role', 'button');
                        row.style.cursor = 'pointer';
                        row.title = 'Clic para editar esta línea';
                        row.innerHTML = `
                            <td>${item.cabys_code || '-'}</td>
                            <td>${item.description || ''}</td>
                            <td>${lineConceptOptions[item.concept] || '-'}</td>
                            <td>${(parseFloat(item.unit_price) || 0).toFixed(2)}</td>
                            <td>${item.discount_percent || 0}</td>
                            <td>${item.tax_rate || 0}</td>
                            <td>${lineTotal.toFixed(2)}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" style="border-radius:150px;height:30px;width:30px;" data-index="${idx}">&times;</button>
                            </td>
                        `;
                        itemsBody.appendChild(row);

                        ['cabys_code', 'commercial_code_type', 'commercial_code', 'description', 'quantity', 'concept', 'unit_of_measure', 'transaction_type', 'commercial_unit_of_measure', 'item_type', 'unit_price', 'discount_percent', 'tax_condition', 'tax_rate'].forEach(function(field) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `items[${idx}][${field}]`;
                            input.value = item[field] ?? '';
                            hiddenContainer.appendChild(input);
                        });
                    });

                    emptyRow.style.display = lineItems.length ? 'none' : '';

                    updateFooterTotals();

                    itemsBody.querySelectorAll('.item-row').forEach(function(row) {
                        row.addEventListener('click', function() {
                            openModal(parseInt(row.dataset.index, 10));
                        });
                    });
                    itemsBody.querySelectorAll('.remove-item-btn').forEach(function(btn) {
                        btn.addEventListener('click', function(event) {
                            // Sin esto, el clic también burbujea a la fila y abre el modal de
                            // edición justo cuando la línea está a punto de borrarse.
                            event.stopPropagation();
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
                const conceptField = document.getElementById('modal-concept');
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
                        // Sin código (línea generada automáticamente al elegir contrato, o
                        // cualquier línea vieja sin CABYS): el select ya mostraba el CABYS
                        // por defecto seleccionado, pero antes el campo real que se guarda
                        // (cabysCodeField) se quedaba vacío si el usuario no volvía a tocar
                        // el select — por eso "Guardar línea" no agregaba ningún CABYS.
                        cabysSelect.value = defaultCabys.code;
                        cabysSearchWrapper.classList.add('d-none');
                        cabysCodeField.value = defaultCabys.code;
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
                    conceptField.value = '';
                    taxRateField.value = 13;
                    unitPriceField.value = '0';
                    discountField.value = '0';
                    commercialCodeTypeField.value = '';
                    commercialCodeField.value = '';
                    unitField.value = suggestedUnitOfMeasure;
                    commercialUnitField.value = '';
                    taxConditionField.value = 'gravado';
                    transactionTypeField.value = '';
                    toggleTaxRateField();
                }

                function fillModalFields(item) {
                    setCabysValue(item.cabys_code || '', item.description || '');
                    descriptionField.value = item.description || '';
                    conceptField.value = item.concept || '';
                    unitPriceField.value = item.unit_price ?? 0;
                    discountField.value = item.discount_percent ?? 0;
                    commercialCodeTypeField.value = item.commercial_code_type || '';
                    commercialCodeField.value = item.commercial_code || '';
                    unitField.value = item.unit_of_measure || suggestedUnitOfMeasure;
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

                    if (!conceptField.value) {
                        conceptField.focus();
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
                        concept: conceptField.value || null,
                        quantity: 1,
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
                                        // El IVA se deja como está (13% por defecto): la tarifa propia
                                        // del código CABYS no debe pisar la sugerencia general.
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
                const invoicePaymentOnlyFields = document.querySelectorAll('.invoice-payment-only');
                const createPaymentReceiptToggle = document.getElementById('create-payment-receipt-toggle');

                function toggleCreditNoteFields() {
                    const isCreditNote = documentTypeSelect.value === '03';

                    creditNoteOnlyFields.forEach(function(field) {
                        field.classList.toggle('d-none', !isCreditNote);
                    });

                    // Una Nota de Crédito no representa un cobro nuevo: no tiene sentido
                    // ofrecer generar un comprobante de pago para ella.
                    invoicePaymentOnlyFields.forEach(function(field) {
                        field.classList.toggle('d-none', isCreditNote);
                    });
                    if (isCreditNote && createPaymentReceiptToggle) {
                        createPaymentReceiptToggle.checked = false;
                    }

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

                const tenantBalancePlaceholder = document.getElementById('tenant-balance-placeholder');
                const tenantBalanceFigures = document.getElementById('tenant-balance-figures');
                const tenantBalanceTotalWrap = document.getElementById('tenant-balance-total-wrap');
                const tenantBalanceNote = document.getElementById('tenant-balance-note');

                function renderTenantBalance(data) {
                    if (!data) return;

                    document.getElementById('tb-rent').textContent = data.rent.balance.toFixed(2);
                    document.getElementById('tb-deposit').textContent = data.deposit.balance.toFixed(2);
                    document.getElementById('tb-late-fee-rent').textContent = data.late_fee_rent.balance.toFixed(2);
                    document.getElementById('tb-late-fee-deposit').textContent = data.late_fee_deposit.balance.toFixed(2);
                    document.getElementById('tb-credit-available').textContent = data.credit_balance.available.toFixed(2);

                    const total = data.rent.balance + data.deposit.balance + data.late_fee_rent.balance + data.late_fee_deposit.balance;
                    document.getElementById('tb-total').textContent = total.toFixed(2);
                    document.getElementById('tb-currency').textContent = data.currency || '';

                    tenantBalancePlaceholder.classList.add('d-none');
                    tenantBalanceFigures.classList.remove('d-none');
                    tenantBalanceTotalWrap.classList.remove('d-none');
                    tenantBalanceNote.classList.remove('d-none');
                }

                function resetTenantBalance() {
                    tenantBalancePlaceholder.classList.remove('d-none');
                    tenantBalanceFigures.classList.add('d-none');
                    tenantBalanceTotalWrap.classList.add('d-none');
                    tenantBalanceNote.classList.add('d-none');
                }

                function refreshTenantBalance() {
                    const agreementId = agreementSelect.value;

                    if (!agreementId) {
                        resetTenantBalance();
                        return;
                    }

                    const url = new URL(tenantBalanceBaseUrl.replace('__ID__', agreementId), window.location.origin);
                    if (invoiceDateField.value) url.searchParams.set('date', invoiceDateField.value);

                    fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                        .then(function(response) { return response.ok ? response.json() : null; })
                        .then(renderTenantBalance)
                        .catch(function() {});
                }

                // --- Comprobante de pago vinculado: se abre al marcar "ya pagó", precargado
                // desde las líneas/fecha/moneda/métodos de pago ya capturados en la factura,
                // pero totalmente editable. Se guarda en memoria (paymentReceiptDraft) hasta
                // que se confirme la factura — ver payment_receipt.* en
                // InvoiceController::validateInvoicePayload()/store().
                const prBackdrop = document.getElementById('payment-receipt-modal-backdrop');
                const prDateField = document.getElementById('pr-date');
                const prCurrencyField = document.getElementById('pr-currency');
                const prItemsBody = document.getElementById('pr-items-body');
                const prTotalDisplay = document.getElementById('pr-total-display');
                const prNotesField = document.getElementById('pr-notes');
                const prOtherPaymentDescription = document.getElementById('pr-payment-method-other-description');
                const paymentReceiptHidden = document.getElementById('payment-receipt-hidden');
                const paymentReceiptDefaultNote = document.getElementById('payment-receipt-default-note');
                const paymentReceiptSummaryNote = document.getElementById('payment-receipt-summary-note');
                const paymentReceiptSummaryText = document.getElementById('payment-receipt-summary-text');

                let paymentReceiptDraft = null;
                let prItems = [];

                function prComputeLineTotal(item) {
                    const price = parseFloat(item.unit_price) || 0;
                    return item.concept === 'discount' ? -Math.abs(price) : price;
                }

                function prRecalculateTotal() {
                    let total = 0;
                    prItemsBody.querySelectorAll('tr').forEach(function(row, idx) {
                        const lineTotal = prComputeLineTotal(prItems[idx]);
                        row.querySelector('.pr-item-total').textContent = lineTotal.toFixed(2);
                        total += lineTotal;
                    });
                    prTotalDisplay.textContent = total.toFixed(2);
                }

                function prRenderItems() {
                    prItemsBody.innerHTML = '';

                    prItems.forEach(function(item, idx) {
                        const row = document.createElement('tr');
                        const isCreditApplication = !!item.is_credit_application;

                        const conceptOptionsHtml = Object.keys(lineConceptOptions).map(function(value) {
                            return `<option value="${value}" ${value === item.concept ? 'selected' : ''}>${lineConceptOptions[value]}</option>`;
                        }).join('');

                        // Subconjunto de conceptos que sí se pueden saldar con crédito (ver
                        // CreditBalanceMovement::APPLIABLE_CONCEPTS) — "Servicio" y "Descuento"
                        // no aplican, por eso usa una lista aparte de lineConceptOptions.
                        const appliableConceptOptionsHtml = Object.keys(appliableConceptOptions).map(function(value) {
                            return `<option value="${value}" ${value === item.concept ? 'selected' : ''}>${appliableConceptOptions[value]}</option>`;
                        }).join('');

                        if (isCreditApplication) {
                            row.innerHTML = `
                                <td>
                                    <input type="text" class="form-control form-control-sm pr-item-description" value="${item.description || 'Aplicación de saldo a favor'}" required>
                                    <span class="badge bg-info text-dark mt-1">Saldo a favor</span>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm pr-item-concept" required>
                                        <option value="">Aplicar contra...</option>
                                        ${appliableConceptOptionsHtml}
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm pr-item-price" value="${item.unit_price ?? 0}" required></td>
                                <td class="pr-item-total">0.00</td>
                                <td class="text-muted small">No aplica</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger pr-remove-item-btn" style="border-radius:150px;height:30px;width:30px;">&times;</button></td>
                            `;
                        } else {
                            row.innerHTML = `
                                <td><input type="text" class="form-control form-control-sm pr-item-description" value="${item.description || ''}" required></td>
                                <td>
                                    <select class="form-select form-select-sm pr-item-concept" required>
                                        <option value="">Seleccione...</option>
                                        ${conceptOptionsHtml}
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm pr-item-price" value="${item.unit_price ?? 0}" required></td>
                                <td class="pr-item-total">0.00</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary pr-evidence-pick-btn">Seleccionar archivo</button>
                                    <input type="file" class="d-none pr-evidence-file-input" accept=".pdf,.png,.jpg,.jpeg,.webp">
                                    <div class="small mt-1">
                                        <span class="text-muted pr-evidence-empty-text ${item.evidence_file ? 'd-none' : ''}">Sin archivo adjunto</span>
                                        <span class="pr-evidence-filename-wrap ${item.evidence_file ? '' : 'd-none'}">
                                            <button type="button" class="btn btn-outline-danger p-0 pr-evidence-clear-btn" style="height:20px;width:20px;border-radius:150px;display:inline-flex;justify-content:center;align-items:center;" title="Quitar archivo">&times;</button>
                                            <span class="pr-evidence-filename-link">${item.evidence_file ? item.evidence_file.name : ''}</span>
                                        </span>
                                    </div>
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger pr-remove-item-btn" style="border-radius:150px;height:30px;width:30px;">&times;</button></td>
                            `;
                        }
                        prItemsBody.appendChild(row);
                        row.querySelector('.pr-item-concept').value = item.concept || '';

                        row.querySelector('.pr-item-description').addEventListener('input', function(event) {
                            prItems[idx].description = event.target.value;
                        });
                        row.querySelector('.pr-item-concept').addEventListener('change', function(event) {
                            prItems[idx].concept = event.target.value;
                            prRecalculateTotal();
                        });
                        row.querySelector('.pr-item-price').addEventListener('input', function(event) {
                            prItems[idx].unit_price = parseFloat(event.target.value) || 0;
                            prRecalculateTotal();
                        });

                        if (!isCreditApplication) {
                            const evidenceInput = row.querySelector('.pr-evidence-file-input');
                            row.querySelector('.pr-evidence-pick-btn').addEventListener('click', function() { evidenceInput.click(); });
                            evidenceInput.addEventListener('change', function() {
                                prItems[idx].evidence_file = evidenceInput.files[0] || null;
                                prRenderItems();
                            });
                            row.querySelector('.pr-evidence-clear-btn').addEventListener('click', function() {
                                prItems[idx].evidence_file = null;
                                prRenderItems();
                            });
                        }

                        row.querySelector('.pr-remove-item-btn').addEventListener('click', function() {
                            if (prItems.length <= 1) return;
                            prItems.splice(idx, 1);
                            prRenderItems();
                        });
                    });

                    prRecalculateTotal();
                }

                document.getElementById('pr-add-item-btn').addEventListener('click', function() {
                    prItems.push({ description: '', concept: '', is_credit_application: false, unit_price: 0, evidence_file: null });
                    prRenderItems();
                });

                document.getElementById('pr-add-credit-application-btn').addEventListener('click', function() {
                    prItems.push({ description: 'Aplicación de saldo a favor', concept: '', is_credit_application: true, unit_price: 0, evidence_file: null });
                    prRenderItems();
                });

                const prOtherPaymentCheckbox = document.getElementById('pr-pm-other');
                if (prOtherPaymentCheckbox) {
                    prOtherPaymentCheckbox.addEventListener('change', function() {
                        prOtherPaymentDescription.classList.toggle('d-none', !prOtherPaymentCheckbox.checked);
                    });
                }

                function paymentReceiptDefaults() {
                    return {
                        date: invoiceDateField.value,
                        currency: document.getElementById('currency').value,
                        payment_methods: Array.from(document.querySelectorAll('input[name="payment_methods[]"]:checked')).map(function(cb) { return cb.value; }),
                        payment_method_other_description: document.getElementById('payment-method-other-description').value,
                        notes: '',
                        // Precio unit. = total de línea con IVA incluido (lo efectivamente
                        // cobrado), igual que hacía antes el servidor al mirrorar en automático.
                        items: lineItems.map(function(item) {
                            const {lineTotal} = computeLineTotal(item);
                            return { description: item.description, concept: item.concept || '', is_credit_application: false, unit_price: lineTotal, evidence_file: null };
                        }),
                    };
                }

                function openPaymentReceiptModal() {
                    const base = paymentReceiptDraft || paymentReceiptDefaults();

                    prDateField.value = base.date;
                    prCurrencyField.value = base.currency;
                    prNotesField.value = base.notes || '';

                    document.querySelectorAll('#pr-payment-methods input[type=checkbox]').forEach(function(cb) {
                        cb.checked = base.payment_methods.indexOf(cb.value) !== -1;
                    });
                    prOtherPaymentDescription.value = base.payment_method_other_description || '';
                    prOtherPaymentDescription.classList.toggle('d-none', base.payment_methods.indexOf('other') === -1);

                    prItems = (base.items.length ? base.items : [{ description: '', concept: '', is_credit_application: false, unit_price: 0, evidence_file: null }])
                        .map(function(item) { return Object.assign({}, item); });
                    prRenderItems();

                    prBackdrop.style.display = 'flex';
                }

                function closePaymentReceiptModal() {
                    prBackdrop.style.display = 'none';
                }

                function cancelPaymentReceiptModal() {
                    // Si nunca se había confirmado un comprobante, marcar la casilla solo para
                    // abrir el modal y luego cerrarlo sin guardar no debe dejarla marcada.
                    if (!paymentReceiptDraft) {
                        createPaymentReceiptToggle.checked = false;
                    }
                    closePaymentReceiptModal();
                }

                function updatePaymentReceiptSummary() {
                    if (!paymentReceiptDraft) {
                        paymentReceiptSummaryNote.classList.add('d-none');
                        paymentReceiptDefaultNote.classList.remove('d-none');
                        return;
                    }

                    const total = paymentReceiptDraft.items.reduce(function(sum, item) { return sum + prComputeLineTotal(item); }, 0);
                    paymentReceiptSummaryText.textContent = `Comprobante configurado: ${paymentReceiptDraft.items.length} línea(s), ${paymentReceiptDraft.currency} ${total.toFixed(2)}.`;
                    paymentReceiptSummaryNote.classList.remove('d-none');
                    paymentReceiptDefaultNote.classList.add('d-none');
                }

                function renderPaymentReceiptHidden() {
                    paymentReceiptHidden.innerHTML = '';

                    if (!paymentReceiptDraft) return;

                    function addHidden(name, value) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value ?? '';
                        paymentReceiptHidden.appendChild(input);
                    }

                    addHidden('payment_receipt[date]', paymentReceiptDraft.date);
                    addHidden('payment_receipt[currency]', paymentReceiptDraft.currency);
                    addHidden('payment_receipt[notes]', paymentReceiptDraft.notes);
                    addHidden('payment_receipt[payment_method_other_description]', paymentReceiptDraft.payment_method_other_description);
                    paymentReceiptDraft.payment_methods.forEach(function(method) {
                        addHidden('payment_receipt[payment_methods][]', method);
                    });

                    paymentReceiptDraft.items.forEach(function(item, idx) {
                        addHidden(`payment_receipt[items][${idx}][description]`, item.description);
                        addHidden(`payment_receipt[items][${idx}][concept]`, item.concept);
                        addHidden(`payment_receipt[items][${idx}][is_credit_application]`, item.is_credit_application ? '1' : '0');
                        addHidden(`payment_receipt[items][${idx}][unit_price]`, item.unit_price);

                        const fileInput = document.createElement('input');
                        fileInput.type = 'file';
                        fileInput.name = `payment_receipt[items][${idx}][evidence_file]`;
                        fileInput.style.display = 'none';
                        if (item.evidence_file) {
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(item.evidence_file);
                            fileInput.files = dataTransfer.files;
                        }
                        paymentReceiptHidden.appendChild(fileInput);
                    });
                }

                document.getElementById('pr-modal-save').addEventListener('click', function() {
                    if (!prDateField.value) {
                        prDateField.focus();
                        return;
                    }

                    const checkedMethods = Array.from(document.querySelectorAll('#pr-payment-methods input[type=checkbox]:checked')).map(function(cb) { return cb.value; });
                    if (!checkedMethods.length) {
                        alert('Selecciona al menos un método de pago para el comprobante.');
                        return;
                    }

                    for (let i = 0; i < prItems.length; i++) {
                        if (!prItems[i].description || !prItems[i].description.trim() || !prItems[i].concept) {
                            alert('Cada línea del comprobante necesita descripción y concepto.');
                            return;
                        }
                    }

                    paymentReceiptDraft = {
                        date: prDateField.value,
                        currency: prCurrencyField.value,
                        payment_methods: checkedMethods,
                        payment_method_other_description: prOtherPaymentDescription.value,
                        notes: prNotesField.value,
                        items: prItems.map(function(item) { return Object.assign({}, item); }),
                    };

                    renderPaymentReceiptHidden();
                    updatePaymentReceiptSummary();
                    createPaymentReceiptToggle.checked = true;
                    closePaymentReceiptModal();
                });

                document.getElementById('pr-modal-cancel').addEventListener('click', cancelPaymentReceiptModal);
                document.getElementById('pr-modal-close').addEventListener('click', cancelPaymentReceiptModal);
                prBackdrop.addEventListener('click', function(event) {
                    if (event.target === prBackdrop) cancelPaymentReceiptModal();
                });

                document.getElementById('payment-receipt-edit-link').addEventListener('click', function(event) {
                    event.preventDefault();
                    openPaymentReceiptModal();
                });

                createPaymentReceiptToggle.addEventListener('change', function() {
                    if (createPaymentReceiptToggle.checked) {
                        openPaymentReceiptModal();
                    } else {
                        paymentReceiptDraft = null;
                        renderPaymentReceiptHidden();
                        updatePaymentReceiptSummary();
                    }
                });

                // Si el formulario vuelve por un error de validación en otro campo (ej. un
                // CABYS inválido) con la casilla ya marcada, se reconstruye el comprobante
                // desde old() en vez de dejar la casilla marcada sin datos detrás — los
                // archivos de evidencia no sobreviven ese viaje (limitación normal de HTML),
                // igual que ya pasa con la evidencia de las líneas de la factura.
                const oldPaymentReceipt = @json(old('payment_receipt', null));
                if (createPaymentReceiptToggle.checked && oldPaymentReceipt) {
                    paymentReceiptDraft = {
                        date: oldPaymentReceipt.date || '',
                        currency: oldPaymentReceipt.currency || 'CRC',
                        payment_methods: oldPaymentReceipt.payment_methods || [],
                        payment_method_other_description: oldPaymentReceipt.payment_method_other_description || '',
                        notes: oldPaymentReceipt.notes || '',
                        items: (oldPaymentReceipt.items || []).map(function(item) {
                            return {
                                description: item.description || '',
                                concept: item.concept || '',
                                is_credit_application: !!item.is_credit_application,
                                unit_price: parseFloat(item.unit_price) || 0,
                                evidence_file: null,
                            };
                        }),
                    };
                    renderPaymentReceiptHidden();
                    updatePaymentReceiptSummary();
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
                            suggestedUnitOfMeasure = data.suggested_unit_of_measure || 'Unid';
                            renderAll();

                            // La precarga del canon solo aplica a Factura Electrónica; una Nota
                            // de Crédito describe una corrección, no un cargo nuevo del contrato.
                            if (preloadCanon && documentTypeSelect.value !== '03' && lineItems.length === 0) {
                                lineItems.push({
                                    cabys_code: defaultCabys.code,
                                    commercial_code_type: null,
                                    commercial_code: null,
                                    description: data.suggested_description,
                                    concept: 'rent',
                                    quantity: 1,
                                    unit_of_measure: suggestedUnitOfMeasure,
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
                    refreshTenantBalance();
                });

                invoiceDateField.addEventListener('change', function() {
                    refreshBillingTerms(false);
                    refreshTenantBalance();
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

                // --- Confirmar antes de guardar y enviar a Hacienda: resumen + vista previa del XML ---
                const invoiceForm = document.getElementById('invoice-form');
                const saveInvoiceBtn = document.getElementById('save-invoice-btn');
                const confirmSendBackdrop = document.getElementById('confirm-send-modal-backdrop');
                const confirmSummary = document.getElementById('confirm-summary');
                const confirmXmlOutput = document.getElementById('confirm-xml-output');
                const confirmXmlStatus = document.getElementById('confirm-xml-status');
                const confirmXmlError = document.getElementById('confirm-xml-error');
                const confirmSendAccept = document.getElementById('confirm-send-accept');
                const invoicePreviewUrl = @json(route('admin.invoices.preview'));

                function buildConfirmSummary() {
                    const isCreditNoteNow = documentTypeSelect.value === '03';
                    const currency = document.getElementById('currency').value;

                    const rowsHtml = lineItems.map(function(item) {
                        const {lineTotal} = computeLineTotal(item);
                        return `
                            <tr>
                                <td>${item.description || ''}</td>
                                <td>${item.cabys_code || '-'}</td>
                                <td class="text-end">${(parseFloat(item.unit_price) || 0).toFixed(2)}</td>
                                <td class="text-end">${item.tax_rate || 0}%</td>
                                <td class="text-end">${lineTotal.toFixed(2)}</td>
                            </tr>
                        `;
                    }).join('');

                    const subtotal = subtotalDisplay.textContent;
                    const total = totalDisplay.textContent;
                    const paymentMethodLabels = Array.from(document.querySelectorAll('input[name="payment_methods[]"]:checked'))
                        .map(function(cb) { return cb.nextElementSibling ? cb.nextElementSibling.textContent : cb.value; })
                        .join(', ') || '-';

                    let referenceHtml = '';
                    if (isCreditNoteNow) {
                        const refOption = referenceInvoiceSelect ? referenceInvoiceSelect.selectedOptions[0] : null;
                        referenceHtml = `
                            <div class="row mb-2">
                                <div class="col-6"><strong class="d-block">Factura a corregir</strong><span>${refOption ? refOption.textContent.trim() : '-'}</span></div>
                                <div class="col-6"><strong class="d-block">Motivo</strong><span>${document.querySelector('[name="credit_note_reason_text"]').value || '-'}</span></div>
                            </div>
                        `;
                    }

                    confirmSummary.innerHTML = `
                        <div class="row mb-2">
                            <div class="col-6"><strong class="d-block">Tipo de documento</strong><span>${isCreditNoteNow ? 'Nota de crédito electrónica' : 'Factura electrónica'}</span></div>
                            <div class="col-6"><strong class="d-block">Fecha</strong><span>${invoiceDateField.value}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6"><strong class="d-block">Contrato</strong><span>${agreementSelect.selectedOptions[0] ? agreementSelect.selectedOptions[0].textContent.trim() : '-'}</span></div>
                            <div class="col-6">${receptorDisplay.innerHTML}</div>
                        </div>
                        ${referenceHtml}
                        <div class="table-responsive mt-3">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Descripción</th><th>CABYS</th><th class="text-end">Precio unit.</th><th class="text-end">IVA</th><th class="text-end">Total línea</th></tr></thead>
                                <tbody>${rowsHtml || '<tr><td colspan="5" class="text-center text-muted">Sin líneas</td></tr>'}</tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <div>Subtotal: ${currency} ${subtotal}</div>
                            <div class="fw-bold">Total: ${currency} ${total}</div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-6"><strong class="d-block">Condición de venta</strong><span>${document.getElementById('sale_condition').selectedOptions[0].textContent}</span></div>
                            <div class="col-6"><strong class="d-block">Métodos de pago</strong><span>${paymentMethodLabels}</span></div>
                        </div>
                    `;
                }

                function requestXmlPreview() {
                    confirmXmlStatus.textContent = 'Generando...';
                    confirmXmlStatus.className = 'badge bg-secondary';
                    confirmXmlOutput.textContent = '';
                    confirmXmlError.classList.add('d-none');
                    confirmSendAccept.disabled = true;

                    fetch(invoicePreviewUrl, {
                        method: 'POST',
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                        body: new FormData(invoiceForm),
                    })
                        .then(function(response) {
                            return response.json().then(function(data) { return {ok: response.ok, data: data}; });
                        })
                        .then(function(result) {
                            if (!result.ok) {
                                throw new Error(result.data.message || 'No se pudo generar la vista previa del XML.');
                            }

                            confirmXmlOutput.textContent = result.data.xml;
                            confirmXmlStatus.textContent = 'Listo';
                            confirmXmlStatus.className = 'badge bg-success';
                            confirmSendAccept.disabled = false;
                        })
                        .catch(function(error) {
                            confirmXmlStatus.textContent = 'Error';
                            confirmXmlStatus.className = 'badge bg-danger';
                            confirmXmlError.textContent = error.message;
                            confirmXmlError.classList.remove('d-none');
                        });
                }

                function closeConfirmModal() {
                    confirmSendBackdrop.style.display = 'none';
                }

                saveInvoiceBtn.addEventListener('click', function() {
                    if (!invoiceForm.reportValidity()) return;

                    buildConfirmSummary();
                    confirmSendBackdrop.style.display = 'flex';
                    requestXmlPreview();
                });

                document.getElementById('confirm-send-cancel').addEventListener('click', closeConfirmModal);
                document.getElementById('confirm-send-close').addEventListener('click', closeConfirmModal);

                confirmSendAccept.addEventListener('click', function() {
                    if (confirmSendAccept.disabled) return;
                    closeConfirmModal();
                    invoiceForm.submit();
                });

                confirmSendBackdrop.addEventListener('click', function(event) {
                    if (event.target === confirmSendBackdrop) closeConfirmModal();
                });

                updateReceptorDisplay();
                refreshTenantBalance();
                renderAll();
            });
        </script>
    </section>
@endsection
