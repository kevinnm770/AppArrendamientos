<?php

namespace App\Http\Controllers;

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
    public function update(Request $request)
    {
        $user = Auth::user() ?? abort(403);

        $roomer = $user->roomer;

        // Si no existe
        if (!$roomer) {

        }

        $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'id_number' => [
                'required', 'string', 'max:25',
                Rule::unique('roomers', 'id_number')->ignore($roomer->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $roomer->legal_name = $request->fullname;
        $roomer->id_number  = $request->id_number;
        $roomer->phone      = $request->phone;

        $roomer->save();

        return redirect()
            ->route('tenant.configuration.index')
            ->with('success', 'Datos de arrendatario guardados correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
