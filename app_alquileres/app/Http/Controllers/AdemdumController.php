<?php

namespace App\Http\Controllers;

use App\Models\Ademdum;
use App\Models\Agreement;
use App\Services\SignedDocService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdemdumController extends Controller
{
    private const CANCELING_RESPONSE_DEADLINE_HOURS = 24;

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
            'defaultData' => $agreement->AdemdumUpdatePeriod ?? $agreement,
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

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['required', 'string'],
            'change_agreement_period' => ['nullable', 'boolean'],
            'signed_doc_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
        ]);

        $changeAgreementPeriod = (bool) ($validated['change_agreement_period'] ?? false);

        if ($changeAgreementPeriod && empty($validated['end_at'])) {
            return back()
                ->withErrors(['end_at' => 'Debes indicar una fecha de fin para cambiar la vigencia del contrato.'])
                ->withInput();
        }

        if ($changeAgreementPeriod && $this->hasAcceptedAdemdumWithAgreementPeriodUpdate($agreement->id)) {
            return back()
                ->withErrors(['change_agreement_period' => 'No puedes cambiar el periodo de vigencia porque ya existe otro ademdum aceptado con actualización de vigencia.'])
                ->withInput();
        }

        $ademdum = Ademdum::create([
            'agreement_id' => $agreement->id,
            'start_at' => Carbon::parse($validated['start_at']),
            'end_at' => !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null,
            'update_start_date_agreement' => $changeAgreementPeriod ? Carbon::parse($validated['start_at']) : null,
            'update_end_date_agreement' => $changeAgreementPeriod && !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null,
            'terms' => $validated['terms'],
            'status' => 'sent',
        ]);

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAdemdum($ademdum->id, $request->file('signed_doc_file'));
        }

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


        if (
            ($ademdum->update_start_date_agreement || $ademdum->update_end_date_agreement)
            && (
                !$ademdum->update_start_date_agreement
                || !$ademdum->update_end_date_agreement
                || !$ademdum->update_start_date_agreement->equalTo($ademdum->start_at)
                || !$ademdum->update_end_date_agreement->equalTo($ademdum->end_at)
            )
        ) {
            return redirect()
                ->route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->withErrors(['ademdum' => 'Las fechas de actualización de vigencia deben coincidir exactamente con las fechas de inicio y fin del ademdum.']);
        }

        $ademdum->update([
            'status' => 'accepted',
            'tenant_confirmed_at' => now(),
            'locked_at' => now(),
        ]);

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

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['required', 'string'],
            'change_agreement_period' => ['nullable', 'boolean'],
            'signed_doc_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
        ]);

        $changeAgreementPeriod = (bool) ($validated['change_agreement_period'] ?? false);

        if ($changeAgreementPeriod && empty($validated['end_at'])) {
            return back()
                ->withErrors(['end_at' => 'Debes indicar una fecha de fin para cambiar la vigencia del contrato.'])
                ->withInput();
        }

        if (
            $changeAgreementPeriod
            && $this->hasAcceptedAdemdumWithAgreementPeriodUpdate($agreement->id, $ademdum->id)
        ) {
            return back()
                ->withErrors(['change_agreement_period' => 'No puedes cambiar el periodo de vigencia porque ya existe otro ademdum aceptado con actualización de vigencia.'])
                ->withInput();
        }

        $ademdum->update([
            'start_at' => Carbon::parse($validated['start_at']),
            'end_at' => !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null,
            'update_start_date_agreement' => $changeAgreementPeriod ? Carbon::parse($validated['start_at']) : null,
            'update_end_date_agreement' => $changeAgreementPeriod && !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null,
            'terms' => $validated['terms'],
        ]);

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAdemdum($ademdum->id, $request->file('signed_doc_file'));
        }

        return redirect()
            ->route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum actualizado correctamente.');
    }

    public function delete(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $this->syncExpiredAcceptedAdemdums($agreement);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if ($ademdum->status !== 'sent') {
            return back()->withErrors(['ademdum' => 'Este ademdum ya no se puede eliminar porque su estado no es "sent".']);
        }

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

            return redirect()
                ->route($viewRoute, ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
                ->with('success', 'Desestimación del adendum aceptada correctamente.');
        }

        $ademdum->update([
            'status' => 'accepted',
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);

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
            'lodging' => 'Hospedaje',
            'event' => 'Evento',
        ];
    }

    private function hasAcceptedAdemdumWithAgreementPeriodUpdate(int $agreementId, ?int $ignoreAdemdumId = null): bool
    {
        $query = Ademdum::query()
            ->where('agreement_id', $agreementId)
            ->whereIn('status', ['accepted','canceling'])
            ->whereNotNull('update_start_date_agreement')
            ->whereNotNull('update_end_date_agreement');

        if ($ignoreAdemdumId) {
            $query->whereKeyNot($ignoreAdemdumId);
        }

        return $query->exists();
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
            ->each(function (Ademdum $ademdum): void {
                $ademdum->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $ademdum->end_at,
                    'cancelled_by' => 'Expired period',
                ]);
            });

        Ademdum::query()
            ->where('agreement_id', $agreement->id)
            ->where('status', 'canceling')
            ->whereNotNull('cancelled_at')
            ->where('cancelled_at', '<=', now()->subHours($cancelingDeadlineHours))
            ->get()
            ->each(function (Ademdum $ademdum) use ($cancelingDeadlineHours): void {
                $deadline = $ademdum->cancelled_at?->copy()->addHours($cancelingDeadlineHours) ?? now();

                $ademdum->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $deadline,
                ]);
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
