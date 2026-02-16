<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct()
    {
        // Registro solo para invitados
        $this->middleware('guest');
    }

    public function index()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        // Validar datos
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Intentar autenticar
        if (Auth::attempt($credentials)) {
            // Regenerar sesión (seguridad)
            $request->session()->regenerate();

            // Redirigir al dashboard
            $user = Auth::user();

            if($user->isLessor()){
                return redirect()->route('admin.index');
            }
            if($user->isRoomer()){
                return redirect()->route('tenant.index');
            }


        }

        // Si falla, volver con error
        return back()->withErrors([
            'email' => 'Las credenciales no son correctas.',
        ])->onlyInput('email');
    }
}
