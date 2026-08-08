<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\CreditBalanceMovement;
use App\Services\TenantBalanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditBalanceController extends Controller
{
    public function __construct(
        private readonly TenantBalanceService $tenantBalanceService
    ) {
    }

    public function index(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $filters = $request->only(['agreement_id', 'type']);

        $movements = CreditBalanceMovement::with(['agreement.property', 'roomer.user'])
            ->where('lessor_id', $lessor->id)
            ->when($filters['agreement_id'] ?? null, fn ($query, $agreementId) => $query->where('agreement_id', $agreementId))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $agreementsForFilter = Agreement::with('property')
            ->where('lessor_id', $lessor->id)
            ->orderByDesc('start_at')
            ->get();

        return view('admin.credit-balance.index', [
            'movements' => $movements,
            'agreementsForFilter' => $agreementsForFilter,
            'typeOptions' => CreditBalanceMovement::typeOptions(),
            'sourceOptions' => CreditBalanceMovement::sourceOptions(),
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

        return view('admin.credit-balance.create', [
            'agreements' => $agreements,
            'appliableConcepts' => CreditBalanceMovement::appliableConceptOptions(),
        ]);
    }

    /**
     * Otorga saldo a favor manual (ajuste del arrendador, siempre con motivo obligatorio
     * para auditoría — ver credit_balance_movements.reason).
     */
    public function storeGrant(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $agreement = Agreement::where('lessor_id', $lessor->id)->findOrFail((int) $validated['agreement_id']);

        CreditBalanceMovement::create([
            'agreement_id' => $agreement->id,
            'lessor_id' => $lessor->id,
            'roomer_id' => $agreement->roomer_id,
            'type' => 'generated',
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'source' => 'manual',
            'reason' => $validated['reason'],
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.credit-balance.index')
            ->with('success', 'Saldo a favor otorgado exitosamente.');
    }

    /**
     * Aplica (consume) saldo a favor ya disponible contra un concepto pendiente. No
     * modifica facturas ni comprobantes existentes — solo registra el movimiento en el
     * ledger, igual que un comprobante de pago registra un ingreso sin referenciar una
     * factura puntual (ver TenantBalanceService::breakdownFor()).
     */
    public function storeApply(Request $request)
    {
        $lessor = $request->user()?->lessor;

        if (!$lessor) {
            return redirect()->route('admin.index');
        }

        $validated = $request->validate([
            'agreement_id' => ['required', Rule::exists('agreements', 'id')->where('lessor_id', $lessor->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in(['CRC', 'USD'])],
            'applied_to_concept' => ['required', Rule::in(array_keys(CreditBalanceMovement::APPLIABLE_CONCEPTS))],
        ]);

        $agreement = Agreement::where('lessor_id', $lessor->id)->findOrFail((int) $validated['agreement_id']);

        $available = $this->tenantBalanceService->breakdownFor($agreement, now())['credit_balance']['available'];

        if ((float) $validated['amount'] > $available) {
            return back()
                ->withErrors(['amount' => 'El monto excede el saldo a favor disponible ('.number_format($available, 2).').'])
                ->withInput();
        }

        CreditBalanceMovement::create([
            'agreement_id' => $agreement->id,
            'lessor_id' => $lessor->id,
            'roomer_id' => $agreement->roomer_id,
            'type' => 'applied',
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'source' => 'manual',
            'applied_to_concept' => $validated['applied_to_concept'],
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.credit-balance.index')
            ->with('success', 'Saldo a favor aplicado exitosamente.');
    }

}
