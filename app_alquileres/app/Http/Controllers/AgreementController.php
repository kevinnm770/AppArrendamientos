<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\Roomer;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SignedDocService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgreementController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $agreements = collect();

        if ($user->isLessor()) {
            $lessor = $user->lessor;

            $agreements = Agreement::with(['property', 'roomer', 'latestAdemdum', 'signedDoc'])
                ->where('lessor_id', $lessor->id)
                ->orderByDesc('start_at')
                ->get();

            $agreements->each(function (Agreement $agreement) use ($user): void {
                $this->syncExpiredAcceptedAgreement($agreement, $user->id);
                $this->finalizeExpiredCanceling($agreement, $user->id);
            });

            return view('admin.agreements.index', [
                'agreements' => $agreements,
            ]);
        }

        if ($user->isRoomer()) {
            $roomer = $user->roomer;

            $agreements = Agreement::with(['property', 'lessor', 'latestAdemdum', 'signedDoc'])
                ->withCount(['ademdums as pending_ademdums_count' => fn (Builder $query) => $query->where('status', 'sent')])
                ->where('roomer_id', $roomer->id)
                ->orderByDesc('start_at')
                ->get();

            $agreements->each(function (Agreement $agreement) use ($user): void {
                $this->syncExpiredAcceptedAgreement($agreement, $user->id);
                $this->finalizeExpiredCanceling($agreement, $user->id);
            });

            return view('tenant.agreements.index', [
                'agreements' => $agreements,
            ]);
        }
    }

    public function register(Request $request)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.agreements.index')
                ->withErrors(['lessor' => 'Debes completar tu perfil de arrendador antes de registrar contratos.']);
        }

        $properties = Property::where('lessor_id', $lessor->id)
            ->where('status', '!=', 'occupied')
            ->orderBy('name')
            ->get(['id', 'name', 'service_type', 'status', 'price', 'currency']);


        $selectedRoomer = null;
        $oldRoomerId = $request->old('roomer_id');

        if ($oldRoomerId) {
            $selectedRoomer = Roomer::query()
                ->whereKey((int) $oldRoomerId)
                ->first(['id', 'legal_name', 'id_number']);
        }

        return view('admin.agreements.register', [
            'properties' => $properties,
            'selectedRoomer' => $selectedRoomer,
            'serviceTypeLabels' => $this->serviceTypeLabels(),
        ]);
    }

    public function edit(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'sent') {
            return redirect()->route('admin.agreements.view', $agreement->id);
        }

        return view('admin.agreements.edit', [
            'agreement' => $agreement,
            'serviceTypeLabels' => $this->serviceTypeLabels(),
        ]);
    }

    public function view(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        $user = $request->user();

        if ($user->isLessor()) {
            return view('admin.agreements.view', [
                'agreement' => $agreement,
                'serviceTypeLabels' => $this->serviceTypeLabels(),
            ]);
        }

        if ($user->isRoomer()) {
            return view('tenant.agreements.view', [
                'agreement' => $agreement,
                'serviceTypeLabels' => $this->serviceTypeLabels(),
            ]);
        }
    }

    public function update(int $agreementId, Request $request, SignedDocService $signedDocService)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'sent') {
            return redirect()
                ->route('admin.agreements.view', $agreement->id)
                ->withErrors(['agreement' => 'Este contrato ya no se puede editar porque su estado no es "sent".']);
        }

        $validated = $request->validate(array_merge([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'signed_doc_file' => [$agreement->signedDoc ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
        ], $this->businessFieldRules()));

        if ($moraError = $this->validateMoraPolicy($validated)) {
            return back()->withErrors(['type_sanction' => $moraError])->withInput();
        }

        $startAt = Carbon::parse($validated['start_at'])->setTimeFrom(now());
        $endAt = Carbon::parse($validated['end_at'])->setTimeFrom(now());

        if ($this->hasDateCollision('property_id', (int) $agreement->property_id, $startAt, $endAt, $agreement->id)) {
            return back()
                ->withErrors(['start_at' => 'La propiedad ya tiene un contrato activo en ese rango de tiempo.'])
                ->withInput();
        }

        if ($this->hasDateCollision('roomer_id', (int) $agreement->roomer_id, $startAt, $endAt, $agreement->id)) {
            return back()
                ->withErrors(['start_at' => 'El arrendatario ya tiene un contrato activo en ese rango de tiempo.'])
                ->withInput();
        }

        $agreement->update(array_merge($this->businessFieldValues($validated), [
            'start_at' => $startAt,
            'end_at' => $endAt,
            'updated_by_user_id' => $request->user()->id,
        ]));

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAgreement($agreement->id, $request->file('signed_doc_file'));
        }

        return redirect()
            ->route('admin.agreements.edit', $agreement->id)
            ->with('success', 'Contrato actualizado correctamente.');
    }

    public function accept(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'sent') {
            return redirect()
                ->route('tenant.agreements.view', $agreement->id)
                ->withErrors(['agreement' => 'Solo puedes aceptar contratos en estado "sent".']);
        }

        DB::transaction(function () use ($agreement, $request) {
            $agreement->update([
                'status' => 'accepted',
                'tenant_confirmed_at' => now(),
                'locked_at' => now(),
                'updated_by_user_id' => $request->user()->id,
            ]);

            $agreement->property()->update([
                'status' => 'occupied',
            ]);

            $lessorUserId = $agreement->lessor?->user_id;

            if ($lessorUserId) {
                $this->notificationService->create(
                    notifyUserId: (int) $lessorUserId,
                    title: "El arrendatario aceptó el contrato {$agreement->contract_number}",
                    priority: 'high',
                    body: '',
                    link: route('admin.agreements.index')
                );
            }
        });

        return redirect()
            ->route('tenant.agreements.view', $agreement->id)
            ->with('success', 'Contrato aceptado correctamente.');
    }

    public function canceling(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'accepted') {
            return redirect()
                ->route('admin.agreements.view', $agreement->id)
                ->withErrors(['agreement' => 'Solo puedes romper contratos en estado "accepted".']);
        }

        $validated = $request->validate([
            'canceled_by' => ['required', 'string', 'max:1000'],
        ]);

        $agreement->update([
            'status' => 'canceling',
            'canceled_by' => trim($validated['canceled_by']),
            'canceled_date' => now(),
            'updated_by_user_id' => $request->user()->id,
        ]);

        $roomerUserId = $agreement->roomer?->user_id;
        if ($roomerUserId) {
            $this->notificationService->create(
                notifyUserId: (int) $roomerUserId,
                title: "El arrendador solicitó desestimar el contrato {$agreement->contract_number}",
                priority: 'high',
                body: '',
                link: route('tenant.agreements.view', $agreement->id)
            );
        }

        $this->notificationService->emailUsers(
            [$agreement->lessor?->user, $agreement->roomer?->user],
            "Solicitud de ruptura del contrato {$agreement->contract_number}",
            "El arrendador solicitó romper el contrato {$agreement->contract_number}. Motivo: " . trim($validated['canceled_by'])
        );

        return redirect()
            ->route('admin.agreements.index')
            ->with('success', 'El contrato fue marcado en estado "canceling".');
    }

    public function cancelingResponse(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $viewRoute = $request->user()?->isLessor() ? 'admin.agreements.view' : 'tenant.agreements.view';

        if ($agreement->status !== 'canceling') {
            return redirect()
                ->route($viewRoute, $agreement->id)
                ->withErrors(['agreement' => 'Solo puedes responder solicitudes de cancelación en estado "canceling".']);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
        ]);

        if ($validated['decision'] === 'accept') {
            $agreement->update([
                'status' => 'cancelled',
                'updated_by_user_id' => $request->user()->id,
            ]);

            $lessorUserId = $agreement->lessor?->user_id;
            $roomerUserId = $agreement->roomer?->user_id;

            $title = "El contrato {$agreement->contract_number} fue desestimado";
            $body = sprintf(
                '<p>Se ejecutó la desestimación del contrato <strong>%s</strong>.</p><p>La relación contractual quedó finalizada y su estado pasó a <strong>cancelled</strong>.</p>',
                $agreement->contract_number
            );

            $this->notificationService->createForUsers(
                array_filter([(int) $lessorUserId, (int) $roomerUserId]),
                $title,
                'high',
                $body,
                null
            );

            return redirect()
                ->route($viewRoute, $agreement->id)
                ->with('success', 'Cancelación del contrato aceptada correctamente.');
        }

        $agreement->update([
            'status' => 'accepted',
            'canceled_by' => null,
            'canceled_date' => null,
            'updated_by_user_id' => $request->user()->id,
        ]);

        $lessorUserId = $agreement->lessor?->user_id;
        if ($lessorUserId) {
            $this->notificationService->create(
                notifyUserId: (int) $lessorUserId,
                title: "El arrendatario rechazó la desestimación del contrato {$agreement->contract_number}",
                priority: 'high',
                body: sprintf(
                    '<p>El arrendatario respondió la solicitud de desestimación del contrato <strong>%s</strong>.</p><p>Resultado: <strong>rechazada</strong>. El contrato continúa vigente en estado <strong>accepted</strong>.</p>',
                    $agreement->contract_number
                ),
                link: null
            );
        }

        return redirect()
            ->route($viewRoute, $agreement->id)
            ->with('success', 'Solicitud de cancelación rechazada. El contrato sigue activo.');
    }

    public function delete(int $agreementId, Request $request, SignedDocService $signedDocService)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'sent') {
            return back()->withErrors(['agreement' => 'Este contrato ya no se puede eliminar porque el arrendatario ya lo aceptó.']);
        }

        DB::transaction(function () use ($agreement, $signedDocService) {
            $signedDocService->deleteForAgreement($agreement->id);

            foreach ($agreement->ademdums as $ademdum) {
                $signedDocService->deleteForAdemdum($ademdum->id);
            }

            $agreement->delete();
        });

        return redirect()
            ->route('admin.agreements.index')
            ->with('success', 'Contrato eliminado correctamente.');
    }

    public function roomerByIdNumber(string $idNumber)
    {
        $roomer = Roomer::query()
            ->where('id_number', trim($idNumber))
            ->first(['id', 'legal_name', 'id_number']);

        if (!$roomer) {
            return response()->json([
                'found' => false,
                'message' => 'No existe un arrendatario registrado con esa cédula.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'roomer' => [
                'id' => $roomer->id,
                'legal_name' => $roomer->legal_name,
                'id_number' => $roomer->id_number,
            ],
        ]);
    }

    /**
     * Términos de pago vigentes del contrato (resolviendo adéndums activos) para
     * precargar la línea de canon al crear una factura, más una tasa de IVA sugerida
     * según el uso del inmueble (vivienda = exento, comercial = 13%).
     */
    public function effectiveBillingTerms(Request $request, int $agreementId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $agreement = Agreement::with('property')
            ->where('lessor_id', $lessor->id)
            ->find($agreementId);

        if (!$agreement) {
            return response()->json(['message' => 'Contrato no encontrado.'], 404);
        }

        $terms = $agreement->effectiveTerms();
        $isHousing = $agreement->service_type === 'home';

        return response()->json([
            'amount' => $terms['amount'],
            'currency' => $terms['currency'],
            'frequency_pay' => $terms['frequency_pay'],
            'suggested_tax_rate' => $isHousing ? 0 : 13,
            'suggested_description' => $isHousing
                ? 'Canon de arrendamiento de vivienda'
                : 'Canon de arrendamiento de local comercial',
        ]);
    }

    public function store(Request $request, SignedDocService $signedDocService)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.agreements.index')
                ->withErrors(['lessor' => 'Debes completar tu perfil de arrendador antes de registrar contratos.']);
        }

        $validated = $request->validate(array_merge([
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where(
                    fn (QueryBuilder $query) => $query
                        ->where('lessor_id', $lessor->id)
                        ->where('status', '!=', 'occupied')
                ),
            ],
            'roomer_id' => ['required', Rule::exists('roomers', 'id')],
            'service_type' => ['required', Rule::in(['home', 'commercial'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'signed_doc_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
        ], $this->businessFieldRules()));

        if ($moraError = $this->validateMoraPolicy($validated)) {
            return back()->withErrors(['type_sanction' => $moraError])->withInput();
        }

        $property = Property::where('lessor_id', $lessor->id)
            ->findOrFail((int) $validated['property_id']);

        if ($property->service_type !== $validated['service_type']) {
            return back()
                ->withErrors(['service_type' => 'El tipo de servicio del contrato debe coincidir con el de la propiedad seleccionada.'])
                ->withInput();
        }

        if ($property->status === 'occupied') {
            return back()
                ->withErrors(['property_id' => 'Solo se puede registrar un contrato en una propiedad que no esté ocupada.'])
                ->withInput();
        }

        $startAt = Carbon::parse($validated['start_at'])->setTimeFrom(now());
        $endAt = Carbon::parse($validated['end_at'])->setTimeFrom(now());

        if ($this->hasDateCollision('property_id', (int) $validated['property_id'], $startAt, $endAt)) {
            return back()
                ->withErrors(['property_id' => 'La propiedad ya tiene un contrato activo en ese rango de tiempo.'])
                ->withInput();
        }

        if ($this->hasDateCollision('roomer_id', (int) $validated['roomer_id'], $startAt, $endAt)) {
            return back()
                ->withErrors(['roomer_id' => 'El arrendatario ya tiene un contrato activo en ese rango de tiempo.'])
                ->withInput();
        }

        $agreement = DB::transaction(function () use ($validated, $lessor, $user, $startAt, $endAt) {
            $agreement = Agreement::create(array_merge($this->businessFieldValues($validated), [
                'contract_number' => 'TMP-' . Str::random(20),
                'property_id' => (int) $validated['property_id'],
                'lessor_id' => $lessor->id,
                'roomer_id' => (int) $validated['roomer_id'],
                'service_type' => $validated['service_type'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'sent',
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]));

            $agreement->update([
                'contract_number' => sprintf('CTR-%d-%06d', $agreement->created_at->year, $agreement->id),
            ]);

            return $agreement;
        });

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAgreement($agreement->id, $request->file('signed_doc_file'));
        }

        $roomerUserId = $agreement->roomer?->user_id;
        if ($roomerUserId) {
            $this->notificationService->create(
                notifyUserId: (int) $roomerUserId,
                title: "Tienes un nuevo contrato {$agreement->contract_number} pendiente de revisión",
                priority: 'high',
                body: '',
                link: route('tenant.agreements.view', $agreement->id)
            );
        }

        $this->notificationService->emailUsers(
            [$agreement->lessor?->user, $agreement->roomer?->user],
            "Nuevo contrato {$agreement->contract_number} registrado",
            "Se registró el contrato {$agreement->contract_number}. Queda pendiente de revisión y aceptación por parte del arrendatario."
        );

        return redirect()
            ->route('admin.agreements.index')
            ->with('success', 'Contrato registrado correctamente.');
    }


    public function downloadSignedDoc(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $signedDoc = $agreement->signedDoc;

        if (!$signedDoc || !Storage::disk($signedDoc->disk)->exists($signedDoc->path)) {
            return back()->withErrors(['signed_doc_file' => 'No hay un respaldo físico adjunto para este contrato.']);
        }

        $compressed = Storage::disk($signedDoc->disk)->get($signedDoc->path);
        $raw = gzdecode($compressed);

        if ($raw === false) {
            abort(500, 'No se pudo descomprimir el respaldo físico del contrato.');
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
        $user = $request->user();
        $lessor = $user?->lessor;
        $roomer = $user?->roomer;

        $query = Agreement::with(['roomer', 'property', 'ademdums', 'latestAdemdum', 'signedDoc']);

        if ($user?->isLessor()) {
            $query->where('lessor_id', $lessor?->id);
        } elseif ($user?->isRoomer()) {
            $query->where('roomer_id', $roomer?->id);
        } else {
            abort(403);
        }

        $agreement = $query->findOrFail($agreementId);

        $this->syncExpiredAcceptedAgreement($agreement, $user?->id);
        $this->finalizeExpiredCanceling($agreement, $user?->id);

        return $agreement->fresh(['roomer', 'property', 'ademdums', 'latestAdemdum', 'signedDoc']);
    }

    private function finalizeExpiredCanceling(Agreement $agreement, ?int $updatedByUserId = null): void
    {
        if ($agreement->status !== 'canceling' || !$agreement->canceled_date) {
            return;
        }

        if ($agreement->canceled_date->copy()->addDay()->isFuture()) {
            return;
        }

        $agreement->update([
            'status' => 'cancelled',
            'updated_by_user_id' => $updatedByUserId,
        ]);

        $lessorUserId = $agreement->lessor?->user_id;
        $roomerUserId = $agreement->roomer?->user_id;

        $this->notificationService->createForUsers(
            array_filter([(int) $lessorUserId, (int) $roomerUserId]),
            "El contrato {$agreement->contract_number} fue desestimado automáticamente",
            'high',
            sprintf(
                '<p>La desestimación del contrato <strong>%s</strong> se ejecutó automáticamente al vencer el plazo de respuesta.</p><p>El estado final es <strong>cancelled</strong>.</p>',
                $agreement->contract_number
            ),
            null
        );
    }


    private function syncExpiredAcceptedAgreement(Agreement $agreement, ?int $updatedByUserId = null): void
    {
        if ($agreement->status !== 'accepted' || !$agreement->end_at || $agreement->end_at->isFuture()) {
            return;
        }

        $agreement->update([
            'status' => 'finished',
            'updated_by_user_id' => $updatedByUserId,
        ]);

        $lessorUserId = $agreement->lessor?->user_id;
        $roomerUserId = $agreement->roomer?->user_id;

        $this->notificationService->createForUsers(
            array_filter([(int) $lessorUserId, (int) $roomerUserId]),
            "El contrato {$agreement->contract_number} finalizó por vigencia",
            'high',
            sprintf(
                '<p>El contrato <strong>%s</strong> finalizó automáticamente al cumplirse su fecha de vigencia.</p><p>El estado final es <strong>finished</strong>.</p>',
                $agreement->contract_number
            ),
            null
        );
    }

    private function hasDateCollision(string $column, int $id, Carbon $startAt, ?Carbon $endAt, ?int $ignoreAgreementId = null): bool
    {
        $query = Agreement::query()
            ->where($column, $id)
            ->whereNotIn('status', ['cancelled', 'finished']);

        if ($ignoreAgreementId) {
            $query->where('id', '!=', $ignoreAgreementId);
        }

        if ($endAt) {
            $query
                ->where('start_at', '<=', $endAt)
                ->where(function (Builder $subQuery) use ($startAt) {
                    $subQuery
                        ->whereNull('end_at')
                        ->orWhere('end_at', '>=', $startAt);
                });
        } else {
            $query->where(function (Builder $subQuery) use ($startAt) {
                $subQuery
                    ->whereNull('end_at')
                    ->orWhere('end_at', '>=', $startAt);
            });
        }

        return $query->exists();
    }

    private function serviceTypeLabels(): array
    {
        return [
            'home' => 'Vivienda',
            'commercial' => 'Local comercial',
        ];
    }

    private function businessFieldRules(): array
    {
        return [
            'frequency_pay' => ['required', Rule::in(array_keys(Agreement::FREQUENCY_PAY_OPTIONS))],
            'payment_date' => ['required', 'integer', 'min:1', 'max:31'],
            'payment_month' => ['required_if:frequency_pay,annual', 'nullable', 'integer', 'min:1', 'max:12'],
            'deadline_pay' => ['required', 'integer', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(array_keys(Agreement::CURRENCY_OPTIONS))],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'type_sanction' => ['required', Rule::in(array_keys(Agreement::TYPE_SANCTION_OPTIONS))],
            'surcharge_delay' => ['nullable', 'numeric', 'min:0'],
            'amount_delay' => ['nullable', 'numeric', 'min:0'],
            'frequency_sanction' => ['nullable', Rule::in(array_keys(Agreement::FREQUENCY_SANCTION_OPTIONS))],
            'base' => ['nullable', Rule::in(array_keys(Agreement::BASE_OPTIONS))],
            'max_days_unlimited' => ['nullable', 'boolean'],
            'max_days' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function validateMoraPolicy(array $validated): ?string
    {
        return Agreement::validateMoraPolicyInput($validated);
    }

    private function businessFieldValues(array $validated): array
    {
        $isAnnual = ($validated['frequency_pay'] ?? null) === 'annual';

        return array_merge([
            'frequency_pay' => $validated['frequency_pay'],
            'payment_date' => (int) $validated['payment_date'],
            'payment_month' => $isAnnual ? (int) $validated['payment_month'] : null,
            'deadline_pay' => (int) $validated['deadline_pay'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'deposit' => $validated['deposit'] ?? 0,
        ], Agreement::moraPolicyValuesFromInput($validated));
    }
}
