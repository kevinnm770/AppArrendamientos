<?php

namespace App\Http\Controllers;

use App\Models\Ademdum;
use App\Models\Agreement;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdemdumController extends Controller
{
    public function index(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'accepted') {
            return redirect()
                ->route('admin.agreements.view', $agreement->id)
                ->withErrors(['agreement' => 'Solo puedes gestionar ademdums en contratos aceptados.']);
        }

        return view('admin.ademdums.index', [
            'agreement' => $agreement,
            'ademdums' => $agreement->ademdums()->latest('created_at')->get(),
            'latestAdemdum' => $agreement->latestAdemdum,
            'defaultData' => $agreement->latestAdemdum ?? $agreement,
            'serviceTypeLabels' => $this->serviceTypeLabels(),
        ]);
    }

    public function store(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'accepted') {
            return back()->withErrors(['agreement' => 'Solo puedes crear ademdums cuando el contrato está en estado "accepted".']);
        }

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['required', 'string'],
        ]);

        $ademdum = Ademdum::create([
            'agreement_id' => $agreement->id,
            'start_at' => Carbon::parse($validated['start_at']),
            'end_at' => !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null,
            'terms' => $validated['terms'],
            'status' => 'sent',
        ]);

        return redirect()
            ->route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum creado correctamente.');
    }

    public function edit(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
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

        return redirect()
            ->route('tenant.ademdums.view', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum aceptado correctamente.');
    }

    public function update(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
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
        ]);

        $ademdum->update([
            'start_at' => Carbon::parse($validated['start_at']),
            'end_at' => !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null,
            'terms' => $validated['terms'],
        ]);

        return redirect()
            ->route('admin.ademdums.edit', ['agreementId' => $agreement->id, 'ademdumId' => $ademdum->id])
            ->with('success', 'Ademdum actualizado correctamente.');
    }

    public function delete(int $agreementId, int $ademdumId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);
        $ademdum = $this->getAgreementAdemdum($agreement, $ademdumId);

        if ($ademdum->status !== 'sent') {
            return back()->withErrors(['ademdum' => 'Este ademdum ya no se puede eliminar porque su estado no es "sent".']);
        }

        $ademdum->delete();

        return redirect()
            ->route('admin.ademdums.index', ['agreementId' => $agreement->id])
            ->with('success', 'Ademdum eliminado correctamente.');
    }

    private function getOwnedAgreement(int $agreementId, Request $request): Agreement
    {
        $lessor = $request->user()?->lessor;

        return Agreement::with(['roomer', 'property', 'ademdums', 'latestAdemdum'])
            ->where('lessor_id', $lessor?->id)
            ->findOrFail($agreementId);
    }

    private function getAccessibleAgreement(int $agreementId, Request $request): Agreement
    {
        $user = $request->user();

        $query = Agreement::with(['roomer', 'property', 'ademdums', 'latestAdemdum']);

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
}
