<?php

namespace App\Http\Controllers;

use App\Services\CostaRicaElectronicInvoiceService;
use App\Services\NationalIdentityLookupService;
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

    public function update(Request $request, CostaRicaElectronicInvoiceService $electronicInvoiceService, NationalIdentityLookupService $identityLookup)
    {
        $user = Auth::user() ?? abort(403);
        $lessor = $user->lessor;

        if (!$lessor) {
            return redirect()
                ->route('admin.configuration.index')
                ->withErrors(['lessor' => 'No se encontró la información del arrendador para actualizar.']);
        }

        $request->validate([
            // legal_name NO se valida ni se toma del request: es de solo lectura y se
            // recarga siempre desde el servicio nacional de identificación (ver abajo),
            // para que ningún usuario pueda escribir un nombre distinto al registrado.
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
            'province' => ['nullable', 'digits:1'],
            'canton' => ['nullable', 'digits:2'],
            'district' => ['nullable', 'digits:2'],
            // Barrio es texto libre en el XSD real (5-50 caracteres), no un código de 2
            // dígitos — Hacienda no publica un catálogo codificado de barrios para FE.
            'barrio' => ['nullable', 'string', 'min:5', 'max:50'],
            // Formato real de Hacienda: 4 dígitos + punto + 1 dígito (ej. "6209.0"), exactamente
            // 6 caracteres — el XSD lo define como string de 6 caracteres, NO como 6 dígitos
            // numéricos (rechazo real al mandarlo sin el punto). No quitar el punto.
            'economic_activity_code' => ['nullable', 'regex:/^\d{4}\.\d$/'],
            'certificate_file' => ['nullable', 'file', 'max:4096', 'extensions:p12,pfx'],
            'certificate_pin' => ['nullable', 'string', 'max:255'],
            'hacienda_username' => ['nullable', 'string', 'max:120'],
            'hacienda_password' => ['nullable', 'string', 'max:255'],
        ], [
            'economic_activity_code.regex' => 'El código de actividad económica debe tener el formato exacto de ATV: 4 dígitos, un punto y 1 dígito (ej. "6209.0").',
            'certificate_file.extensions' => 'El certificado debe ser un archivo .p12 o .pfx.',
        ]);

        // El nombre legal siempre se recarga desde el servicio nacional de identificación
        // a partir del tipo y número de identificación — nunca se confía en el valor
        // enviado por el formulario, así ningún usuario puede cambiarlo manualmente.
        $lookup = $identityLookup->lookup($request->id_number);

        if ($lookup['found']) {
            $legalName = $lookup['name'];
            // El tipo detectado por el servicio (a partir del propio número) manda sobre
            // el que haya seleccionado el usuario, para no guardar una combinación
            // tipo/número inconsistente con el catálogo de Hacienda.
            $identificationType = $lookup['identification_type'] ?: $request->identification_type;
        } elseif ($request->id_number === $lessor->id_number) {
            // Mismo número ya guardado: probablemente una falla transitoria del servicio de
            // identificación, no se borra el nombre ya verificado anteriormente.
            $legalName = $lessor->legal_name;
            $identificationType = $request->identification_type;
        } else {
            // Número nuevo/cambiado que no se pudo verificar: no se arrastra el nombre del
            // número anterior (pertenece a otra identificación), se deja en blanco.
            $legalName = '';
            $identificationType = $request->identification_type;
        }

        $certificatePin = $request->filled('certificate_pin')
            ? $request->certificate_pin
            : $lessor->certificate_pin;

        $haciendaPassword = $request->filled('hacienda_password')
            ? $request->hacienda_password
            : $lessor->hacienda_password;

        $lessor->fill([
            'legal_name' => $legalName,
            'commercial_name' => $request->commercial_name,
            'identification_type' => $identificationType,
            'id_number' => $request->id_number,
            'phone' => $request->phone,
            'email' => $request->email,
            'province' => $request->province,
            'canton' => $request->canton,
            'district' => $request->district,
            'barrio' => $request->barrio,
            'economic_activity_code' => $request->economic_activity_code,
            'certificate_pin' => $certificatePin,
            'hacienda_username' => $request->hacienda_username,
            'hacienda_password' => $haciendaPassword,
        ]);
        $lessor->save();

        try {
            $setup = $electronicInvoiceService->storeLessorCertificate(
                $lessor,
                $request->file('certificate_file'),
                $request->filled('certificate_pin') ? $request->certificate_pin : null,
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.configuration.index')
                ->withInput($request->except(['certificate_pin', 'hacienda_password']))
                ->withErrors(['certificate' => $exception->getMessage()]);
        }

        $messages = ['Datos de arrendador guardados correctamente.'];

        if ($setup['certificate_uploaded']) {
            $messages[] = 'El certificado .p12 fue guardado y quedó enlazado al arrendador para firmar comprobantes.';
        }

        return redirect()
            ->route('admin.configuration.index')
            ->with('success', implode(' ', $messages));
    }

    public function destroy(string $id)
    {
        //
    }

    /**
     * Consulta el nombre/razón social y las actividades económicas registradas de una
     * identificación (física, jurídica, DIMEX o NITE) para autocompletar el formulario.
     * Usa apis.gometa.org/cedulas — un servicio nacional independiente, no la API directa
     * de Hacienda — llamado desde el servidor (no el navegador) para evitar CORS.
     */
    public function lookupIdentification(Request $request, NationalIdentityLookupService $identityLookup)
    {
        $identification = (string) $request->query('identification', '');
        $result = $identityLookup->lookup($identification);

        if (!$result['found']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }
}
