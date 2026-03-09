@extends('layouts.admin')

@section('content')
    <section class="section">
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Nueva factura</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">
                    Emisión de comprobantes para arrendamiento conforme a práctica de facturación electrónica en Costa Rica.
                </p>
                <p class="small text-muted mb-4">
                    Esta vista prioriza los datos usuales de Hacienda para FE de arrendadores (identificación, actividad económica, condición de venta,
                    medio de pago y metadatos electrónicos) sin alterar la lógica actual del sistema.
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

                <div class="alert alert-info mb-4" role="alert">
                    <strong>Referencia Hacienda (arrendadores):</strong> para factura electrónica asegúrate de registrar correctamente tipo de documento,
                    situación, actividad económica y datos de venta/pago. La clave numérica y XML final se gestionan en el flujo electrónico.
                </div>

                <form method="POST" action="{{ route('admin.invoices.store') }}" class="row g-3" id="invoice-form">
                    @csrf

                    <div class="col-12">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">1) Encabezado del comprobante</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo de factura</label>
                        <select name="invoice_type" class="form-select" id="invoice_type" required>
                            <option value="electronic" @selected(old('invoice_type', 'electronic') === 'electronic')>Electrónica</option>
                            <option value="simple" @selected(old('invoice_type') === 'simple')>Simple</option>
                        </select>
                        <small class="text-muted">Usa "Electrónica" para envío y trazabilidad con Hacienda.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Número factura</label>
                        <input type="text" name="invoice_number" class="form-control" value="{{ old('invoice_number') }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha de emisión</label>
                        <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Contrato</label>
                        <select name="agreement_id" class="form-select" required>
                            <option value="">Seleccione un contrato</option>
                            @foreach ($agreements as $agreement)
                                <option value="{{ $agreement->id }}" @selected(old('agreement_id') == $agreement->id)>
                                    #{{ $agreement->id }} - {{ $agreement->property->name ?? 'Sin propiedad' }} / {{ $agreement->roomer->legal_name ?? 'Sin arrendatario' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control" rows="2" required placeholder="Ej: Canon de arrendamiento mensual, periodo enero 2026">{{ old('description') }}</textarea>
                    </div>

                    <div class="col-12 mt-3">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">2) Montos y condiciones comerciales</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select" required>
                            <option value="CRC" @selected(old('currency', 'CRC') === 'CRC')>CRC</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Subtotal</label>
                        <input type="number" step="0.01" min="0" name="subtotal" class="form-control" value="{{ old('subtotal') }}" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">IVA %</label>
                        <input type="number" step="0.01" min="0" max="100" name="tax_percent" class="form-control" value="{{ old('tax_percent', '13') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Desc. %</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount_percent" class="form-control" value="{{ old('discount_percent', '0') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Mora ₡/$</label>
                        <input type="number" step="0.01" min="0" name="late_fee_total" class="form-control" value="{{ old('late_fee_total', '0') }}">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Condición venta</label>
                        <select name="sale_condition" class="form-select" id="sale_condition" required>
                            @foreach ($saleConditionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('sale_condition', 'cash') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Medio de pago</label>
                        <select name="payment_method" class="form-select" required>
                            @foreach ($paymentMethodOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'transfer') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-none" id="credit-term-wrapper">
                        <label class="form-label">Plazo crédito (referencia)</label>
                        <input type="number" min="1" class="form-control" placeholder="Días" aria-label="Plazo crédito referencial">
                        <small class="text-muted">Campo informativo de apoyo visual (sin persistencia).</small>
                    </div>

                    <div class="col-12 mt-3 electronic-only">
                        <h6 class="text-uppercase text-muted fw-bold mb-1">3) Datos electrónicos (Hacienda)</h6>
                        <hr class="mt-1 mb-2">
                    </div>

                    <div class="col-md-3 electronic-only">
                        <label class="form-label">Tipo doc FE</label>
                        <input type="text" name="document_type" class="form-control" value="{{ old('document_type', '01') }}" maxlength="2">
                        <small class="text-muted">Ej. 01 para FE (según catálogo operativo).</small>
                    </div>

                    <div class="col-md-3 electronic-only">
                        <label class="form-label">Situación</label>
                        <input type="text" name="situation" class="form-control" value="{{ old('situation', '1') }}" maxlength="1">
                        <small class="text-muted">Normal, contingencia o sin internet según escenario.</small>
                    </div>

                    <div class="col-md-3 electronic-only">
                        <label class="form-label">Código actividad</label>
                        <input type="text" name="activity_code" class="form-control" value="{{ old('activity_code') }}" maxlength="6" placeholder="Ej: 682001">
                    </div>

                    <div class="col-md-3 electronic-only">
                        <label class="form-label">Actividad económica</label>
                        <input type="text" name="economic_activity" class="form-control" value="{{ old('economic_activity') }}" placeholder="Arrendamiento de bienes inmuebles">
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
                                        <div class="d-grid gap-1">
                                            <form method="POST" action="{{ route('admin.invoices.electronic.send', $invoice->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Enviar</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.invoices.electronic.retry', $invoice->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Reintentar</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.invoices.electronic.check-status', $invoice->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info">Consultar estado</button>
                                            </form>
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

        <div class="card mt-4">
            <div class="card-header"><h5>Opciones API recomendadas</h5></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach ($providers as $provider)
                        <li><strong>{{ $provider['name'] }}</strong>: {{ $provider['type'] }} ({{ $provider['price'] }}). {{ $provider['notes'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const invoiceTypeInput = document.getElementById('invoice_type');
                const saleConditionInput = document.getElementById('sale_condition');
                const creditTermWrapper = document.getElementById('credit-term-wrapper');
                const electronicOnlyFields = document.querySelectorAll('.electronic-only');

                if (!invoiceTypeInput) {
                    return;
                }

                const toggleElectronicFields = function() {
                    const showElectronicFields = invoiceTypeInput.value === 'electronic';

                    electronicOnlyFields.forEach(function(fieldWrapper) {
                        fieldWrapper.style.display = showElectronicFields ? '' : 'none';

                        const input = fieldWrapper.querySelector('input, select, textarea');

                        if (input && ['document_type', 'situation'].includes(input.name)) {
                            input.required = showElectronicFields;
                        }
                    });
                };

                const toggleCreditTerm = function() {
                    if (!saleConditionInput || !creditTermWrapper) {
                        return;
                    }

                    const creditValues = ['credit', '02'];
                    creditTermWrapper.classList.toggle('d-none', !creditValues.includes(String(saleConditionInput.value)));
                };

                invoiceTypeInput.addEventListener('change', toggleElectronicFields);
                saleConditionInput?.addEventListener('change', toggleCreditTerm);
                toggleElectronicFields();
                toggleCreditTerm();
            });
        </script>
    </section>
@endsection
