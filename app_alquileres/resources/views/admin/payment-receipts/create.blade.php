@extends('layouts.admin')

@section('content')
    @php
        $isEdit = isset($receipt);
    @endphp

    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Comprobante de pago</h3>
                <p class="text-subtitle text-muted">Deja constancia de un pago del inquilino solo en el sistema (no se envía a Hacienda).</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.payment-receipts.index') }}">Payment-receipts</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $isEdit ? 'Edit' : 'Register' }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
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

                <form method="POST" action="{{ $isEdit ? route('admin.payment-receipts.update', $receipt->id) : route('admin.payment-receipts.store') }}" enctype="multipart/form-data" class="row g-3" id="receipt-form">
                    @csrf
                    @if ($isEdit)
                        @method('PATCH')
                    @endif
                    <input type="hidden" name="invoice_id" id="invoice_id_field" value="{{ old('invoice_id', $isEdit ? $receipt->invoice_id : '') }}">

                    <div class="col-md-4">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="date" id="receipt-date" class="form-control" value="{{ old('date', $isEdit ? $receipt->date->toDateString() : now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Número</label>
                        <input type="text" class="form-control" value="{{ $nextReceiptNumberPreview }}" disabled>
                        <small class="note-cyan">{{ $isEdit ? 'Consecutivo interno, asignado al crear el comprobante.' : 'Consecutivo interno, asignado automáticamente al guardar.' }}</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select" id="currency" required>
                            <option value="CRC" @selected(old('currency', $isEdit ? $receipt->currency : 'CRC') === 'CRC')>CRC</option>
                            <option value="USD" @selected(old('currency', $isEdit ? $receipt->currency : null) === 'USD')>USD</option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Contrato</label>
                        <select name="agreement_id" class="form-select" id="agreement_id" required>
                            <option value="">Seleccione un contrato</option>
                            @foreach ($agreements as $agreement)
                                <option
                                    value="{{ $agreement->id }}"
                                    data-roomer-name="{{ $agreement->roomer->legal_name ?? '' }}"
                                    data-roomer-id-number="{{ $agreement->roomer->id_number ?? '' }}"
                                    data-roomer-phone="{{ $agreement->roomer->phone ?? '' }}"
                                    data-roomer-email="{{ $agreement->roomer->user->email ?? '' }}"
                                    @selected(old('agreement_id', $isEdit ? $receipt->agreement_id : null) == $agreement->id)
                                >
                                    #{{ $agreement->id }} - {{ $agreement->property->name ?? 'Sin propiedad' }} / {{ $agreement->roomer->legal_name ?? 'Sin arrendatario' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="note-cyan">Al elegir el contrato se precarga la línea con el monto vigente.</small>
                    </div>

                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100" id="receptor-display">
                            <strong class="d-block mb-1">Inquilino</strong>
                            <p class="text-muted small mb-0">Selecciona un contrato.</p>
                        </div>
                    </div>

                    <div class="col-12" id="unpaid-invoice-wrap" style="display:none;">
                        <label class="form-label">Vincular a factura electrónica (opcional)</label>
                        <select class="form-select" id="unpaid-invoice-select">
                            <option value="">Ninguna (comprobante independiente)</option>
                        </select>
                        <small class="note-cyan">Solo se listan facturas electrónicas ya aceptadas por Hacienda de este contrato con saldo pendiente (una factura puede pagarse por tractos con varios comprobantes). Al elegir una se reemplazan las líneas actuales con el monto que falta por pagar.</small>
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
                    </div>

                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="receipt-items-table">
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th style="width:180px;">Concepto</th>
                                        <th style="width:90px;">Retorno</th>
                                        <th style="width:160px;">Precio unit.</th>
                                        <th style="width:140px;">Total línea</th>
                                        <th style="width:200px;">Evidencia</th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="receipt-items-body"></tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Total</td>
                                        <td id="receipt-total-display">0.00</td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-receipt-item-btn">+ Añadir línea</button>
                        <button type="button" class="btn btn-sm btn-outline-info" id="add-credit-application-btn">Aplicar saldo a favor</button>
                    </div>

                    <div class="col-12 my-3 mt-5">
                        <label class="form-label d-block mb-0">Métodos de pago</label>
                        <small class="note-cyan d-block mb-3">Puedes marcar más de uno si el pago se dividió (ej. parte transferencia, parte efectivo).</small>
                        @php
                            $selectedPaymentMethods = old('payment_methods', $isEdit ? ($receipt->payment_methods ?? ['cash']) : ['cash']);
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
                        <input type="text" name="payment_method_other_description" id="payment-method-other-description" class="form-control mt-2 d-none" placeholder="Describe el método de pago" value="{{ old('payment_method_other_description', $isEdit ? $receipt->payment_method_other_description : null) }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes', $isEdit ? $receipt->notes : null) }}">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.payment-receipts.index') }}" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar cambios' : 'Guardar comprobante' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const billingTermsBaseUrl = @json(route('admin.agreements.billing-terms', ['agreementId' => '__ID__']));
            const tenantBalanceBaseUrl = @json(route('admin.agreements.tenant-balance', ['agreementId' => '__ID__']));
            const unpaidInvoicesBaseUrl = @json(route('admin.agreements.unpaid-invoices', ['agreementId' => '__ID__']));
            const conceptOptions = @json($conceptOptions);
            const appliableConceptOptions = @json($appliableConceptOptions);
            const excludeReceiptId = @json($isEdit ? $receipt->id : null);
            const agreementSelect = document.getElementById('agreement_id');
            const dateInput = document.getElementById('receipt-date');
            const receptorDisplay = document.getElementById('receptor-display');
            const itemsBody = document.getElementById('receipt-items-body');
            const totalDisplay = document.getElementById('receipt-total-display');
            const invoiceIdField = document.getElementById('invoice_id_field');
            const unpaidInvoiceWrap = document.getElementById('unpaid-invoice-wrap');
            const unpaidInvoiceSelect = document.getElementById('unpaid-invoice-select');

            @php
                $prefillItems = old('items', $isEdit
                    ? $receipt->items->map(fn ($item) => [
                        'description' => $item->description,
                        'concept' => $item->concept,
                        'is_return' => (bool) $item->is_return,
                        'is_credit_application' => (bool) $item->is_credit_application,
                        'quantity' => $item->quantity,
                        // El signo (discount/is_return) lo transmiten el concepto y el
                        // checkbox, no el número: se muestra siempre en positivo.
                        'unit_price' => abs((float) $item->unit_price),
                        'existing_file_payment_id' => $item->file_payment_id,
                        'existing_file_name' => $item->filePayment->name_file ?? null,
                        'existing_file_url' => $item->filePayment->url ?? null,
                    ])->values()->all()
                    : []);
            @endphp
            let items = @json($prefillItems);

            function recalculateTotal() {
                let total = 0;
                itemsBody.querySelectorAll('.receipt-item-row').forEach(function(row) {
                    const qty = parseFloat(row.querySelector('.item-quantity').value) || 0;
                    const price = parseFloat(row.querySelector('.item-price').value) || 0;
                    const concept = row.querySelector('.item-concept').value;
                    const isReturnInput = row.querySelector('.item-is-return');
                    const isReturn = isReturnInput ? isReturnInput.checked : false;
                    let lineTotal = qty * price;
                    if (concept === 'discount' || isReturn) lineTotal = -Math.abs(lineTotal);
                    row.querySelector('.item-line-total').textContent = lineTotal.toFixed(2);
                    total += lineTotal;
                });
                totalDisplay.textContent = total.toFixed(2);
            }

            function reindexRows() {
                itemsBody.querySelectorAll('.receipt-item-row').forEach(function(row, idx) {
                    row.querySelectorAll('[data-field]').forEach(function(input) {
                        input.name = `items[${idx}][${input.dataset.field}]`;
                    });
                });
            }

            function addRow(item, isCreditApplication) {
                item = item || {};
                isCreditApplication = isCreditApplication || !!item.is_credit_application;

                const row = document.createElement('tr');
                row.className = 'receipt-item-row' + (isCreditApplication ? ' credit-application-row' : '');

                const conceptOptionsHtml = Object.keys(conceptOptions).map(function(value) {
                    return `<option value="${value}" ${value === item.concept ? 'selected' : ''}>${conceptOptions[value]}</option>`;
                }).join('');

                // Subconjunto: "Servicio" y "Descuento" no son saldos que se puedan saldar
                // con crédito, así que no tiene sentido ofrecerlos aquí (ver
                // CreditBalanceMovement::APPLIABLE_CONCEPTS, misma lista que usa la pantalla
                // dedicada de Aplicación de saldo a favor y la de Factura electrónica).
                const appliableConceptOptionsHtml = Object.keys(appliableConceptOptions).map(function(value) {
                    return `<option value="${value}" ${value === item.concept ? 'selected' : ''}>${appliableConceptOptions[value]}</option>`;
                }).join('');

                if (isCreditApplication) {
                    row.innerHTML = `
                        <td>
                            <input type="text" class="form-control form-control-sm" data-field="description" value="${item.description ?? 'Aplicación de saldo a favor'}" required>
                            <input type="hidden" class="item-quantity" data-field="quantity" value="1">
                            <input type="hidden" data-field="is_credit_application" value="1">
                            <span class="badge bg-info text-dark mt-1">Saldo a favor</span>
                        </td>
                        <td>
                            <select class="form-select form-select-sm item-concept" data-field="concept" required>
                                <option value="">Aplicar contra...</option>
                                ${appliableConceptOptionsHtml}
                            </select>
                        </td>
                        <td class="text-center text-muted small">&mdash;</td>
                        <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm item-price" data-field="unit_price" value="${item.unit_price ?? 0}" required></td>
                        <td class="item-line-total">0.00</td>
                        <td class="text-muted small">No aplica</td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-receipt-item-btn" style="border-radius:150px;height:30px;width:30px;">&times;</button></td>
                    `;
                } else {
                    row.innerHTML = `
                        <td>
                            <input type="text" class="form-control form-control-sm" data-field="description" value="${item.description ?? ''}" required>
                            <input type="hidden" data-field="tax_condition" value="exento">
                            <input type="hidden" class="item-quantity" data-field="quantity" value="${item.quantity ?? 1}">
                            <input type="hidden" data-field="is_credit_application" value="0">
                        </td>
                        <td>
                            <select class="form-select form-select-sm item-concept" data-field="concept" required>
                                <option value="">Seleccione...</option>
                                ${conceptOptionsHtml}
                            </select>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input item-is-return" data-field="is_return" value="1" ${item.is_return ? 'checked' : ''}>
                        </td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm item-price" data-field="unit_price" value="${item.unit_price ?? 0}" required></td>
                        <td class="item-line-total">0.00</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary evidence-pick-btn">Seleccionar archivo</button>
                            <input type="file" class="d-none evidence-file-input" data-field="evidence_file" accept=".pdf,.png,.jpg,.jpeg,.webp">
                            <input type="hidden" data-field="existing_file_payment_id" value="${item.existing_file_payment_id || ''}">
                            <input type="hidden" class="evidence-remove-flag" data-field="remove_evidence_file" value="0">
                            <div class="small mt-1">
                                <span class="text-muted evidence-empty-text">Sin archivo adjunto</span>
                                <span class="evidence-filename-wrap d-none">
                                    <button type="button" class="btn btn-outline-danger p-0 evidence-clear-btn" style="height:20px;width:20px;border-radius:150px;display:inline-flex;justify-content:center;align-items:center;" title="Quitar archivo">&times;</button>
                                    <a href="#" target="_blank" rel="noopener" class="evidence-filename-link"></a>
                                </span>
                            </div>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-receipt-item-btn" style="border-radius:150px;height:30px;width:30px;">&times;</button></td>
                    `;
                }

                itemsBody.appendChild(row);

                row.dataset.existingFileName = item.existing_file_name || '';
                row.dataset.existingFileUrl = item.existing_file_url || '';

                row.querySelectorAll('.item-price, .item-concept, .item-is-return').forEach(function(input) {
                    input.addEventListener('input', recalculateTotal);
                    input.addEventListener('change', recalculateTotal);
                });
                row.querySelector('.remove-receipt-item-btn').addEventListener('click', function() {
                    if (itemsBody.querySelectorAll('.receipt-item-row').length <= 1) return;
                    row.remove();
                    reindexRows();
                    recalculateTotal();
                });

                if (!isCreditApplication) {
                    const evidenceFileInput = row.querySelector('.evidence-file-input');
                    const evidenceRemoveFlag = row.querySelector('.evidence-remove-flag');

                    row.querySelector('.evidence-pick-btn').addEventListener('click', function() {
                        evidenceFileInput.click();
                    });

                    evidenceFileInput.addEventListener('change', function() {
                        if (evidenceFileInput.files && evidenceFileInput.files[0]) {
                            evidenceRemoveFlag.value = '0';
                        }
                        updateEvidenceDisplay(row);
                    });

                    row.querySelector('.evidence-clear-btn').addEventListener('click', function() {
                        evidenceFileInput.value = '';
                        if (row.dataset.existingFileName) {
                            evidenceRemoveFlag.value = '1';
                        }
                        updateEvidenceDisplay(row);
                    });

                    updateEvidenceDisplay(row);
                }

                reindexRows();
                recalculateTotal();
            }

            function updateEvidenceDisplay(row) {
                const fileInput = row.querySelector('.evidence-file-input');
                const emptyText = row.querySelector('.evidence-empty-text');
                const filenameWrap = row.querySelector('.evidence-filename-wrap');
                const filenameLink = row.querySelector('.evidence-filename-link');
                const removeFlag = row.querySelector('.evidence-remove-flag');
                const newFile = fileInput.files && fileInput.files[0];

                if (newFile) {
                    filenameLink.textContent = newFile.name;
                    filenameLink.removeAttribute('href');
                    filenameWrap.classList.remove('d-none');
                    emptyText.classList.add('d-none');
                } else if (row.dataset.existingFileName && removeFlag.value !== '1') {
                    filenameLink.textContent = row.dataset.existingFileName;
                    filenameLink.href = row.dataset.existingFileUrl || '#';
                    filenameWrap.classList.remove('d-none');
                    emptyText.classList.add('d-none');
                } else {
                    filenameWrap.classList.add('d-none');
                    emptyText.classList.remove('d-none');
                }
            }

            document.getElementById('add-receipt-item-btn').addEventListener('click', function() {
                addRow(null, false);
            });

            document.getElementById('add-credit-application-btn').addEventListener('click', function() {
                addRow(null, true);
            });

            if (items.length) {
                items.forEach(function(item) { addRow(item, item.is_credit_application); });
            } else {
                addRow();
            }

            function updateReceptorDisplay() {
                const option = agreementSelect.selectedOptions[0];

                if (!option || !option.value) {
                    receptorDisplay.innerHTML = '<strong class="d-block mb-1">Inquilino</strong><p class="text-muted small mb-0">Selecciona un contrato.</p>';
                    return;
                }

                receptorDisplay.innerHTML = `
                    <strong class="d-block mb-1">Inquilino</strong>
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
                if (dateInput.value) url.searchParams.set('date', dateInput.value);
                if (excludeReceiptId) url.searchParams.set('exclude_receipt_id', excludeReceiptId);

                fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                    .then(function(response) { return response.ok ? response.json() : null; })
                    .then(renderTenantBalance)
                    .catch(function() {});
            }

            function refreshUnpaidInvoices(preselectId) {
                const agreementId = agreementSelect.value;

                if (!agreementId) {
                    unpaidInvoiceWrap.style.display = 'none';
                    return;
                }

                const url = new URL(unpaidInvoicesBaseUrl.replace('__ID__', agreementId), window.location.origin);
                if (excludeReceiptId) url.searchParams.set('exclude_receipt_id', excludeReceiptId);

                fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                    .then(function(response) { return response.ok ? response.json() : null; })
                    .then(function(data) {
                        if (!data) return;

                        unpaidInvoiceSelect.innerHTML = '<option value="">Ninguna (comprobante independiente)</option>';

                        (data.invoices || []).forEach(function(invoice) {
                            const option = document.createElement('option');
                            option.value = invoice.id;
                            const paidNote = invoice.paid > 0 ? ` (ya pagado ${invoice.currency} ${invoice.paid.toFixed(2)}, pendiente ${invoice.currency} ${invoice.remaining.toFixed(2)})` : '';
                            option.textContent = `${invoice.invoice_number} — ${invoice.date} — total ${invoice.currency} ${invoice.total.toFixed(2)}${paidNote}`;
                            option.dataset.description = invoice.description || '';
                            option.dataset.remaining = invoice.remaining;
                            option.dataset.currency = invoice.currency;
                            unpaidInvoiceSelect.appendChild(option);
                        });

                        unpaidInvoiceWrap.style.display = '';

                        // Selecciona sin disparar 'change' (no queremos borrar las líneas ya
                        // precargadas al abrir la página, solo reflejar el vínculo existente).
                        if (preselectId) {
                            unpaidInvoiceSelect.value = String(preselectId);
                        }
                    })
                    .catch(function() {});
            }

            unpaidInvoiceSelect.addEventListener('change', function() {
                const option = unpaidInvoiceSelect.selectedOptions[0];
                invoiceIdField.value = unpaidInvoiceSelect.value;

                if (!unpaidInvoiceSelect.value || !option) {
                    return;
                }

                itemsBody.querySelectorAll('.receipt-item-row').forEach(function(row) { row.remove(); });

                addRow({
                    description: option.dataset.description || `Pago de factura ${option.textContent}`,
                    concept: 'rent',
                    is_return: false,
                    // Precarga lo que falta por pagar, no el total de la factura: si ya
                    // tenía otros comprobantes vinculados (pago por tractos), esta es la
                    // línea que cierra el saldo pendiente, no vuelve a cobrar todo.
                    unit_price: parseFloat(option.dataset.remaining) || 0,
                }, false);
            });

            agreementSelect.addEventListener('change', function() {
                updateReceptorDisplay();
                refreshTenantBalance();
                refreshUnpaidInvoices(null);

                const agreementId = agreementSelect.value;
                if (!agreementId) return;

                fetch(billingTermsBaseUrl.replace('__ID__', agreementId), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                })
                    .then(function(response) { return response.ok ? response.json() : null; })
                    .then(function(data) {
                        if (!data) return;
                        document.getElementById('currency').value = data.currency;

                        const firstRow = itemsBody.querySelector('.receipt-item-row:not(.credit-application-row)');
                        if (firstRow) {
                            firstRow.querySelector('[data-field="description"]').value = data.suggested_description;
                            firstRow.querySelector('.item-price').value = data.amount;
                            firstRow.querySelector('.item-concept').value = 'rent';
                            recalculateTotal();
                        }
                    })
                    .catch(function() {});
            });

            dateInput.addEventListener('change', refreshTenantBalance);

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

            updateReceptorDisplay();
            refreshTenantBalance();
            if (agreementSelect.value) {
                refreshUnpaidInvoices(invoiceIdField.value || null);
            }
        });
    </script>
@endsection
