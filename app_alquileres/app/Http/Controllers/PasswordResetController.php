<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function create()
    {
        return view('auth.passwords.email');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['lessor', 'roomer'])],
        ]);

        $user = User::where('email', $request->email)->where('role', $request->role)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'No encontramos una cuenta con ese correo para el rol seleccionado.',
            ]);
        }

        $recentRequest = PasswordResetRequest::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();

        if ($recentRequest) {
            throw ValidationException::withMessages([
                'email' => 'Ya te enviamos un enlace hace poco. Espera un minuto antes de solicitar otro.',
            ]);
        }

        PasswordResetRequest::where('user_id', $user->id)->whereNull('used_at')->delete();

        $token = Str::random(64);

        PasswordResetRequest::create([
            'user_id' => $user->id,
            'token' => Hash::make($token),
            'expires_at' => now()->addMinutes(60),
        ]);

        $url = route('auth.password.reset', [
            'token' => $token,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        $user->notify(new PasswordResetRequested($url));

        return back()->with('status', 'Te enviamos un enlace para restablecer tu contraseña.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.passwords.reset', [
            'token' => $token,
            'email' => $request->query('email'),
            'role' => $request->query('role'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['lessor', 'roomer'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)->where('role', $request->role)->first();

        $pending = $user
            ? PasswordResetRequest::where('user_id', $user->id)->whereNull('used_at')->latest()->first()
            : null;

        if (!$user || !$pending || $pending->isExpired() || !Hash::check($request->token, $pending->token)) {
            throw ValidationException::withMessages([
                'email' => 'Este enlace de restablecimiento no es válido o expiró.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        $pending->update(['used_at' => now()]);

        event(new PasswordReset($user));

        return redirect()
            ->route('auth.login')
            ->with('success', 'Tu contraseña fue restablecida correctamente. Ya puedes iniciar sesión.');
    }
}
