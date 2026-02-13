<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage as FacadesStorage;
use Illuminate\Validation\Rule;

class UserController extends Controller
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
            if($user->isLessor()){
                $datarole=$user->lessor;
                $datarole->role="Arrendador(a)";
            }
            if($user->isRoomer()){
                $datarole=$user->roomer;
                $datarole->role="Inquilino(a)";
            }
        }

        return view('configuration.index',compact('user','datarole'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        return $request;
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
        $user_datos = Auth::user(); // el usuario logueado
        if (!$user_datos) {
            abort(403);
        }

        $rules = [
            'name' => [
                'required',
                'string',
                'max:15',
                Rule::unique('users', 'name')->ignore($user_datos->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user_datos->id),
            ],
            'profile_photo_path' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];

        $request->validate($rules);

        $user_datos->name = $request->name;
        $user_datos->email = $request->email;

        if ($request->hasFile('profile_photo_path')) {
            if ($user_datos->profile_photo_path && FacadesStorage::disk('public')->exists($user_datos->profile_photo_path)) {
                FacadesStorage::disk('public')->delete($user_datos->profile_photo_path);
            }

            $user_datos->profile_photo_path = $request->file('profile_photo_path')
                ->store('profiles_images', 'public');
        }

        $user_datos->save();

        if($user_datos->isLessor()){
            return redirect()
                ->route('admin.configuration.index')
                ->with('success', 'Datos guardados correctamente');
        }
        if($user_datos->isRoomer()){
            return redirect()
                ->route('tenant.configuration.index')
                ->with('success', 'Datos guardados correctamente');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
