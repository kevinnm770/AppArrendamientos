<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\Roomer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
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

    private function hasDateCollision(string $column, int $id, Carbon $startAt, ?Carbon $endAt): bool
    {
        $query = Agreement::query()
            ->where($column, $id)
            ->whereNotIn('status', ['cancelled', 'finished']);

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
}
