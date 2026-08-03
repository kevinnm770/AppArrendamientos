@extends('layouts.admin')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Editar contrato {{ $agreement->contract_number }}</h3>
                <p class="text-subtitle text-muted">Solo los contratos en estado de <span class="note-cyan" style="font-weight: bold;">sent</span> se pueden editar o eliminar.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.agreements.index') }}">Agreements</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
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

    <form id="agreement-form" method="POST" action="{{ route('admin.agreements.edit.update', $agreement->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Datos principales</h4>
                    <p class="text-muted mb-0" style="font-size:10pt;">Identificación del contrato: propiedad, arrendatario, vigencia y respaldo firmado.</p>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><strong>Arrendatario:</strong> {{ $agreement->roomer->legal_name }}</div>
                    <div class="col-md-4"><strong>Propiedad:</strong> {{ $agreement->property->name }}</div>
                    <div class="col-md-4"><strong>Servicio:</strong> {{ $serviceTypeLabels[$agreement->service_type] ?? $agreement->service_type }}</div>

                    <div class="col-md-6">
                        <label for="start_at" class="form-label">Inicio</label>
                        <input id="start_at" type="date" name="start_at" class="form-control"
                            value="{{ old('start_at', optional($agreement->start_at)->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label for="end_at" class="form-label">Fin</label>
                        <input id="end_at" type="date" name="end_at" class="form-control"
                            value="{{ old('end_at', optional($agreement->end_at)->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-12">
                        <label for="signed_doc_file" class="form-label">Documento oficial firmado</label>
                        @if ($agreement->signedDoc)
                            <div class="alert alert-light-primary py-2 mb-2">
                                Archivo actual: <strong>{{ $agreement->signedDoc->original_name }}</strong>
                                <a href="{{ route('admin.agreements.signed-doc.download', $agreement->id) }}" class="ms-2">Descargar</a>
                            </div>
                        @endif
                        <input id="signed_doc_file" type="file" name="signed_doc_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.bmp,.tiff" @if (!$agreement->signedDoc) required @endif>
                        <small class="note-cyan" style="font-size:10pt;">
                            Este es el documento oficial del contrato: asegúrate de adjuntarlo ya firmado por ambas partes.
                            @if ($agreement->signedDoc)
                                Si cargas uno nuevo, reemplazará el actual.
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Detalles de pago</h4>
                    <p class="text-muted mb-0" style="font-size:10pt;">Condiciones económicas del contrato: monto, moneda, frecuencia y fecha de pago.</p>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label for="frequency_pay" class="form-label">Frecuencia de pago</label>
                        <select id="frequency_pay" name="frequency_pay" class="form-select" required>
                            @foreach (\App\Models\Agreement::FREQUENCY_PAY_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('frequency_pay', $agreement->frequency_pay) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="payment_date" class="form-label">Día de pago</label>
                        <input id="payment_date" name="payment_date" type="number" min="1" max="31" class="form-control" value="{{ old('payment_date', $agreement->payment_date) }}" required>
                        <small class="text-muted" style="font-size:9pt;">Día del mes en que vence la cuota. Si un mes no tiene ese día, se aplica el último día disponible.</small>
                    </div>

                    <div class="col-md-4" id="payment_month_wrapper">
                        <label for="payment_month" class="form-label">Mes de pago</label>
                        <select id="payment_month" name="payment_month" class="form-select">
                            <option value="">Selecciona un mes</option>
                            @foreach (\App\Models\Agreement::MONTHS as $value => $label)
                                <option value="{{ $value }}" @selected((string) old('payment_month', $agreement->payment_month) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted" style="font-size:9pt;">Solo aplica cuando la frecuencia de pago es anual.</small>
                    </div>

                    <div class="col-md-4">
                        <label for="deadline_pay" class="form-label">Días de gracia</label>
                        <input id="deadline_pay" name="deadline_pay" type="number" min="0" class="form-control" value="{{ old('deadline_pay', $agreement->deadline_pay) }}" required>
                        <small class="text-muted" style="font-size:9pt;">Días después de la fecha de pago sin recargo por mora.</small>
                    </div>

                    <div class="col-md-2">
                        <label for="currency" class="form-label">Moneda</label>
                        <select id="currency" name="currency" class="form-select" required>
                            @foreach (\App\Models\Agreement::CURRENCY_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('currency', $agreement->currency) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="amount" class="form-label">Monto</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" class="form-control" value="{{ old('amount', $agreement->amount) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label for="deposit" class="form-label">Depósito</label>
                        <input id="deposit" name="deposit" type="number" step="0.01" min="0" class="form-control" value="{{ old('deposit', $agreement->deposit) }}" required>
                    </div>

                    @php
                        $deadlineDepositValue = old('deadline_deposit', optional($agreement->deadline_deposit)->format('Y-m-d'));
                    @endphp

                    <div class="col-md-4">
                        <label for="deadline_deposit" class="form-label">Fecha límite del depósito</label>
                        <input id="deadline_deposit" type="date" name="deadline_deposit" class="form-control"
                            value="{{ $deadlineDepositValue }}" @if (empty($deadlineDepositValue)) disabled @endif>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="deadline_deposit_unlimited" @checked(empty($deadlineDepositValue))>
                            <label class="form-check-label" for="deadline_deposit_unlimited" style="font-size:9pt;">Sin fecha límite</label>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Política de morosidad</h4>
                    <p class="text-muted mb-0" style="font-size:10pt;">Define cómo se calculará el recargo si el pago se atrasa más allá de los días de gracia. Esta política queda registrada como referencia del contrato; no se aplica de forma automática a las facturas.</p>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label for="type_sanction" class="form-label">Tipo de sanción</label>
                        <select id="type_sanction" name="type_sanction" class="form-select" required>
                            @foreach (\App\Models\Agreement::TYPE_SANCTION_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('type_sanction', $agreement->type_sanction) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12" id="mora_details_wrapper">
                        <div class="row g-3">
                            <div class="col-md-4" id="surcharge_delay_wrapper">
                                <label for="surcharge_delay" class="form-label">Porcentaje de recargo</label>
                                <input id="surcharge_delay" name="surcharge_delay" type="number" step="0.01" min="0" class="form-control" value="{{ old('surcharge_delay', $agreement->surcharge_delay ?? 0) }}">
                                <small class="text-muted" style="font-size:9pt;">Porcentaje que se aplica por cada periodo de atraso.</small>
                            </div>

                            <div class="col-md-4" id="amount_delay_wrapper">
                                <label for="amount_delay" class="form-label">Monto fijo de recargo</label>
                                <input id="amount_delay" name="amount_delay" type="number" step="0.01" min="0" class="form-control" value="{{ old('amount_delay', $agreement->amount_delay ?? 0) }}">
                                <small class="text-muted" style="font-size:9pt;">Monto fijo que se aplica por cada periodo de atraso.</small>
                            </div>

                            <div class="col-md-4" id="base_wrapper">
                                <label for="base" class="form-label">Base de cálculo</label>
                                <select id="base" name="base" class="form-select">
                                    <option value="">Selecciona una base</option>
                                    @foreach (\App\Models\Agreement::BASE_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('base', $agreement->base) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" style="font-size:9pt;">Sobre qué monto se calcula el porcentaje: la cuota original o el saldo pendiente.</small>
                            </div>

                            <div class="col-md-4">
                                <label for="frequency_sanction" class="form-label">Frecuencia de aplicación</label>
                                <select id="frequency_sanction" name="frequency_sanction" class="form-select">
                                    <option value="">Selecciona una frecuencia</option>
                                    @foreach (\App\Models\Agreement::FREQUENCY_SANCTION_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('frequency_sanction', $agreement->frequency_sanction) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" style="font-size:9pt;">Cada cuánto se vuelve a aplicar el recargo mientras el pago siga atrasado.</small>
                            </div>

                            <div class="col-md-4">
                                <label for="max_days_unlimited" class="form-label">Límite de acumulación</label>
                                <select id="max_days_unlimited" name="max_days_unlimited" class="form-select">
                                    <option value="0" @selected(old('max_days_unlimited', $agreement->max_days_unlimited ? '1' : '0') == '0')>Definido (indicar días)</option>
                                    <option value="1" @selected(old('max_days_unlimited', $agreement->max_days_unlimited ? '1' : '0') == '1')>Indefinido (sin límite)</option>
                                </select>
                            </div>

                            <div class="col-md-4" id="max_days_wrapper">
                                <label for="max_days" class="form-label">Días máximos de acumulación</label>
                                <input id="max_days" name="max_days" type="number" min="0" class="form-control" value="{{ old('max_days', $agreement->max_days ?? 0) }}">
                                <small class="text-muted" style="font-size:9pt;">Pasados estos días de atraso, el recargo deja de seguir acumulándose.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @php
            $depositPolicyEligible = ((float) old('deposit', $agreement->deposit)) > 0 && !empty($deadlineDepositValue);
        @endphp

        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h4 class="card-title">Política de morosidad del depósito</h4>
                        <p class="text-muted mb-0" style="font-size:10pt;">Define el recargo aplicable si el depósito no se entrega antes de la fecha límite indicada arriba. Es independiente de la política de morosidad del alquiler.</p>
                    </div>
                    <div class="form-check" id="same_as_rent_deposit_policy_wrapper" style="display:none;">
                        <input class="form-check-input" type="checkbox" id="same_as_rent_deposit_policy" name="same_as_rent_deposit_policy" value="1" @checked(old('same_as_rent_deposit_policy'))>
                        <label class="form-check-label" for="same_as_rent_deposit_policy">Aplicar la misma política del alquiler</label>
                    </div>
                </div>
                <div class="card-body" id="deposit_policy_not_eligible_note" style="display:{{ $depositPolicyEligible ? 'none' : '' }};">
                    <div class="alert alert-light-secondary mb-0">Para configurar esta política, indica un depósito mayor a 0 y una fecha límite del depósito.</div>
                </div>
                <div class="card-body" id="deposit_policy_same_as_rent_note" style="display:none;">
                    <div class="alert alert-light-info mb-0">Se aplicará la misma política de morosidad definida para el alquiler.</div>
                </div>
                <div class="card-body row g-3" id="deposit_policy_fields_wrapper" style="display:{{ $depositPolicyEligible ? '' : 'none' }};">
                    <div class="col-md-4">
                        <label for="type_sanction_deposit" class="form-label">Tipo de sanción</label>
                        <select id="type_sanction_deposit" name="type_sanction_deposit" class="form-select" required>
                            @foreach (\App\Models\Agreement::TYPE_SANCTION_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('type_sanction_deposit', $agreement->type_sanction_deposit) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12" id="mora_deposit_details_wrapper">
                        <div class="row g-3">
                            <div class="col-md-4" id="surcharge_delay_deposit_wrapper">
                                <label for="surcharge_delay_deposit" class="form-label">Porcentaje de recargo</label>
                                <input id="surcharge_delay_deposit" name="surcharge_delay_deposit" type="number" step="0.01" min="0" class="form-control" value="{{ old('surcharge_delay_deposit', $agreement->surcharge_delay_deposit ?? 0) }}">
                                <small class="text-muted" style="font-size:9pt;">Porcentaje que se aplica por cada periodo de atraso.</small>
                            </div>

                            <div class="col-md-4" id="amount_delay_deposit_wrapper">
                                <label for="amount_delay_deposit" class="form-label">Monto fijo de recargo</label>
                                <input id="amount_delay_deposit" name="amount_delay_deposit" type="number" step="0.01" min="0" class="form-control" value="{{ old('amount_delay_deposit', $agreement->amount_delay_deposit ?? 0) }}">
                                <small class="text-muted" style="font-size:9pt;">Monto fijo que se aplica por cada periodo de atraso.</small>
                            </div>

                            <div class="col-md-4" id="base_deposit_wrapper">
                                <label for="base_deposit" class="form-label">Base de cálculo</label>
                                <select id="base_deposit" name="base_deposit" class="form-select">
                                    <option value="">Selecciona una base</option>
                                    @foreach (\App\Models\Agreement::BASE_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('base_deposit', $agreement->base_deposit) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" style="font-size:9pt;">Sobre qué monto se calcula el porcentaje: el depósito original o el saldo pendiente.</small>
                            </div>

                            <div class="col-md-4">
                                <label for="frequency_sanction_deposit" class="form-label">Frecuencia de aplicación</label>
                                <select id="frequency_sanction_deposit" name="frequency_sanction_deposit" class="form-select">
                                    <option value="">Selecciona una frecuencia</option>
                                    @foreach (\App\Models\Agreement::FREQUENCY_SANCTION_OPTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('frequency_sanction_deposit', $agreement->frequency_sanction_deposit) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" style="font-size:9pt;">Cada cuánto se vuelve a aplicar el recargo mientras el depósito siga sin entregarse.</small>
                            </div>

                            <div class="col-md-4">
                                <label for="max_days_unlimited_deposit" class="form-label">Límite de acumulación</label>
                                <select id="max_days_unlimited_deposit" name="max_days_unlimited_deposit" class="form-select">
                                    <option value="0" @selected(old('max_days_unlimited_deposit', $agreement->max_days_unlimited_deposit ? '1' : '0') == '0')>Definido (indicar días)</option>
                                    <option value="1" @selected(old('max_days_unlimited_deposit', $agreement->max_days_unlimited_deposit ? '1' : '0') == '1')>Indefinido (sin límite)</option>
                                </select>
                            </div>

                            <div class="col-md-4" id="max_days_deposit_wrapper">
                                <label for="max_days_deposit" class="form-label">Días máximos de acumulación</label>
                                <input id="max_days_deposit" name="max_days_deposit" type="number" min="0" class="form-control" value="{{ old('max_days_deposit', $agreement->max_days_deposit ?? 0) }}">
                                <small class="text-muted" style="font-size:9pt;">Pasados estos días de atraso, el recargo deja de seguir acumulándose.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer my-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-danger" id="delete-agreement-button">
                        <i class="fa-solid fa-trash"></i> Eliminar
                    </button>
                    <div class="d-flex justify-content-end gap-2 ms-auto">
                        <a href="{{ route('admin.agreements.index') }}" class="btn btn-light-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary" id="agreement-submit-button">Guardar cambios</button>
                    </div>
                </div>
            </div>
        </section>
    </form>

    <form method="POST" action="{{ route('admin.agreements.delete', $agreement->id) }}" id="delete-agreement-form">
        @csrf
        @method('DELETE')
    </form>

    <script>
        window.addEventListener('load', () => {
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

            frequencyPaySelect?.addEventListener('change', toggleMonthField);
            typeSanctionSelect?.addEventListener('change', toggleSanctionFields);
            maxDaysUnlimitedSelect?.addEventListener('change', toggleMaxDaysField);
            toggleMonthField();
            toggleSanctionFields();

            const depositInput = document.getElementById('deposit');
            const deadlineDepositInput = document.getElementById('deadline_deposit');
            const deadlineDepositUnlimitedCheckbox = document.getElementById('deadline_deposit_unlimited');

            const toggleDeadlineDepositField = () => {
                const unlimited = deadlineDepositUnlimitedCheckbox.checked;
                deadlineDepositInput.disabled = unlimited;
                deadlineDepositInput.style.display = unlimited ? 'none' : '';

                if (unlimited) {
                    deadlineDepositInput.value = '';
                }
            };

            deadlineDepositUnlimitedCheckbox?.addEventListener('change', () => {
                toggleDeadlineDepositField();
                updateDepositPolicySectionVisibility();
            });
            toggleDeadlineDepositField();

            const typeSanctionDepositSelect = document.getElementById('type_sanction_deposit');
            const moraDepositDetailsWrapper = document.getElementById('mora_deposit_details_wrapper');
            const surchargeDelayDepositWrapper = document.getElementById('surcharge_delay_deposit_wrapper');
            const amountDelayDepositWrapper = document.getElementById('amount_delay_deposit_wrapper');
            const baseDepositWrapper = document.getElementById('base_deposit_wrapper');
            const maxDaysUnlimitedDepositSelect = document.getElementById('max_days_unlimited_deposit');
            const maxDaysDepositWrapper = document.getElementById('max_days_deposit_wrapper');
            const sameAsRentDepositPolicyCheckbox = document.getElementById('same_as_rent_deposit_policy');
            const sameAsRentDepositPolicyWrapper = document.getElementById('same_as_rent_deposit_policy_wrapper');
            const depositPolicyFieldsWrapper = document.getElementById('deposit_policy_fields_wrapper');
            const depositPolicySameAsRentNote = document.getElementById('deposit_policy_same_as_rent_note');
            const depositPolicyNotEligibleNote = document.getElementById('deposit_policy_not_eligible_note');

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

            // Mapa campo del alquiler -> campo equivalente del depósito, usado para el
            // autorrelleno del checkbox "Aplicar la misma política del alquiler".
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

            // Los detalles de la política del depósito solo se muestran (y son
            // obligatorios) cuando NO se está aplicando la misma política del alquiler.
            const updateDepositPolicyFieldsVisibility = () => {
                const sameAsRent = sameAsRentDepositPolicyCheckbox.checked;
                depositPolicyFieldsWrapper.style.display = sameAsRent ? 'none' : '';
                depositPolicySameAsRentNote.style.display = sameAsRent ? '' : 'none';
                typeSanctionDepositSelect.required = !sameAsRent;
            };

            // El checkbox solo tiene sentido si el alquiler sí tiene una sanción
            // definida; si el alquiler pasa a "Sin sanción", se oculta y se desmarca.
            const updateSameAsRentDepositVisibility = () => {
                const rentHasSanction = typeSanctionSelect.value !== 'none';
                sameAsRentDepositPolicyWrapper.style.display = rentHasSanction ? '' : 'none';

                if (!rentHasSanction && sameAsRentDepositPolicyCheckbox.checked) {
                    sameAsRentDepositPolicyCheckbox.checked = false;
                    updateDepositPolicyFieldsVisibility();
                }
            };

            // La política de morosidad del depósito solo se puede configurar si hay un
            // depósito mayor a 0 y una fecha límite definida; si no, toda la sección
            // (checkbox y campos) queda oculta y se muestra una nota explicativa.
            const isDepositPolicyEligible = () => {
                const depositValue = parseFloat(depositInput.value || '0');
                const hasDeposit = !Number.isNaN(depositValue) && depositValue > 0;
                const hasDeadline = !deadlineDepositUnlimitedCheckbox.checked && !!deadlineDepositInput.value;
                return hasDeposit && hasDeadline;
            };

            const updateDepositPolicySectionVisibility = () => {
                const eligible = isDepositPolicyEligible();
                depositPolicyNotEligibleNote.style.display = eligible ? 'none' : '';

                if (!eligible) {
                    sameAsRentDepositPolicyWrapper.style.display = 'none';
                    depositPolicyFieldsWrapper.style.display = 'none';
                    depositPolicySameAsRentNote.style.display = 'none';
                    typeSanctionDepositSelect.required = false;
                    return;
                }

                updateSameAsRentDepositVisibility();
                updateDepositPolicyFieldsVisibility();
            };

            sameAsRentDepositPolicyCheckbox?.addEventListener('change', () => {
                if (sameAsRentDepositPolicyCheckbox.checked) {
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

            typeSanctionSelect?.addEventListener('change', updateSameAsRentDepositVisibility);
            typeSanctionDepositSelect?.addEventListener('change', toggleSanctionDepositFields);
            maxDaysUnlimitedDepositSelect?.addEventListener('change', toggleMaxDaysDepositField);
            depositInput?.addEventListener('input', updateDepositPolicySectionVisibility);
            deadlineDepositInput?.addEventListener('change', updateDepositPolicySectionVisibility);
            toggleSanctionDepositFields();
            updateDepositPolicySectionVisibility();

            const agreementForm = document.getElementById('agreement-form');
            const agreementSubmitButton = document.getElementById('agreement-submit-button');
            agreementForm?.addEventListener('submit', () => {
                agreementSubmitButton.disabled = true;
                agreementSubmitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            });

            const deleteButton = document.getElementById('delete-agreement-button');
            const deleteForm = document.getElementById('delete-agreement-form');

            if (deleteButton && deleteForm) {
                deleteButton.addEventListener('click', async function() {
                    if (typeof Swal === 'undefined') {
                        if (confirm('¿Seguro que deseas eliminar este contrato? Esta acción no se puede deshacer.')) {
                            deleteForm.submit();
                        }
                        return;
                    }

                    const result = await Swal.fire({
                        title: 'Eliminar contrato',
                        text: 'Esta acción no se puede deshacer.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc3545'
                    });

                    if (result.isConfirmed) {
                        deleteButton.disabled = true;
                        deleteForm.submit();
                    }
                });
            }
        });
    </script>
@endsection
