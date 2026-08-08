<?php

namespace App\Http\Controllers;

use App\Models\AdditionalCharge;
use App\Models\Agreement;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdditionalChargeController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    public function index(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $filters = $request->only(['agreement_id', 'status']);

        $charges = AdditionalCharge::with(['agreement.property', 'roomer.user'])
            ->where('lessor_id', $lessor->id)
            ->when($filters['agreement_id'] ?? null, fn ($query, $agreementId) => $query->where('agreement_id', $agreementId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('charge_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $agreementsForFilter = Agreement::with('property')
            ->where('lessor_id', $lessor->id)
            ->orderByDesc('start_at')
            ->get();

        return view('admin.additional-charges.index', [
            'charges' => $charges,
            'agreementsForFilter' => $agreementsForFilter,
            'conceptOptions' => AdditionalCharge::conceptOptions(),
            'statusOptions' => AdditionalCharge::statusOptions(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $agreements = Agreement::with(['roomer.user', 'property'])
            ->where('lessor_id', $lessor->id)
            ->whereIn('status', ['accepted', 'canceling'])
            ->orderByDesc('start_at')
            ->get();

        return view('admin.additional-charges.create', [
            'agreements' => $agreements,
            'conceptOptions' => AdditionalCharge::conceptOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'concept' => ['required', Rule::in(array_keys(AdditionalCharge::CONCEPT_OPTIONS))],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'charge_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $agreement = Agreement::where('lessor_id', $lessor->id)->findOrFail((int) $validated['agreement_id']);

        $charge = AdditionalCharge::create([
            'agreement_id' => $agreement->id,
            'lessor_id' => $lessor->id,
            'roomer_id' => $agreement->roomer_id,
            'concept' => $validated['concept'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'charge_date' => $validated['charge_date'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        $this->notifyChargeCreated($charge->fresh(['agreement.property', 'roomer.user']));

        return redirect()
            ->route('admin.additional-charges.index')
            ->with('success', 'Cargo adicional registrado exitosamente.');
    }

    /**
     * No se borra: se cancela (conserva el registro para auditoría) y deja de sumar en
     * TenantBalanceService (ver AdditionalCharge::scopeActive()).
     */
    public function cancel(Request $request, int $chargeId)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $charge = AdditionalCharge::where('lessor_id', $lessor->id)->findOrFail($chargeId);

        $charge->update([
            'status' => 'cancelled',
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.additional-charges.index')
            ->with('success', 'Cargo adicional cancelado.');
    }

    private function notifyChargeCreated(AdditionalCharge $charge): void
    {
        $roomerUser = $charge->roomer?->user;

        if (!$roomerUser) {
            return;
        }

        $contractLabel = trim(($charge->agreement->contract_number ?? '').' - '.($charge->agreement->property->name ?? ''), ' -');
        $conceptLabel = AdditionalCharge::CONCEPT_OPTIONS[$charge->concept] ?? 'Otro';

        $subject = 'Nuevo cargo a cobrar registrado';
        $body = "Se registró un cargo adicional".($contractLabel !== '' ? " para tu contrato {$contractLabel}." : '.')."\n"
            ."Concepto: {$conceptLabel}\n"
            ."Descripción: {$charge->description}\n"
            ."Monto: {$charge->currency} ".number_format((float) $charge->amount, 2)."\n"
            ."Fecha: ".optional($charge->charge_date)->format('d/m/Y');

        $this->notificationService->emailUsers([$roomerUser], $subject, $body);
    }
}
