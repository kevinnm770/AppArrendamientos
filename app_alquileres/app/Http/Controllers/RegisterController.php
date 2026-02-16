<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Lessor;
use App\Models\Roomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct()
    {
        // Registro solo para invitados
        $this->middleware('guest');
    }

    public function index()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $request->validate([
            'role' => ['required', Rule::in(['lessor', 'roomer'])],

            // User
            'username' => ['required', 'string', 'max:15', Rule::unique('users', 'name')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // Perfil (para lessor o roomer)
            'fullname' => ['required', 'string', 'max:255'],
            'id_number' => ['required', 'string', 'max:25', 'regex:/^[0-9]+$/'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
        ]);

        return DB::transaction(function () use ($request) {

            // 1) Crear user
            $user = User::create([
                'name' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // 2) Crear SOLO 1 perfil según role
            $role = $request->role;

            if ($role === 'lessor') {
                // Evitar duplicidad por id_number dentro de lessors
                $request->validate([
                    'id_number' => [Rule::unique('lessors', 'id_number')],
                ]);

                // Extra: por si acaso, bloquear que exista roomer (no debería en registro)
                if (Roomer::where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages([
                        'role' => 'Este usuario ya tiene un perfil de Inquilino y no puede tener ambos.',
                    ]);
                }

                Lessor::create([
                    'user_id' => $user->id,
                    'legal_name' => $request->fullname,
                    'id_number' => $request->id_number,
                    'phone' => $request->phone,
                ]);
            }

            if ($role === 'roomer') {
                $request->validate([
                    'id_number' => [Rule::unique('roomers', 'id_number')],
                ]);

                if (Lessor::where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages([
                        'role' => 'Este usuario ya tiene un perfil de Arrendador y no puede tener ambos.',
                    ]);
                }

                Roomer::create([
                    'user_id' => $user->id,
                    'legal_name' => $request->fullname,
                    'id_number' => $request->id_number,
                    'phone' => $request->phone,
                ]);
            }

            // 3) Login automático (opcional)
            Auth::login($user);

            if($user->isLessor()){
                return redirect()
                    ->route('admin.index')
                    ->with('success', 'Registro completado correctamente.');
            }
            if($user->isRoomer()){
                return redirect()
                    ->route('tenant.index')
                    ->with('success', 'Registro completado correctamente.');
            }


        });
    }
}
