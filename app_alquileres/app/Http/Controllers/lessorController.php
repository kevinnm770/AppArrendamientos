<?php

namespace App\Http\Controllers;

use App\Services\CostaRicaElectronicInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

class lessorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        } else {
            if ($user->isLessor()) {
                $datarole = $user->lessor;

                return view('admin.index', compact('datarole'));
            }
        }
    }

    public function create()
    {
    }

    public function store(Request $request)
    {
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, CostaRicaElectronicInvoiceService $electronicInvoiceService)
    {
        $user = Auth::user() ?? abort(403);
        $lessor = $user->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.configuration.index')
                ->withErrors(['lessor' => 'No se encontró la información del arrendador para actualizar.']);
        }

        $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'identification_type' => ['required', Rule::in(['fisico', 'juridico', 'dimex', 'nite'])],
            'id_number' => [
                'required',
                'string',
                'max:25',
                Rule::unique('lessors', 'id_number')->ignore($lessor->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'digits:1'],
            'canton' => ['nullable', 'digits:2'],
            'district' => ['nullable', 'digits:2'],
            'barrio' => ['nullable', 'digits:2'],
            'other_signs' => ['nullable', 'string', 'max:255'],
            'economic_activity_code' => ['nullable', 'digits:6'],
            'certificate_file' => ['nullable', 'file', 'max:4096', 'mimes:p12,pfx'],
            'certificate_pin' => ['nullable', 'string', 'max:255'],
            'hacienda_username' => ['nullable', 'string', 'max:120'],
            'hacienda_password' => ['nullable', 'string', 'max:255'],
        ]);

        $certificatePin = $request->filled('certificate_pin')
            ? $request->certificate_pin
            : $lessor->certificate_pin;

        $haciendaPassword = $request->filled('hacienda_password')
            ? $request->hacienda_password
            : $lessor->hacienda_password;

        $lessor->fill([
            'legal_name' => $request->legal_name,
            'commercial_name' => $request->commercial_name,
            'identification_type' => $request->identification_type,
            'id_number' => $request->id_number,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'province' => $request->province,
            'canton' => $request->canton,
            'district' => $request->district,
            'barrio' => $request->barrio,
            'other_signs' => $request->other_signs,
            'economic_activity_code' => $request->economic_activity_code,
            'certificate_pin' => $certificatePin,
            'hacienda_username' => $request->hacienda_username,
            'hacienda_password' => $haciendaPassword,
        ]);
        $lessor->save();

        try {
            $setup = $electronicInvoiceService->syncLessorCrLibreSetup(
                $lessor,
                $request->file('certificate_file'),
                $request->filled('certificate_pin') ? $request->certificate_pin : null,
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.configuration.index')
                ->withInput($request->except(['certificate_pin', 'hacienda_password']))
                ->withErrors(['crlibre' => $exception->getMessage()]);
        }

        $messages = ['Datos de arrendador guardados correctamente.'];

        if ($setup['account_created']) {
            $messages[] = 'La cuenta técnica del arrendador fue registrada automáticamente en CRLibre.';
        }

        if ($setup['certificate_uploaded']) {
            $messages[] = 'El certificado .p12 fue subido a CRLibre y quedó enlazado al arrendador.';
        }

        return redirect()
            ->route('admin.configuration.index')
            ->with('success', implode(' ', $messages));
    }

    public function destroy(string $id)
    {
        //
    }
}
