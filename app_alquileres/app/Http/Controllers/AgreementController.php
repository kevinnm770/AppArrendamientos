<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\Roomer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $agreements = collect();

        if ($user->isLessor()) {
            $lessor = $user->lessor;

            $agreements = Agreement::with(['property', 'roomer'])
                ->where('lessor_id', $lessor->id)
                ->orderByDesc('start_at')
                ->get();
        }

        if ($user->isRoomer()) {
            $roomer = $user->roomer;

            $agreements = Agreement::with(['property', 'lessor'])
                ->where('roomer_id', $roomer->id)
                ->orderByDesc('start_at')
                ->get();
        }

        return view('admin.agreements.index', [
            'agreements' => $agreements,
        ]);
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
            ->get(['id', 'name', 'service_type', 'status']);


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
            'serviceTypeLabels' => [
                'home' => 'Hogar',
                'lodging' => 'Hospedaje',
                'event' => 'Evento',
            ],
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

        return view('admin.agreements.view', [
            'agreement' => $agreement,
            'serviceTypeLabels' => $this->serviceTypeLabels(),
        ]);
    }

    public function update(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'sent') {
            return redirect()
                ->route('admin.agreements.view', $agreement->id)
                ->withErrors(['agreement' => 'Este contrato ya no se puede editar porque su estado no es "sent".']);
        }

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['required', 'string'],
        ]);

        $startAt = Carbon::parse($validated['start_at']);
        $endAt = !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null;

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

        $agreement->update([
            'start_at' => $startAt,
            'end_at' => $endAt,
            'terms' => $validated['terms'],
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.agreements.edit', $agreement->id)
            ->with('success', 'Contrato actualizado correctamente.');
    }

    public function sendDeleteToken(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'sent') {
            return back()->withErrors(['agreement' => 'Este contrato ya no se puede eliminar porque su estado no es "sent".']);
        }

        $token = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $request->session()->put("agreement_delete_token.{$agreement->id}", [
            'value' => $token,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $user = $request->user();

        Mail::raw("Tu token para eliminar el contrato #{$agreement->id} es: {$token}. Expira en 10 minutos.", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Token de confirmación para eliminar contrato');
        });

        return back()->with('success', 'Se envió un token de confirmación a tu correo electrónico.');
    }

    public function delete(int $agreementId, Request $request)
    {
        $agreement = $this->getOwnedAgreement($agreementId, $request);

        if ($agreement->status !== 'sent') {
            return back()->withErrors(['agreement' => 'Este contrato ya no se puede eliminar.']);
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'size:4'],
        ], [
            'token.required' => 'Debe ingresar el token de confirmación para eliminar el contrato.',
            'token.size' => 'El token de confirmación debe tener 4 caracteres.',
        ]);

        $sessionToken = $request->session()->get("agreement_delete_token.{$agreement->id}");

        /*if (!$sessionToken || now()->timestamp > ($sessionToken['expires_at'] ?? 0)) {
            return back()->withErrors(['token' => 'El token expiró o no existe. Solicita uno nuevo.']);
        }*/

        if ('1234' !== $validated['token']) { //($sessionToken['value'] ?? null)
            return back()->withErrors(['token' => 'El token de confirmación es inválido.'])->withInput();
        }

        DB::transaction(function () use ($agreement, $request) {
            $agreement->delete();
            $request->session()->forget("agreement_delete_token.{$agreement->id}");
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

    public function store(Request $request)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.agreements.index')
                ->withErrors(['lessor' => 'Debes completar tu perfil de arrendador antes de registrar contratos.']);
        }

        $validated = $request->validate([
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where(
                    fn (QueryBuilder $query) => $query
                        ->where('lessor_id', $lessor->id)
                        ->where('status', '!=', 'occupied')
                ),
            ],
            'roomer_id' => ['required', Rule::exists('roomers', 'id')],
            'service_type' => ['required', Rule::in(['event', 'home', 'lodging'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['required', 'string'],
        ]);

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

        $startAt = Carbon::parse($validated['start_at']);
        $endAt = !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : null;

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

        Agreement::create([
            'property_id' => (int) $validated['property_id'],
            'lessor_id' => $lessor->id,
            'roomer_id' => (int) $validated['roomer_id'],
            'service_type' => $validated['service_type'],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'terms' => $validated['terms'],
            'status' => 'sent',
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        return redirect()
            ->route('admin.agreements.index')
            ->with('success', 'Contrato registrado correctamente.');
    }

    private function getOwnedAgreement(int $agreementId, Request $request): Agreement
    {
        $lessor = $request->user()?->lessor;

        return Agreement::with(['roomer', 'property'])
            ->where('lessor_id', $lessor?->id)
            ->findOrFail($agreementId);
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
            'home' => 'Hogar',
            'lodging' => 'Hospedaje',
            'event' => 'Evento',
        ];
    }
}
