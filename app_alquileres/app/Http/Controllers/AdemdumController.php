<?php

namespace App\Http\Controllers;

use App\Models\Ademdum;
use App\Models\Agreement;
use App\Services\NotificationService;
use App\Services\SignedDocService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdemdumController extends Controller
{
    private const CANCELING_RESPONSE_DEADLINE_HOURS = 24;

    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);

        if ($agreement->status !== 'accepted') {
            return redirect()
                ->route('admin.agreements.view', $agreement->id)
                ->withErrors(['agreement' => 'Solo puedes gestionar ademdums en contratos aceptados.']);
        }

        return view('admin.ademdums.index', [
            'agreement' => $agreement,
            'ademdums' => $agreement->ademdums()->latest('created_at')->get(),
            'latestAdemdum' => $agreement->latestAdemdum,
            'effectiveTerms' => $agreement->effectiveTerms(),
            'serviceTypeLabels' => $this->serviceTypeLabels(),
        ]);
    }

    public function store(int $agreementId, Request $request, SignedDocService $signedDocService)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);

        if ($agreement->status !== 'accepted') {
            return back()->withErrors(['agreement' => 'Solo puedes crear adendums cuando el contrato está en estado "accepted".']);
        }

        $validated = $request->validate(array_merge([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'signed_doc_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
        ], $this->businessFieldRules()));

        if ($moraError = $this->validateMoraPolicy($validated)) {
            return back()->withErrors(['type_sanction' => $moraError])->withInput();
        }

        $depositPolicyEligible = $this->isDepositPolicyEligible($agreement, $validated);

        if ($depositPolicyEligible && $moraDepositError = $this->validateMoraPolicyDeposit($validated)) {
            return back()->withErrors(['type_sanction_deposit' => $moraDepositError])->withInput();
        }

        $startAt = Carbon::parse($validated['start_at'])->setTimeFrom(now());
        $endAt = Carbon::parse($validated['end_at'])->setTimeFrom(now());

        if ($periodError = $this->ensurePeriodWithinAgreement($agreement, $startAt, $endAt)) {
            return back()->withErrors(['end_at' => $periodError])->withInput();
        }

        $overriddenFields = array_intersect(array_keys($validated), Agreement::BUSINESS_FIELDS);

        if ($conflictField = $this->findFieldOverrideConflict($agreement, $overriddenFields, $startAt, $endAt)) {
            return back()
                ->withErrors(['start_at' => "Ya existe otro adendum vigente en ese periodo que también modifica el campo \"{$conflictField}\"."])
                ->withInput();
        }

        $ademdum = Ademdum::create(array_merge($this->sparseBusinessFieldValues($validated, $depositPolicyEligible), [
            'agreement_id' => $agreement->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'sent',
        ]));

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAdemdum($agreement->id, $ademdum->id, $request->file('signed_doc_file'));
        }

        $roomerUserId = $agreement->roomer?->user_id;
        if ($roomerUserId) {
            $this->notificationService->create(
                notifyUserId: (int) $roomerUserId,
                title: "Tienes un nuevo adendum #{$ademdum->id} pendiente de revisión",
                priority: 'high',
                body: '',
                link: route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            );
        }

        $this->notificationService->emailUsers(
            [$agreement->lessor?->user, $agreement->roomer?->user],
            "Nuevo adendum #{$ademdum->id} registrado en el contrato {$agreement->contract_number}",
            "Se registró un nuevo adendum para el contrato {$agreement->contract_number}. Queda pendiente de revisión y aceptación por parte del arrendatario."
        );

        return redirect()
            ->route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum creado correctamente.');
    }

    public function edit(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if ($ademdum->status !== 'sent') {
            return redirect()->route('admin.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id]);
        }

        return view('admin.ademdums.edit', [
            'agreement' => $agreement,
            'ademdum' => $ademdum,
            'effectiveTerms' => $agreement->effectiveTerms(),
            'serviceTypeLabels' => $this->serviceTypeLabels(),
        ]);
    }

    public function view(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getAccessibleAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if ($request->user()?->isRoomer() && $ademdum->status === 'sent' && $agreement->status !== 'accepted') {
            return redirect()
                ->route('tenant.agreements.view', $agreement->id)
                ->withErrors(['agreement' => 'Debes aceptar primero el contrato para revisar ademdums pendientes.']);
        }

        $view = $request->user()?->isRoomer() ? 'tenant.ademdums.view' : 'admin.ademdums.view';

        return view($view, [
            'agreement' => $agreement,
            'ademdum' => $ademdum,
            'effectiveTerms' => $agreement->effectiveTerms($ademdum->start_at),
            'serviceTypeLabels' => $this->serviceTypeLabels(),
        ]);
    }

    public function accept(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getAccessibleAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if (!$request->user()?->isRoomer()) {
            abort(403);
        }

        if ($ademdum->status !== 'sent') {
            return redirect()
                ->route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->withErrors(['ademdum' => 'Solo puedes aceptar ademdums en estado "sent".']);
        }


        $ademdum->update([
            'status' => 'accepted',
            'tenant_confirmed_at' => now(),
            'locked_at' => now(),
        ]);

        $lessorUserId = $agreement->lessor?->user_id;
        if ($lessorUserId) {
            $this->notificationService->create(
                notifyUserId: (int) $lessorUserId,
                title: "El arrendatario aceptó el adendum #{$ademdum->id}",
                priority: 'high',
                body: '',
                link: route('admin.agreements.index')
            );
        }

        return redirect()
            ->route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum aceptado correctamente.');
    }

    public function update(int $agreementId, int $ademdumId, Request $request, SignedDocService $signedDocService)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if ($ademdum->status !== 'sent') {
            return redirect()
                ->route('admin.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->withErrors(['ademdum' => 'Este ademdum ya no se puede editar porque su estado no es "sent".']);
        }

        $validated = $request->validate(array_merge([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'signed_doc_file' => [$ademdum->signedDoc ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
        ], $this->businessFieldRules()));

        if ($moraError = $this->validateMoraPolicy($validated)) {
            return back()->withErrors(['type_sanction' => $moraError])->withInput();
        }

        $depositPolicyEligible = $this->isDepositPolicyEligible($agreement, $validated);

        if ($depositPolicyEligible && $moraDepositError = $this->validateMoraPolicyDeposit($validated)) {
            return back()->withErrors(['type_sanction_deposit' => $moraDepositError])->withInput();
        }

        $startAt = Carbon::parse($validated['start_at'])->setTimeFrom(now());
        $endAt = Carbon::parse($validated['end_at'])->setTimeFrom(now());

        if ($periodError = $this->ensurePeriodWithinAgreement($agreement, $startAt, $endAt)) {
            return back()->withErrors(['end_at' => $periodError])->withInput();
        }

        $overriddenFields = array_intersect(array_keys($validated), Agreement::BUSINESS_FIELDS);

        if ($conflictField = $this->findFieldOverrideConflict($agreement, $overriddenFields, $startAt, $endAt, $ademdum->id)) {
            return back()
                ->withErrors(['start_at' => "Ya existe otro adendum vigente en ese periodo que también modifica el campo \"{$conflictField}\"."])
                ->withInput();
        }

        $ademdum->update(array_merge(
            array_fill_keys(Agreement::BUSINESS_FIELDS, null),
            $this->sparseBusinessFieldValues($validated, $depositPolicyEligible),
            [
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]
        ));

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAdemdum($agreement->id, $ademdum->id, $request->file('signed_doc_file'));
        }

        return redirect()
            ->route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum actualizado correctamente.');
    }

    public function delete(int $agreementId, int $ademdumId, Request $request, SignedDocService $signedDocService)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if ($ademdum->status !== 'sent') {
            return back()->withErrors(['ademdum' => 'Este ademdum ya no se puede eliminar porque su estado no es "sent".']);
        }

        $signedDocService->deleteForAdemdum($ademdum->id);
        $ademdum->delete();

        return redirect()
            ->route('admin.ademdums.index', ['agreementId' => $agreement->id])
            ->with('success', 'Ademdum eliminado correctamente.');
    }

    public function canceling(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if ($ademdum->status !== 'accepted') {
            return redirect()
                ->route('admin.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->withErrors(['ademdum' => 'Solo puedes dejar sin efecto ademdums en estado "accepted".']);
        }

        $validated = $request->validate([
            'cancelled_by' => ['required', 'string', 'max:255'],
        ]);

        $ademdum->update([
            'status' => 'canceling',
            'cancelled_by' => trim($validated['cancelled_by']),
            'cancelled_at' => now(),
        ]);

        $roomerUserId = $agreement->roomer?->user_id;
        if ($roomerUserId) {
            $this->notificationService->create(
                notifyUserId: (int) $roomerUserId,
                title: "El arrendador solicitó desestimar el adendum #{$ademdum->id}",
                priority: 'high',
                body: '',
                link: route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            );
        }

        $this->notificationService->emailUsers(
            [$agreement->lessor?->user, $agreement->roomer?->user],
            "Solicitud de desestimación del adendum #{$ademdum->id} ({$agreement->contract_number})",
            "El arrendador solicitó dejar sin efecto el adendum #{$ademdum->id} del contrato {$agreement->contract_number}. Motivo: " . trim($validated['cancelled_by'])
        );

        return redirect()
            ->route('admin.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum marcado como "canceling" correctamente.');
    }

    public function cancelingResponse(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getAccessibleAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        $viewRoute = $request->user()?->isLessor() ? 'admin.ademdums.view' : 'tenant.ademdums.view';

        if ($ademdum->status !== 'canceling') {
            return redirect()
                ->route($viewRoute, ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->withErrors(['ademdum' => 'Solo puedes responder solicitudes de desestimación en estado "canceling".']);
        }

        if ($this->isCancelingResponseExpired($ademdum)) {
            $deadline = $ademdum->cancelled_at?->copy()->addHours(self::CANCELING_RESPONSE_DEADLINE_HOURS) ?? now();

            $ademdum->update([
                'status' => 'cancelled',
                'cancelled_at' => $deadline,
            ]);

            return redirect()
                ->route($viewRoute, ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->withErrors(['ademdum' => 'El tiempo para responder la desestimación ha finalizado (24h). El ademdum quedó en estado "cancelled".']);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
        ]);

        if ($validated['decision'] === 'accept') {
            $ademdum->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $lessorUserId = $agreement->lessor?->user_id;
            $roomerUserId = $agreement->roomer?->user_id;

            $this->notificationService->createForUsers(
                array_filter([(int) $lessorUserId, (int) $roomerUserId]),
                "El adendum #{$ademdum->id} fue desestimado",
                'high',
                sprintf(
                    '<p>Se ejecutó la desestimación del adendum <strong>#%d</strong>.</p><p>El adendum quedó en estado <strong>cancelled</strong>.</p>',
                    $ademdum->id
                ),
                null
            );

            return redirect()
                ->route($viewRoute, ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->with('success', 'Desestimación del adendum aceptada correctamente.');
        }

        $ademdum->update([
            'status' => 'accepted',
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);

        $lessorUserId = $agreement->lessor?->user_id;
        if ($lessorUserId) {
            $this->notificationService->create(
                notifyUserId: (int) $lessorUserId,
                title: "El arrendatario rechazó la desestimación del adendum #{$ademdum->id}",
                priority: 'high',
                body: sprintf(
                    '<p>El arrendatario respondió la solicitud de desestimación del adendum <strong>#%d</strong>.</p><p>Resultado: <strong>rechazada</strong>. El adendum continúa activo en estado <strong>accepted</strong>.</p>',
                    $ademdum->id
                ),
                link: null
            );
        }

        return redirect()
            ->route($viewRoute, ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Solicitud de desestimación rechazada. El adendum sigue activo.');
    }


    public function downloadSignedDoc(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getAccessibleAgreement($agreementId, $request);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);
        $signedDoc = $ademdum->signedDoc;

        if (!$signedDoc || !Storage::disk($signedDoc->disk)->exists($signedDoc->path)) {
            return back()->withErrors(['signed_doc_file' => 'No hay un respaldo físico adjunto para este adendum.']);
        }

        $compressed = Storage::disk($signedDoc->disk)->get($signedDoc->path);
        $raw = gzdecode($compressed);

        if ($raw === false) {
            abort(500, 'No se pudo descomprimir el respaldo físico del adendum.');
        }

        return response()->streamDownload(function () use ($raw): void {
            echo $raw;
        }, $signedDoc->original_name, [
            'Content-Type' => $signedDoc->mime_type,
            'Content-Length' => (string) strlen($raw),
        ]);
    }

    private function getOwnedAgreement(int $agreementId, Request $request): Agreement
    {
        $lessor = $request->user()?->lessor;

        return Agreement::with(['roomer', 'property', 'ademdums', 'latestAdemdum', 'signedDoc'])
            ->where('lessor_id', $lessor?->id)
            ->findOrFail($agreementId);
    }

    private function getAccessibleAgreement(int $agreementId, Request $request): Agreement
    {
        $user = $request->user();

        $query = Agreement::with(['roomer', 'property', 'ademdums', 'latestAdemdum', 'signedDoc']);

        if ($user?->isLessor()) {
            $query->where('lessor_id', $user?->lessor?->id);
        } elseif ($user?->isRoomer()) {
            $query->where('roomer_id', $user?->roomer?->id);
        } else {
            abort(403);
        }

        return $query->findOrFail($agreementId);
    }

    private function getAgreementAdemdum(Agreement $agreement, int $ademdumId): Ademdum
    {
        return Ademdum::query()
            ->with('signedDoc')
            ->where('agreement_id', $agreement->id)
            ->whereKey($ademdumId)
            ->firstOrFail();
    }

    private function serviceTypeLabels(): array
    {
        return [
            'home' => 'Hogar',
            'commercial' => 'Comercial',
        ];
    }

    private function businessFieldRules(): array
    {
        return [
            'frequency_pay' => ['sometimes', Rule::in(array_keys(Agreement::FREQUENCY_PAY_OPTIONS))],
            'payment_date' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'payment_month' => ['required_if:frequency_pay,annual', 'nullable', 'integer', 'min:1', 'max:12'],
            'deadline_pay' => ['sometimes', 'integer', 'min:0'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', Rule::in(array_keys(Agreement::CURRENCY_OPTIONS))],
            'deposit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'deadline_deposit' => ['sometimes', 'nullable', 'date'],
            'type_sanction' => ['sometimes', Rule::in(array_keys(Agreement::TYPE_SANCTION_OPTIONS))],
            'surcharge_delay' => ['nullable', 'numeric', 'min:0'],
            'amount_delay' => ['nullable', 'numeric', 'min:0'],
            'frequency_sanction' => ['nullable', Rule::in(array_keys(Agreement::FREQUENCY_SANCTION_OPTIONS))],
            'base' => ['nullable', Rule::in(array_keys(Agreement::BASE_OPTIONS))],
            'max_days_unlimited' => ['nullable', 'boolean'],
            'max_days' => ['nullable', 'integer', 'min:0'],
            'type_sanction_deposit' => ['sometimes', Rule::in(array_keys(Agreement::TYPE_SANCTION_OPTIONS))],
            'surcharge_delay_deposit' => ['nullable', 'numeric', 'min:0'],
            'amount_delay_deposit' => ['nullable', 'numeric', 'min:0'],
            'frequency_sanction_deposit' => ['nullable', Rule::in(array_keys(Agreement::FREQUENCY_SANCTION_OPTIONS))],
            'base_deposit' => ['nullable', Rule::in(array_keys(Agreement::BASE_OPTIONS))],
            'max_days_unlimited_deposit' => ['nullable', 'boolean'],
            'max_days_deposit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Solo valida la política de morosidad si el adendum realmente la desbloqueó
     * (type_sanction presente en el submit); si está bloqueada, no hay nada que validar.
     */
    private function validateMoraPolicy(array $validated): ?string
    {
        if (!array_key_exists('type_sanction', $validated)) {
            return null;
        }

        return Agreement::validateMoraPolicyInput($validated);
    }

    /**
     * Misma idea que validateMoraPolicy(), pero para la política de morosidad del depósito.
     */
    private function validateMoraPolicyDeposit(array $validated): ?string
    {
        if (!array_key_exists('type_sanction_deposit', $validated)) {
            return null;
        }

        return Agreement::validateMoraPolicyInput($validated, '_deposit');
    }

    /**
     * La política de morosidad del depósito solo tiene sentido si el depósito vigente
     * en este punto del contrato (el que este adendum defina, o si no lo toca, el
     * efectivo heredado) es mayor a 0 y tiene una fecha límite definida.
     */
    private function isDepositPolicyEligible(Agreement $agreement, array $validated): bool
    {
        $effectiveTerms = $agreement->effectiveTerms();

        $deposit = array_key_exists('deposit', $validated) ? $validated['deposit'] : $effectiveTerms['deposit'];
        $deadlineDeposit = array_key_exists('deadline_deposit', $validated) ? $validated['deadline_deposit'] : $effectiveTerms['deadline_deposit'];

        return ((float) $deposit) > 0 && !empty($deadlineDeposit);
    }

    /**
     * A diferencia de Agreement (donde todos los campos de negocio son obligatorios),
     * un adendum solo guarda los campos que el usuario desbloqueó explícitamente;
     * el resto queda NULL (heredado del contrato/adendum vigente). Si la política de
     * morosidad del depósito no es elegible, se ignora cualquier valor enviado para
     * ella y queda en blanco (heredada), sin importar lo que el usuario haya tocado.
     */
    private function sparseBusinessFieldValues(array $validated, bool $depositPolicyEligible = true): array
    {
        $values = Arr::only($validated, Agreement::INDEPENDENT_FIELDS);

        if (array_key_exists('frequency_pay', $values) && $values['frequency_pay'] !== 'annual') {
            $values['payment_month'] = null;
        }

        if (array_key_exists('deposit', $values)) {
            $values['deposit'] = $values['deposit'] ?? 0;
        }

        if (array_key_exists('type_sanction', $validated)) {
            $values = array_merge($values, Agreement::moraPolicyValuesFromInput($validated));
        }

        if ($depositPolicyEligible && array_key_exists('type_sanction_deposit', $validated)) {
            $values = array_merge($values, Agreement::moraPolicyValuesFromInput($validated, '_deposit'));
        }

        return $values;
    }

    /**
     * Compara solo por día calendario: start_at/end_at llevan la hora en que se creó
     * cada registro (ver setTimeFrom(now()) en store/update), así que comparar con hora
     * incluida rechazaría incorrectamente un mismo día si el adendum se crea más tarde
     * en el día que el contrato original.
     */
    private function ensurePeriodWithinAgreement(Agreement $agreement, Carbon $startAt, ?Carbon $endAt): ?string
    {
        if ($startAt->copy()->startOfDay()->lt($agreement->start_at->copy()->startOfDay())) {
            return 'La fecha de inicio del adendum no puede ser anterior al inicio del contrato original.';
        }

        if ($agreement->end_at) {
            if (!$endAt) {
                return 'Debes indicar una fecha de fin para el adendum, ya que el contrato original tiene una fecha de finalización y un adendum no puede extenderla.';
            }

            if ($endAt->copy()->startOfDay()->gt($agreement->end_at->copy()->startOfDay())) {
                return 'La fecha de fin del adendum no puede superar la fecha de fin del contrato original.';
            }
        }

        return null;
    }

    private function findFieldOverrideConflict(Agreement $agreement, array $fields, Carbon $startAt, ?Carbon $endAt, ?int $ignoreAdemdumId = null): ?string
    {
        if (empty($fields)) {
            return null;
        }

        $query = Ademdum::query()
            ->where('agreement_id', $agreement->id)
            ->whereIn('status', ['accepted', 'canceling']);

        if ($ignoreAdemdumId) {
            $query->whereKeyNot($ignoreAdemdumId);
        }

        if ($endAt) {
            $query
                ->where('start_at', '<=', $endAt)
                ->where(function ($subQuery) use ($startAt) {
                    $subQuery->whereNull('end_at')->orWhere('end_at', '>=', $startAt);
                });
        } else {
            $query->where(function ($subQuery) use ($startAt) {
                $subQuery->whereNull('end_at')->orWhere('end_at', '>=', $startAt);
            });
        }

        foreach ($query->get() as $existing) {
            foreach ($fields as $field) {
                if ($existing->{$field} !== null) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function syncExpiredAcceptedAdemdums(Agreement $agreement): void
    {
        $cancelingDeadlineHours = self::CANCELING_RESPONSE_DEADLINE_HOURS;

        Ademdum::query()
            ->where('agreement_id', $agreement->id)
            ->where('status', 'accepted')
            ->whereNotNull('end_at')
            ->where('end_at', '<', now())
            ->get()
            ->each(function (Ademdum $ademdum) use ($agreement): void {
                $ademdum->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $ademdum->end_at,
                    'cancelled_by' => 'Expired period',
                ]);

                $lessorUserId = $agreement->lessor?->user_id;
                $roomerUserId = $agreement->roomer?->user_id;

                $this->notificationService->createForUsers(
                    array_filter([(int) $lessorUserId, (int) $roomerUserId]),
                    "El adendum #{$ademdum->id} finalizó por vigencia",
                    'high',
                    sprintf(
                        '<p>El adendum <strong>#%d</strong> finalizó automáticamente al cumplirse su fecha de vigencia.</p><p>El estado final es <strong>cancelled</strong>.</p>',
                        $ademdum->id
                    ),
                    null
                );
            });

        Ademdum::query()
            ->where('agreement_id', $agreement->id)
            ->where('status', 'canceling')
            ->whereNotNull('cancelled_at')
            ->where('cancelled_at', '<=', now()->subHours($cancelingDeadlineHours))
            ->get()
            ->each(function (Ademdum $ademdum) use ($agreement, $cancelingDeadlineHours): void {
                $deadline = $ademdum->cancelled_at?->copy()->addHours($cancelingDeadlineHours) ?? now();

                $ademdum->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $deadline,
                ]);

                $lessorUserId = $agreement->lessor?->user_id;
                $roomerUserId = $agreement->roomer?->user_id;

                $this->notificationService->createForUsers(
                    array_filter([(int) $lessorUserId, (int) $roomerUserId]),
                    "El adendum #{$ademdum->id} fue desestimado automáticamente",
                    'high',
                    sprintf(
                        '<p>La desestimación del adendum <strong>#%d</strong> se ejecutó automáticamente al vencer el plazo de respuesta.</p><p>El estado final es <strong>cancelled</strong>.</p>',
                        $ademdum->id
                    ),
                    null
                );
            });
    }

    private function isCancelingResponseExpired(Ademdum $ademdum): bool
    {
        if (!$ademdum->cancelled_at) {
            return false;
        }

        return $ademdum->cancelled_at
            ->copy()
            ->addHours(self::CANCELING_RESPONSE_DEADLINE_HOURS)
            ->lessThanOrEqualTo(now());
    }
}
