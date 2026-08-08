@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Aplicación de saldo a favor</h3>
                <p class="text-subtitle text-muted">Otorga saldo a favor al inquilino o aplica el que ya tenga disponible contra un concepto pendiente.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.credit-balance.index') }}">Credit-balance</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Register</li>
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

                <div class="btn-group mb-4" role="group">
                    <input type="radio" class="btn-check" name="movement-mode" id="mode-apply" autocomplete="off" checked>
                    <label class="btn btn-outline-primary" for="mode-apply">Aplicar saldo existente</label>

                    <input type="radio" class="btn-check" name="movement-mode" id="mode-grant" autocomplete="off">
                    <label class="btn btn-outline-primary" for="mode-grant">Otorgar saldo manual</label>
                </div>

                <div class="col-md-8 mb-4">
                    <label class="form-label">Contrato</label>
                    <select class="form-select" id="agreement_id" required>
                        <option value="">Seleccione un contrato</option>
                        @foreach ($agreements as $agreement)
                            <option value="{{ $agreement->id }}" data-currency="{{ $agreement->currency }}">
                                #{{ $agreement->id }} - {{ $agreement->property->name ?? 'Sin propiedad' }} / {{ $agreement->roomer->legal_name ?? 'Sin arrendatario' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="border rounded p-3 mt-2 d-none" id="credit-balance-panel">
                        <strong class="d-block mb-1">Saldo a favor disponible</strong>
                        <span class="fw-bold fs-5" id="credit-balance-available">0.00</span> <span id="credit-balance-currency"></span>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.credit-balance.apply') }}" class="row g-3" id="apply-form">
                    @csrf
                    <input type="hidden" name="agreement_id" class="sync-agreement-id" required>

                    <div class="col-md-4">
                        <label class="form-label">Monto a aplicar</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select sync-currency" required>
                            <option value="CRC" @selected(old('currency', 'CRC') === 'CRC')>CRC</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Aplicar contra</label>
                        <select name="applied_to_concept" class="form-select" required>
                            <option value="">Seleccione...</option>
                            @foreach ($appliableConcepts as $value => $label)
                                <option value="{{ $value }}" @selected(old('applied_to_concept') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('admin.credit-balance.index') }}" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Aplicar saldo a favor</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.credit-balance.grant') }}" class="row g-3 d-none" id="grant-form">
                    @csrf
                    <input type="hidden" name="agreement_id" class="sync-agreement-id" required>

                    <div class="col-md-4">
                        <label class="form-label">Monto a otorgar</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select sync-currency" required>
                            <option value="CRC" @selected(old('currency', 'CRC') === 'CRC')>CRC</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Motivo (obligatorio)</label>
                        <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" required maxlength="500" placeholder="Ej. corrección de sobrepago no detectado, cortesía comercial, ajuste por reclamo...">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('admin.credit-balance.index') }}" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Otorgar saldo a favor</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tenantBalanceBaseUrl = @json(route('admin.agreements.tenant-balance', ['agreementId' => '__ID__']));
            const agreementSelect = document.getElementById('agreement_id');
            const applyForm = document.getElementById('apply-form');
            const grantForm = document.getElementById('grant-form');
            const modeApply = document.getElementById('mode-apply');
            const modeGrant = document.getElementById('mode-grant');
            const balancePanel = document.getElementById('credit-balance-panel');
            const balanceAvailable = document.getElementById('credit-balance-available');
            const balanceCurrency = document.getElementById('credit-balance-currency');

            function toggleMode() {
                applyForm.classList.toggle('d-none', !modeApply.checked);
                grantForm.classList.toggle('d-none', !modeGrant.checked);
            }

            modeApply.addEventListener('change', toggleMode);
            modeGrant.addEventListener('change', toggleMode);

            agreementSelect.addEventListener('change', function() {
                const agreementId = agreementSelect.value;
                const option = agreementSelect.selectedOptions[0];

                document.querySelectorAll('.sync-agreement-id').forEach(function(input) {
                    input.value = agreementId;
                });

                if (option && option.dataset.currency) {
                    document.querySelectorAll('.sync-currency').forEach(function(select) {
                        select.value = option.dataset.currency;
                    });
                }

                if (!agreementId) {
                    balancePanel.classList.add('d-none');
                    return;
                }

                fetch(tenantBalanceBaseUrl.replace('__ID__', agreementId), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                })
                    .then(function(response) { return response.ok ? response.json() : null; })
                    .then(function(data) {
                        if (!data) return;
                        balanceAvailable.textContent = data.credit_balance.available.toFixed(2);
                        balanceCurrency.textContent = data.currency || '';
                        balancePanel.classList.remove('d-none');
                    })
                    .catch(function() {});
            });
        });
    </script>
@endsection
