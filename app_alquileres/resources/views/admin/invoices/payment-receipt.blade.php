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
                <h3>Comprobante de pago</h3>
                <p class="text-subtitle text-muted">Deja constancia de un pago del inquilino solo en el sistema (no se envía a Hacienda).</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.invoices.index') }}">Invoices</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Payment receipt</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">Nuevo comprobante de pago</h4>
                <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Volver a facturas registradas</a>
            </div>
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

                <form method="POST" action="{{ route('admin.invoices.store') }}" class="row g-3" id="receipt-form">
                    @csrf
                    <input type="hidden" name="invoice_type" value="simple">

                    <div class="col-md-4">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="date" id="receipt-date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Número</label>
                        <input type="text" class="form-control" value="{{ $nextInvoiceNumberPreview }}" disabled>
                        <small class="note-cyan">Consecutivo interno, asignado automáticamente al guardar.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select" id="currency" required>
                            <option value="CRC" @selected(old('currency', 'CRC') === 'CRC')>CRC</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
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
                                    @selected(old('agreement_id') == $agreement->id)
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

                    <div class="col-12 mt-4">
                        <div class="border rounded p-3" id="tenant-balance-panel">
                            <strong class="d-block mb-2">Saldo pendiente del inquilino</strong>
                            <p class="text-muted small mb-0" id="tenant-balance-placeholder">Selecciona un contrato para ver el saldo pendiente.</p>
                            <div class="row row-cols-2 row-cols-md-4 g-2 d-none" id="tenant-balance-figures">
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
                                        <th style="width:160px;">Precio unit.</th>
                                        <th style="width:140px;">Total línea</th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="receipt-items-body"></tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total</td>
                                        <td id="receipt-total-display">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-receipt-item-btn">+ Añadir línea</button>
                    </div>

                    <input type="hidden" name="sale_condition" value="cash">

                    <div class="col-12 my-3 mt-5">
                        <label class="form-label d-block mb-0">Métodos de pago</label>
                        <small class="note-cyan d-block mb-3">Puedes marcar más de uno si el pago se dividió (ej. parte transferencia, parte efectivo).</small>
                        @php
                            $selectedPaymentMethods = old('payment_methods', ['cash']);
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

                    <div class="col-12">
                        <label class="form-label">Notas (opcional)</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Guardar comprobante</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const billingTermsBaseUrl = @json(route('admin.agreements.billing-terms', ['agreementId' => '__ID__']));
            const tenantBalanceBaseUrl = @json(route('admin.agreements.tenant-balance', ['agreementId' => '__ID__']));
            const conceptOptions = @json($conceptOptions);
            const agreementSelect = document.getElementById('agreement_id');
            const dateInput = document.getElementById('receipt-date');
            const receptorDisplay = document.getElementById('receptor-display');
            const itemsBody = document.getElementById('receipt-items-body');
            const totalDisplay = document.getElementById('receipt-total-display');

            let items = @json(old('items', []));

            function recalculateTotal() {
                let total = 0;
                itemsBody.querySelectorAll('.receipt-item-row').forEach(function(row) {
                    const qty = parseFloat(row.querySelector('.item-quantity').value) || 0;
                    const price = parseFloat(row.querySelector('.item-price').value) || 0;
                    const concept = row.querySelector('.item-concept').value;
                    let lineTotal = qty * price;
                    if (concept === 'discount') lineTotal = -Math.abs(lineTotal);
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

            function addRow(description, quantity, unitPrice, concept) {
                const row = document.createElement('tr');
                row.className = 'receipt-item-row';

                const conceptOptionsHtml = Object.keys(conceptOptions).map(function(value) {
                    return `<option value="${value}" ${value === concept ? 'selected' : ''}>${conceptOptions[value]}</option>`;
                }).join('');

                row.innerHTML = `
                    <td>
                        <input type="text" class="form-control form-control-sm" data-field="description" value="${description ?? ''}" required>
                        <input type="hidden" data-field="tax_condition" value="exento">
                        <input type="hidden" class="item-quantity" data-field="quantity" value="${quantity ?? 1}">
                    </td>
                    <td>
                        <select class="form-select form-select-sm item-concept" data-field="concept" required>
                            <option value="">Seleccione...</option>
                            ${conceptOptionsHtml}
                        </select>
                    </td>
                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm item-price" data-field="unit_price" value="${unitPrice ?? 0}" required></td>
                    <td class="item-line-total">0.00</td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-receipt-item-btn">&times;</button></td>
                `;
                itemsBody.appendChild(row);

                row.querySelectorAll('.item-price, .item-concept').forEach(function(input) {
                    input.addEventListener('input', recalculateTotal);
                    input.addEventListener('change', recalculateTotal);
                });
                row.querySelector('.remove-receipt-item-btn').addEventListener('click', function() {
                    if (itemsBody.querySelectorAll('.receipt-item-row').length <= 1) return;
                    row.remove();
                    reindexRows();
                    recalculateTotal();
                });

                reindexRows();
                recalculateTotal();
            }

            document.getElementById('add-receipt-item-btn').addEventListener('click', function() {
                addRow('', 1, 0, '');
            });

            if (items.length) {
                items.forEach(function(item) { addRow(item.description, item.quantity, item.unit_price, item.concept); });
            } else {
                addRow('', 1, 0, '');
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

                fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                    .then(function(response) { return response.ok ? response.json() : null; })
                    .then(renderTenantBalance)
                    .catch(function() {});
            }

            agreementSelect.addEventListener('change', function() {
                updateReceptorDisplay();
                refreshTenantBalance();

                const agreementId = agreementSelect.value;
                if (!agreementId) return;

                fetch(billingTermsBaseUrl.replace('__ID__', agreementId), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                })
                    .then(function(response) { return response.ok ? response.json() : null; })
                    .then(function(data) {
                        if (!data) return;
                        document.getElementById('currency').value = data.currency;

                        const firstRow = itemsBody.querySelector('.receipt-item-row');
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
        });
    </script>
@endsection
