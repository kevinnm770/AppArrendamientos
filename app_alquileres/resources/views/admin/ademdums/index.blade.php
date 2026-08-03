@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Adendums del contrato {{ $agreement->contract_number }}</h3>
                <p class="text-subtitle text-muted">Crear un adendum virtual vinculado al contrato {{ $agreement->contract_number }}</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.agreements.index') }}">Agreements</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Adendum</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <section class="section">
            <div class="alert alert-light-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if (session('success'))
        <div class="alert alert-light-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-light-info">
        Todos los campos abajo se prellenan con las condiciones vigentes del contrato. Están bloqueados por defecto: usa el botón "Modificar" solo en los campos que este adendum debe cambiar. Los campos que dejes bloqueados seguirán heredando el valor vigente del contrato mientras dure este adendum.
    </div>

    <form id="ademdum-form" method="POST" action="{{ route('admin.ademdums.store', ['agreementId' => $agreement->id]) }}" enctype="multipart/form-data">
        @csrf

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Datos principales</h4>
                    <p class="text-muted mb-0" style="font-size:10pt;">Vigencia de este adendum y respaldo firmado. El periodo debe estar dentro de la vigencia del contrato original ({{ optional($agreement->start_at)->format('d/m/Y') }} - {{ optional($agreement->end_at)->format('d/m/Y') }}).</p>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>

                    <div class="col-md-6">
                        <label for="start_at" class="form-label">Inicio del adendum</label>
                        <input id="start_at" type="date" name="start_at" class="form-control"
                            value="{{ old('start_at', now()->format('Y-m-d')) }}"
                            min="{{ optional($agreement->start_at)->format('Y-m-d') }}"
                            max="{{ optional($agreement->end_at)->format('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-6">
                        <label for="end_at" class="form-label">Fin del adendum</label>
                        <input id="end_at" type="date" name="end_at" class="form-control"
                            value="{{ old('end_at', optional($agreement->end_at)->format('Y-m-d')) }}"
                            min="{{ optional($agreement->start_at)->format('Y-m-d') }}"
                            max="{{ optional($agreement->end_at)->format('Y-m-d') }}" required>
                        <small class="text-muted" style="font-size:9pt;">Debe estar dentro de la vigencia del contrato original.</small>
                    </div>

                    <div class="col-md-12">
                        <label for="signed_doc_file" class="form-label">Documento oficial firmado</label>
                        <input id="signed_doc_file" type="file" name="signed_doc_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.bmp,.tiff" required>
                        <small class="note-cyan" style="font-size:10pt;">Formatos permitidos: PDF, JPG, PNG, WEBP, BMP o TIFF (máx. 10 MB). Este será el documento oficial del adendum: asegúrate de adjuntarlo ya firmado por ambas partes.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Detalles de pago</h4>
                    <p class="text-muted mb-0" style="font-size:10pt;">Condiciones económicas vigentes. Desbloquea únicamente lo que este adendum debe modificar.</p>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="frequency_pay" class="form-label mb-0">Frecuencia de pago</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="frequency_pay">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <select id="frequency_pay" name="frequency_pay" class="form-select" data-default-value="{{ $effectiveTerms['frequency_pay'] }}" disabled>
                            @foreach (\App\Models\Agreement::FREQUENCY_PAY_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('frequency_pay', $effectiveTerms['frequency_pay']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted" style="font-size:9pt;">Vigente: {{ \App\Models\Agreement::FREQUENCY_PAY_OPTIONS[$effectiveTerms['frequency_pay']] ?? $effectiveTerms['frequency_pay'] }}.</small>
                    </div>

                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="payment_date" class="form-label mb-0">Día de pago</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="payment_date">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <input id="payment_date" name="payment_date" type="number" min="1" max="31" class="form-control" value="{{ old('payment_date', $effectiveTerms['payment_date']) }}" data-default-value="{{ $effectiveTerms['payment_date'] }}" disabled>
                        <small class="text-muted" style="font-size:9pt;">Vigente: día {{ $effectiveTerms['payment_date'] }} de cada periodo.</small>
                    </div>

                    <div class="col-md-4" id="payment_month_wrapper">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="payment_month" class="form-label mb-0">Mes de pago</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="payment_month">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <select id="payment_month" name="payment_month" class="form-select" data-default-value="{{ $effectiveTerms['payment_month'] }}" disabled>
                            <option value="">Selecciona un mes</option>
                            @foreach (\App\Models\Agreement::MONTHS as $value => $label)
                                <option value="{{ $value }}" @selected((string) old('payment_month', $effectiveTerms['payment_month']) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted" style="font-size:9pt;">Solo aplica cuando la frecuencia vigente es anual.</small>
                    </div>

                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="deadline_pay" class="form-label mb-0">Días de gracia</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="deadline_pay">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <input id="deadline_pay" name="deadline_pay" type="number" min="0" class="form-control" value="{{ old('deadline_pay', $effectiveTerms['deadline_pay']) }}" data-default-value="{{ $effectiveTerms['deadline_pay'] }}" disabled>
                        <small class="text-muted" style="font-size:9pt;">Vigente: {{ $effectiveTerms['deadline_pay'] }} días. Ej: para dar más días de gracia solo en ciertos meses, desbloquea únicamente este campo.</small>
                    </div>

                    <div class="col-md-2">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="currency" class="form-label mb-0">Moneda</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="currency">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <select id="currency" name="currency" class="form-select" data-default-value="{{ $effectiveTerms['currency'] }}" disabled>
                            @foreach (\App\Models\Agreement::CURRENCY_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('currency', $effectiveTerms['currency']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="amount" class="form-label mb-0">Monto</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="amount">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" class="form-control" value="{{ old('amount', $effectiveTerms['amount']) }}" data-default-value="{{ $effectiveTerms['amount'] }}" disabled>
                        <small class="text-muted" style="font-size:9pt;">Vigente: {{ $agreement->currencySymbol() }}{{ number_format((float) $effectiveTerms['amount'], 2) }}.</small>
                    </div>

                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="deposit" class="form-label mb-0">Depósito</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="deposit">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <input id="deposit" name="deposit" type="number" step="0.01" min="0" class="form-control" value="{{ old('deposit', $effectiveTerms['deposit']) }}" data-default-value="{{ $effectiveTerms['deposit'] }}" disabled>
                        <small class="text-muted" style="font-size:9pt;">Vigente: {{ $agreement->currencySymbol() }}{{ number_format((float) $effectiveTerms['deposit'], 2) }}.</small>
                    </div>

                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-end mb-1">
                            <label for="deadline_deposit" class="form-label mb-0">Fecha límite del depósito</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="deadline_deposit">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                        <input id="deadline_deposit" name="deadline_deposit" type="date" class="form-control" value="{{ old('deadline_deposit', optional($effectiveTerms['deadline_deposit'])->format('Y-m-d')) }}" data-default-value="{{ optional($effectiveTerms['deadline_deposit'])->format('Y-m-d') }}" disabled>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="deadline_deposit_unlimited" @checked(empty(old('deadline_deposit', optional($effectiveTerms['deadline_deposit'])->format('Y-m-d')))) disabled>
                            <label class="form-check-label" for="deadline_deposit_unlimited" style="font-size:9pt;">Sin fecha límite</label>
                        </div>
                        <small class="text-muted" style="font-size:9pt;">Vigente: {{ optional($effectiveTerms['deadline_deposit'])->format('d/m/Y') ?? 'No aplica' }}.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="card-title">Política de morosidad</h4>
                        <p class="text-muted mb-0" style="font-size:10pt;">Estos campos se modifican en conjunto, ya que dependen entre sí. Esta política solo se registra como referencia; no se aplica automáticamente a las facturas.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" data-lock-toggle="type_sanction,surcharge_delay,amount_delay,base,frequency_sanction,max_days_unlimited,max_days">
                        <i class="bi bi-unlock-fill"></i> Modificar
                    </button>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label for="type_sanction" class="form-label">Tipo de sanción</label>
                        <small class="text-muted d-block" style="font-size:9pt;">Vigente: {{ \App\Models\Agreement::TYPE_SANCTION_OPTIONS[$effectiveTerms['type_sanction']] ?? $effectiveTerms['type_sanction'] }}.</small>
                        <select id="type_sanction" name="type_sanction" class="form-select" data-default-value="{{ $effectiveTerms['type_sanction'] }}" disabled>
                            @foreach (\App\Models\Agreement::TYPE_SANCTION_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('type_sanction', $effectiveTerms['type_sanction']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12" id="mora_details_wrapper">
                        <div class="row g-3">
                            <div class="col-md-4" id="surcharge_delay_wrapper">
                                <label for="surcharge_delay" class="form-label">Porcentaje de recargo</label>
                                <input id="surcharge_delay" name="surcharge_delay" type="number" step="0.01" min="0" class="form-control" value="{{ old('surcharge_delay', $effectiveTerms['surcharge_delay'] ?? 0) }}" data-default-value="{{ $effectiveTerms['surcharge_delay'] ?? 0 }}" disabled>
                            </div>

                            <div class="col-md-4" id="amount_delay_wrapper">
                                <label for="amount_delay" class="form-label">Monto fijo de recargo</label>
                                <input id="amount_delay" name="amount_delay" type="number" step="0.01" min="0" class="form-control" value="{{ old('amount_delay', $effectiveTerms['amount_delay'] ?? 0) }}" data-default-value="{{ $effectiveTerms['amount_delay'] ?? 0 }}" disabled>
                            </div>

                            <div class="col-md-4" id="base_wrapper">
                                <label for="base" class="form-label">Base de cálculo</label>
                                <select id="base" name="base" class="form-select" data-default-value="{{ $effectiveTerms['base'] }}" disabled>
                                    <option value="">Selecciona una base</option>
                                    @foreach (\App\Models\Agreement::BASE_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('base', $effectiveTerms['base']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="frequency_sanction" class="form-label">Frecuencia de aplicación</label>
                                <select id="frequency_sanction" name="frequency_sanction" class="form-select" data-default-value="{{ $effectiveTerms['frequency_sanction'] }}" disabled>
                                    <option value="">Selecciona una frecuencia</option>
                                    @foreach (\App\Models\Agreement::FREQUENCY_SANCTION_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('frequency_sanction', $effectiveTerms['frequency_sanction']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="max_days_unlimited" class="form-label">Límite de acumulación</label>
                                <select id="max_days_unlimited" name="max_days_unlimited" class="form-select" data-default-value="{{ $effectiveTerms['max_days_unlimited'] ? '1' : '0' }}" disabled>
                                    <option value="0" @selected(old('max_days_unlimited', $effectiveTerms['max_days_unlimited'] ? '1' : '0') == '0')>Definido (indicar días)</option>
                                    <option value="1" @selected(old('max_days_unlimited', $effectiveTerms['max_days_unlimited'] ? '1' : '0') == '1')>Indefinido (sin límite)</option>
                                </select>
                            </div>

                            <div class="col-md-4" id="max_days_wrapper">
                                <label for="max_days" class="form-label">Días máximos de acumulación</label>
                                <input id="max_days" name="max_days" type="number" min="0" class="form-control" value="{{ old('max_days', $effectiveTerms['max_days'] ?? 0) }}" data-default-value="{{ $effectiveTerms['max_days'] ?? 0 }}" disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @php
            $depositPolicyEligible = ((float) old('deposit', $effectiveTerms['deposit'])) > 0
                && !empty(old('deadline_deposit', optional($effectiveTerms['deadline_deposit'])->format('Y-m-d')));
        @endphp

        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="card-title">Política de morosidad del depósito</h4>
                        <p class="text-muted mb-0" style="font-size:10pt;">Estos campos se modifican en conjunto. Aplica si el depósito no se entrega antes de su fecha límite; es independiente de la política de morosidad del alquiler.</p>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap" id="deposit_policy_header_controls" style="display:{{ $depositPolicyEligible ? '' : 'none' }};">
                        <div class="form-check mb-0" id="same_as_rent_deposit_policy_wrapper" style="display:none;">
                            <input class="form-check-input" type="checkbox" id="same_as_rent_deposit_policy">
                            <label class="form-check-label" for="same_as_rent_deposit_policy">Aplicar la misma política del alquiler</label>
                        </div>
                        <div id="deposit_mora_lock_toggle_wrapper">
                            <button type="button" class="btn btn-sm btn-outline-secondary lock-toggle-btn" id="deposit-mora-lock-toggle-btn" data-lock-toggle="type_sanction_deposit,surcharge_delay_deposit,amount_delay_deposit,base_deposit,frequency_sanction_deposit,max_days_unlimited_deposit,max_days_deposit">
                                <i class="bi bi-unlock-fill"></i> Modificar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="deposit_policy_not_eligible_note" style="display:{{ $depositPolicyEligible ? 'none' : '' }};">
                    <div class="alert alert-light-secondary mb-0">Para configurar esta política, el contrato debe tener un depósito mayor a 0 y una fecha límite del depósito definida.</div>
                </div>
                <div class="card-body" id="deposit_policy_same_as_rent_note" style="display:none;">
                    <div class="alert alert-light-info mb-0">Se aplicará la misma política de morosidad definida para el alquiler.</div>
                </div>
                <div class="card-body row g-3" id="deposit_policy_fields_wrapper" style="display:{{ $depositPolicyEligible ? '' : 'none' }};">
                    <div class="col-md-4">
                        <label for="type_sanction_deposit" class="form-label">Tipo de sanción</label>
                        <small class="text-muted d-block" style="font-size:9pt;">Vigente: {{ \App\Models\Agreement::TYPE_SANCTION_OPTIONS[$effectiveTerms['type_sanction_deposit']] ?? $effectiveTerms['type_sanction_deposit'] }}.</small>
                        <select id="type_sanction_deposit" name="type_sanction_deposit" class="form-select" data-default-value="{{ $effectiveTerms['type_sanction_deposit'] }}" disabled>
                            @foreach (\App\Models\Agreement::TYPE_SANCTION_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('type_sanction_deposit', $effectiveTerms['type_sanction_deposit']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12" id="mora_deposit_details_wrapper">
                        <div class="row g-3">
                            <div class="col-md-4" id="surcharge_delay_deposit_wrapper">
                                <label for="surcharge_delay_deposit" class="form-label">Porcentaje de recargo</label>
                                <input id="surcharge_delay_deposit" name="surcharge_delay_deposit" type="number" step="0.01" min="0" class="form-control" value="{{ old('surcharge_delay_deposit', $effectiveTerms['surcharge_delay_deposit'] ?? 0) }}" data-default-value="{{ $effectiveTerms['surcharge_delay_deposit'] ?? 0 }}" disabled>
                            </div>

                            <div class="col-md-4" id="amount_delay_deposit_wrapper">
                                <label for="amount_delay_deposit" class="form-label">Monto fijo de recargo</label>
                                <input id="amount_delay_deposit" name="amount_delay_deposit" type="number" step="0.01" min="0" class="form-control" value="{{ old('amount_delay_deposit', $effectiveTerms['amount_delay_deposit'] ?? 0) }}" data-default-value="{{ $effectiveTerms['amount_delay_deposit'] ?? 0 }}" disabled>
                            </div>

                            <div class="col-md-4" id="base_deposit_wrapper">
                                <label for="base_deposit" class="form-label">Base de cálculo</label>
                                <select id="base_deposit" name="base_deposit" class="form-select" data-default-value="{{ $effectiveTerms['base_deposit'] }}" disabled>
                                    <option value="">Selecciona una base</option>
                                    @foreach (\App\Models\Agreement::BASE_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('base_deposit', $effectiveTerms['base_deposit']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="frequency_sanction_deposit" class="form-label">Frecuencia de aplicación</label>
                                <select id="frequency_sanction_deposit" name="frequency_sanction_deposit" class="form-select" data-default-value="{{ $effectiveTerms['frequency_sanction_deposit'] }}" disabled>
                                    <option value="">Selecciona una frecuencia</option>
                                    @foreach (\App\Models\Agreement::FREQUENCY_SANCTION_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('frequency_sanction_deposit', $effectiveTerms['frequency_sanction_deposit']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="max_days_unlimited_deposit" class="form-label">Límite de acumulación</label>
                                <select id="max_days_unlimited_deposit" name="max_days_unlimited_deposit" class="form-select" data-default-value="{{ $effectiveTerms['max_days_unlimited_deposit'] ? '1' : '0' }}" disabled>
                                    <option value="0" @selected(old('max_days_unlimited_deposit', $effectiveTerms['max_days_unlimited_deposit'] ? '1' : '0') == '0')>Definido (indicar días)</option>
                                    <option value="1" @selected(old('max_days_unlimited_deposit', $effectiveTerms['max_days_unlimited_deposit'] ? '1' : '0') == '1')>Indefinido (sin límite)</option>
                                </select>
                            </div>

                            <div class="col-md-4" id="max_days_deposit_wrapper">
                                <label for="max_days_deposit" class="form-label">Días máximos de acumulación</label>
                                <input id="max_days_deposit" name="max_days_deposit" type="number" min="0" class="form-control" value="{{ old('max_days_deposit', $effectiveTerms['max_days_deposit'] ?? 0) }}" data-default-value="{{ $effectiveTerms['max_days_deposit'] ?? 0 }}" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.agreements.view', $agreement->id) }}" class="btn btn-light-secondary">Volver</a>
                    <button type="submit" class="btn btn-primary" id="ademdum-submit-button">Registrar adendum</button>
                </div>
            </div>
        </section>
    </form>

    <script>
        window.addEventListener('load', () => {
            const form = document.getElementById('ademdum-form');
            if (!form) {
                return;
            }

            const frequencyPaySelect = document.getElementById('frequency_pay');
            const paymentMonthWrapper = document.getElementById('payment_month_wrapper');
            const typeSanctionSelect = document.getElementById('type_sanction');
            const moraDetailsWrapper = document.getElementById('mora_details_wrapper');
            const surchargeDelayWrapper = document.getElementById('surcharge_delay_wrapper');
            const amountDelayWrapper = document.getElementById('amount_delay_wrapper');
            const baseWrapper = document.getElementById('base_wrapper');
            const maxDaysUnlimitedSelect = document.getElementById('max_days_unlimited');
            const maxDaysWrapper = document.getElementById('max_days_wrapper');

            const toggleMonthField = () => {
                paymentMonthWrapper.style.display = frequencyPaySelect.value === 'annual' ? '' : 'none';
            };

            const toggleMaxDaysField = () => {
                maxDaysWrapper.style.display = maxDaysUnlimitedSelect.value === '1' ? 'none' : '';
            };

            const toggleSanctionFields = () => {
                const isNone = typeSanctionSelect.value === 'none';
                const isPercent = typeSanctionSelect.value === 'percent';
                const isAmountFix = typeSanctionSelect.value === 'amount_fix';

                moraDetailsWrapper.style.display = isNone ? 'none' : '';
                surchargeDelayWrapper.style.display = isPercent ? '' : 'none';
                baseWrapper.style.display = isPercent ? '' : 'none';
                amountDelayWrapper.style.display = isAmountFix ? '' : 'none';

                if (!isNone) {
                    toggleMaxDaysField();
                }
            };

            const depositInput = document.getElementById('deposit');
            const deadlineDepositInput = document.getElementById('deadline_deposit');
            const deadlineDepositUnlimitedCheckbox = document.getElementById('deadline_deposit_unlimited');

            // El checkbox solo es interactivo cuando el campo de fecha límite está
            // desbloqueado; si está bloqueado (hereda del contrato), simplemente refleja
            // si el valor vigente es "sin fecha límite", sin permitir cambiarlo.
            const toggleDeadlineDepositUnlimitedCheckbox = () => {
                deadlineDepositUnlimitedCheckbox.disabled = deadlineDepositInput.disabled;

                if (deadlineDepositInput.disabled) {
                    deadlineDepositUnlimitedCheckbox.checked = !deadlineDepositInput.value;
                    deadlineDepositInput.style.display = '';
                    return;
                }

                deadlineDepositInput.style.display = deadlineDepositUnlimitedCheckbox.checked ? 'none' : '';
            };

            deadlineDepositUnlimitedCheckbox?.addEventListener('change', () => {
                if (deadlineDepositUnlimitedCheckbox.checked) {
                    deadlineDepositInput.value = '';
                }

                toggleDeadlineDepositUnlimitedCheckbox();
                updateDepositPolicySectionVisibility();
            });
            toggleDeadlineDepositUnlimitedCheckbox();

            const typeSanctionDepositSelect = document.getElementById('type_sanction_deposit');
            const moraDepositDetailsWrapper = document.getElementById('mora_deposit_details_wrapper');
            const surchargeDelayDepositWrapper = document.getElementById('surcharge_delay_deposit_wrapper');
            const amountDelayDepositWrapper = document.getElementById('amount_delay_deposit_wrapper');
            const baseDepositWrapper = document.getElementById('base_deposit_wrapper');
            const maxDaysUnlimitedDepositSelect = document.getElementById('max_days_unlimited_deposit');
            const maxDaysDepositWrapper = document.getElementById('max_days_deposit_wrapper');

            const toggleMaxDaysDepositField = () => {
                maxDaysDepositWrapper.style.display = maxDaysUnlimitedDepositSelect.value === '1' ? 'none' : '';
            };

            const toggleSanctionDepositFields = () => {
                const isNone = typeSanctionDepositSelect.value === 'none';
                const isPercent = typeSanctionDepositSelect.value === 'percent';
                const isAmountFix = typeSanctionDepositSelect.value === 'amount_fix';

                moraDepositDetailsWrapper.style.display = isNone ? 'none' : '';
                surchargeDelayDepositWrapper.style.display = isPercent ? '' : 'none';
                baseDepositWrapper.style.display = isPercent ? '' : 'none';
                amountDelayDepositWrapper.style.display = isAmountFix ? '' : 'none';

                if (!isNone) {
                    toggleMaxDaysDepositField();
                }
            };

            const sameAsRentDepositPolicyCheckbox = document.getElementById('same_as_rent_deposit_policy');
            const sameAsRentDepositPolicyWrapper = document.getElementById('same_as_rent_deposit_policy_wrapper');
            const depositMoraLockToggleWrapper = document.getElementById('deposit_mora_lock_toggle_wrapper');
            const depositPolicyHeaderControls = document.getElementById('deposit_policy_header_controls');
            const depositPolicyFieldsWrapper = document.getElementById('deposit_policy_fields_wrapper');
            const depositPolicySameAsRentNote = document.getElementById('deposit_policy_same_as_rent_note');
            const depositPolicyNotEligibleNote = document.getElementById('deposit_policy_not_eligible_note');
            const depositMoraLockToggleBtn = document.getElementById('deposit-mora-lock-toggle-btn');

            // Los detalles de la política del depósito solo se muestran cuando NO se
            // está aplicando la misma política del alquiler; en ese caso también se
            // oculta el botón de bloquear/modificar, ya que deja de tener sentido.
            const updateDepositPolicyFieldsVisibility = () => {
                const sameAsRent = sameAsRentDepositPolicyCheckbox.checked;
                depositPolicyFieldsWrapper.style.display = sameAsRent ? 'none' : '';
                depositPolicySameAsRentNote.style.display = sameAsRent ? '' : 'none';
                depositMoraLockToggleWrapper.style.display = sameAsRent ? 'none' : '';
            };

            // El checkbox solo tiene sentido si el alquiler sí tiene una sanción
            // definida; si pasa a "Sin sanción", se oculta y se desmarca.
            const updateSameAsRentDepositVisibility = () => {
                const rentHasSanction = typeSanctionSelect.value !== 'none';
                sameAsRentDepositPolicyWrapper.style.display = rentHasSanction ? '' : 'none';

                if (!rentHasSanction && sameAsRentDepositPolicyCheckbox.checked) {
                    sameAsRentDepositPolicyCheckbox.checked = false;
                    updateDepositPolicyFieldsVisibility();
                }
            };

            // La política de morosidad del depósito solo se puede configurar si el
            // depósito (vigente o editado en este adendum) es mayor a 0 y tiene una
            // fecha límite definida; si no, toda la sección queda oculta con una nota.
            const isDepositPolicyEligible = () => {
                const depositValue = parseFloat(depositInput.value || '0');
                const hasDeposit = !Number.isNaN(depositValue) && depositValue > 0;
                const hasDeadline = !!deadlineDepositInput.value;
                return hasDeposit && hasDeadline;
            };

            const updateDepositPolicySectionVisibility = () => {
                const eligible = isDepositPolicyEligible();
                depositPolicyNotEligibleNote.style.display = eligible ? 'none' : '';
                depositPolicyHeaderControls.style.display = eligible ? '' : 'none';

                if (!eligible) {
                    depositPolicyFieldsWrapper.style.display = 'none';
                    depositPolicySameAsRentNote.style.display = 'none';
                    return;
                }

                updateSameAsRentDepositVisibility();
                updateDepositPolicyFieldsVisibility();
            };

            document.querySelectorAll('.lock-toggle-btn').forEach((button) => {
                const fields = button.dataset.lockToggle.split(',')
                    .map((id) => document.getElementById(id))
                    .filter(Boolean);

                const setLocked = (locked) => {
                    fields.forEach((field) => {
                        if (locked && field.dataset.defaultValue !== undefined) {
                            field.value = field.dataset.defaultValue;
                        }
                        field.disabled = locked;
                    });

                    button.innerHTML = locked
                        ? '<i class="bi bi-unlock-fill"></i> Modificar'
                        : '<i class="bi bi-lock-fill"></i> Bloquear';
                    button.classList.toggle('btn-outline-secondary', locked);
                    button.classList.toggle('btn-outline-primary', !locked);

                    if (fields.includes(typeSanctionSelect)) {
                        toggleSanctionFields();
                        updateSameAsRentDepositVisibility();
                    }

                    if (fields.includes(frequencyPaySelect)) {
                        toggleMonthField();
                    }

                    if (fields.includes(maxDaysUnlimitedSelect)) {
                        toggleMaxDaysField();
                    }

                    if (fields.includes(typeSanctionDepositSelect)) {
                        toggleSanctionDepositFields();
                    }

                    if (fields.includes(maxDaysUnlimitedDepositSelect)) {
                        toggleMaxDaysDepositField();
                    }

                    if (fields.includes(depositInput) || fields.includes(deadlineDepositInput)) {
                        if (fields.includes(deadlineDepositInput)) {
                            toggleDeadlineDepositUnlimitedCheckbox();
                        }
                        updateDepositPolicySectionVisibility();
                    }
                };

                button.addEventListener('click', () => setLocked(!fields[0].disabled));
            });

            frequencyPaySelect?.addEventListener('change', toggleMonthField);
            typeSanctionSelect?.addEventListener('change', toggleSanctionFields);
            maxDaysUnlimitedSelect?.addEventListener('change', toggleMaxDaysField);
            toggleMonthField();
            toggleSanctionFields();

            typeSanctionDepositSelect?.addEventListener('change', toggleSanctionDepositFields);
            maxDaysUnlimitedDepositSelect?.addEventListener('change', toggleMaxDaysDepositField);
            depositInput?.addEventListener('input', updateDepositPolicySectionVisibility);
            deadlineDepositInput?.addEventListener('change', updateDepositPolicySectionVisibility);
            toggleSanctionDepositFields();
            updateDepositPolicySectionVisibility();

            // Checkbox "Aplicar la misma política del alquiler": copia los valores
            // actualmente mostrados en el bloque de morosidad del alquiler hacia el
            // bloque de morosidad del depósito, desbloqueándolo primero para que los
            // valores copiados realmente se envíen en el submit.
            const rentToDepositFieldMap = {
                type_sanction: 'type_sanction_deposit',
                surcharge_delay: 'surcharge_delay_deposit',
                amount_delay: 'amount_delay_deposit',
                base: 'base_deposit',
                frequency_sanction: 'frequency_sanction_deposit',
                max_days_unlimited: 'max_days_unlimited_deposit',
                max_days: 'max_days_deposit',
            };

            const applyRentPolicyToDeposit = () => {
                Object.entries(rentToDepositFieldMap).forEach(([rentId, depositId]) => {
                    const rentField = document.getElementById(rentId);
                    const depositField = document.getElementById(depositId);
                    if (rentField && depositField) {
                        depositField.value = rentField.value;
                    }
                });
                toggleSanctionDepositFields();
            };

            sameAsRentDepositPolicyCheckbox?.addEventListener('change', () => {
                if (sameAsRentDepositPolicyCheckbox.checked) {
                    if (typeSanctionDepositSelect?.disabled) {
                        depositMoraLockToggleBtn?.click();
                    }

                    applyRentPolicyToDeposit();
                }

                updateDepositPolicyFieldsVisibility();
            });

            Object.keys(rentToDepositFieldMap).forEach((rentId) => {
                document.getElementById(rentId)?.addEventListener('change', () => {
                    if (sameAsRentDepositPolicyCheckbox?.checked) {
                        applyRentPolicyToDeposit();
                    }
                });
            });

            const submitButton = document.getElementById('ademdum-submit-button');
            form.addEventListener('submit', () => {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            });
        });
    </script>
@endsection
