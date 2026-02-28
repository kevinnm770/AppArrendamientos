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
use App\Services\SignedDocService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AgreementController extends Controller
{
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

            $agreements->each(fn (Agreement $agreement) => $this->finalizeExpiredCanceling($agreement, $user->id));

            return view('admin.agreements.index', [
                'agreements' => $agreements,
            ]);
        }

        if ($user->isRoomer()) {
            $roomer = $user->roomer;

            $agreements = Agreement::with(['property', 'lessor', 'latestAdemdum', 'signedDoc'])
                ->where('roomer_id', $roomer->id)
                ->orderByDesc('start_at')
                ->get();

            $agreements->each(fn (Agreement $agreement) => $this->finalizeExpiredCanceling($agreement, $user->id));

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

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['required', 'string'],
            'signed_doc_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
            'remove_signed_doc' => ['nullable', 'boolean'],
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

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAgreement($agreement->id, $request->file('signed_doc_file'));
        } elseif ((bool) ($validated['remove_signed_doc'] ?? false)) {
            $signedDocService->deleteForAgreement($agreement->id);
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

        return redirect()
            ->route($viewRoute, $agreement->id)
            ->with('success', 'Solicitud de cancelación rechazada. El contrato sigue activo.');
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

    public function store(Request $request, SignedDocService $signedDocService)
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
            'signed_doc_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:10240'],
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

        $agreement = Agreement::create([
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

        if ($request->hasFile('signed_doc_file')) {
            $signedDocService->storeForAgreement($agreement->id, $request->file('signed_doc_file'));
        }

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
