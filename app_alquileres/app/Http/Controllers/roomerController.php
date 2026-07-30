<?php

namespace App\Http\Controllers;

use App\Services\NationalIdentityLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class roomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user){
            abort(403);
        }else{
            if($user->isRoomer()){
                $datarole=$user->roomer;
                return view('tenant.index', compact('datarole'));
            }
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, NationalIdentityLookupService $identityLookup)
    {
        $user = Auth::user() ?? abort(403);

        $roomer = $user->roomer;

        if (!$roomer) {
            return redirect()
                ->route('tenant.configuration.index')
                ->withErrors(['roomer' => 'No se encontró la información del inquilino para actualizar.']);
        }

        $request->validate([
            // legal_name NO se valida ni se toma del request: es de solo lectura y se
            // recarga siempre desde el servicio nacional de identificación (ver abajo),
            // para que ningún usuario pueda escribir un nombre distinto al registrado.
            'identification_type' => ['required', Rule::in(['fisico', 'juridico', 'dimex', 'nite'])],
            'id_number' => [
                'required', 'string', 'max:25',
                Rule::unique('roomers', 'id_number')->ignore($roomer->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'province' => ['nullable', 'digits:1'],
            'canton' => ['nullable', 'digits:2'],
            'district' => ['nullable', 'digits:2'],
            // Barrio es texto libre en el XSD real (5-50 caracteres), no un código de 2
            // dígitos — Hacienda no publica un catálogo codificado de barrios para FE.
            'barrio' => ['nullable', 'string', 'min:5', 'max:50'],
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
        } elseif ($request->id_number === $roomer->id_number) {
            // Mismo número ya guardado: probablemente una falla transitoria del servicio de
            // identificación, no se borra el nombre ya verificado anteriormente.
            $legalName = $roomer->legal_name;
            $identificationType = $request->identification_type;
        } else {
            // Número nuevo/cambiado que no se pudo verificar: no se arrastra el nombre del
            // número anterior (pertenece a otra identificación), se deja en blanco.
            $legalName = '';
            $identificationType = $request->identification_type;
        }

        $roomer->legal_name = $legalName;
        $roomer->identification_type = $identificationType;
        $roomer->id_number  = $request->id_number;
        $roomer->phone      = $request->phone;
        $roomer->province   = $request->province;
        $roomer->canton     = $request->canton;
        $roomer->district   = $request->district;
        $roomer->barrio     = $request->barrio;

        $roomer->save();

        return redirect()
            ->route('tenant.configuration.index')
            ->with('success', 'Datos de arrendatario guardados correctamente');
    }

    /**
     * Consulta el nombre registrado de una identificación (física, jurídica, DIMEX o
     * NITE) para autocompletar el formulario. Usa apis.gometa.org/cedulas — un servicio
     * nacional independiente, no la API directa de Hacienda.
     */
    public function lookupIdentification(Request $request, NationalIdentityLookupService $identityLookup)
    {
        $result = $identityLookup->lookup((string) $request->query('identification', ''));

        if (!$result['found']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
