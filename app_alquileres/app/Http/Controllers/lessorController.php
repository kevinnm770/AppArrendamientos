<?php

namespace App\Http\Controllers;

use App\Models\Lessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class lessorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

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

        $lessor = $user->lessor;

        // Si no existe
        if (!$lessor) {

        }

        $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'id_number' => [
                'required', 'string', 'max:25',
                Rule::unique('lessors', 'id_number')->ignore($lessor->id),
            ],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $lessor->legal_name = $request->fullname;
        $lessor->id_number  = $request->id_number;
        $lessor->phone      = $request->phone;
        $lessor->address    = $request->address;

        $lessor->save();

        return redirect()
            ->route('admin.configuration.index')
            ->with('success', 'Datos de arrendador guardados correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
