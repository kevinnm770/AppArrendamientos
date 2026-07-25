<?php

namespace App\Http\Controllers;

use App\Models\EmailChangeRequest;
use App\Models\PasswordChangeRequest;
use App\Notifications\EmailChangeAuthorizationRequested;
use App\Notifications\EmailChangeVerificationRequested;
use App\Notifications\PasswordChangeRequested;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class AccountSecurityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->only(['requestPasswordChange', 'requestEmailChange']);
        $this->middleware('signed')->only(['confirmPasswordChange', 'confirmEmailCurrent', 'confirmEmailNew', 'cancelEmailChange']);
        $this->middleware('throttle:10,1');
    }

    public function requestPasswordChange(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        PasswordChangeRequest::where('user_id', $user->id)->whereNull('used_at')->delete();

        $expiresAt = now()->addMinutes(60);
        $pending = PasswordChangeRequest::create([
            'user_id' => $user->id,
            'new_password' => Hash::make($request->password),
            'expires_at' => $expiresAt,
        ]);

        $url = URL::temporarySignedRoute('account.password-change.confirm', $expiresAt, ['id' => $pending->id]);

        $user->notify(new PasswordChangeRequested($url));

        return back()->with('success', 'Revisa tu correo para confirmar el cambio de contraseña.');
    }

    public function confirmPasswordChange(Request $request, int $id)
    {
        $pending = PasswordChangeRequest::find($id);

        if (!$pending || $pending->isUsed() || $pending->isExpired()) {
            return $this->result(
                'Enlace no válido',
                'Este enlace de confirmación ya no es válido o expiró. Solicita el cambio de nuevo desde tu panel.'
            );
        }

        $user = $pending->user;
        $user->forceFill(['password' => $pending->new_password])->save();
        $pending->update(['used_at' => now()]);

        if (auth()->check() && auth()->id() === $user->id) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->result(
            'Contraseña actualizada',
            'Tu contraseña se cambió correctamente. Inicia sesión con tu nueva contraseña.'
        );
    }

    public function requestEmailChange(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'new_email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('role', $user->role)),
            ],
        ]);

        if (strcasecmp($request->new_email, $user->email) === 0) {
            return back()->withErrors(['new_email' => 'Ese ya es tu correo actual.']);
        }

        EmailChangeRequest::where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->where(function ($query) {
                $query->whereNull('current_confirmed_at')->orWhereNull('new_confirmed_at');
            })
            ->delete();

        $expiresAt = now()->addMinutes(60);
        $pending = EmailChangeRequest::create([
            'user_id' => $user->id,
            'new_email' => $request->new_email,
            'expires_at' => $expiresAt,
        ]);

        $authorizeUrl = URL::temporarySignedRoute('account.email-change.confirm-current', $expiresAt, ['id' => $pending->id]);
        $cancelUrl = URL::temporarySignedRoute('account.email-change.cancel', $expiresAt, ['id' => $pending->id]);
        $verifyUrl = URL::temporarySignedRoute('account.email-change.confirm-new', $expiresAt, ['id' => $pending->id]);

        $user->notify(new EmailChangeAuthorizationRequested($request->new_email, $authorizeUrl, $cancelUrl));
        Notification::route('mail', $request->new_email)
            ->notify(new EmailChangeVerificationRequested($user->name, $verifyUrl));

        return back()->with('success', 'Enviamos una autorización a tu correo actual y una verificación al correo nuevo. El cambio se aplicará cuando confirmes ambos.');
    }

    public function confirmEmailCurrent(Request $request, int $id)
    {
        return $this->confirmEmailSide($id, 'current_confirmed_at', 'correo actual');
    }

    public function confirmEmailNew(Request $request, int $id)
    {
        return $this->confirmEmailSide($id, 'new_confirmed_at', 'correo nuevo');
    }

    public function cancelEmailChange(Request $request, int $id)
    {
        $pending = EmailChangeRequest::find($id);

        if ($pending && !$pending->isCancelled()) {
            $pending->update(['cancelled_at' => now()]);
        }

        return $this->result(
            'Solicitud cancelada',
            'La solicitud de cambio de correo fue cancelada. Tu correo actual no cambió.'
        );
    }

    protected function confirmEmailSide(int $id, string $column, string $label)
    {
        $pending = EmailChangeRequest::find($id);

        if (!$pending || $pending->isCancelled() || $pending->isExpired()) {
            return $this->result(
                'Enlace no válido',
                'Esta solicitud de cambio de correo ya no es válida (expiró o fue cancelada).'
            );
        }

        if (!$pending->{$column}) {
            $pending->update([$column => now()]);
            $pending->refresh();
        }

        if ($pending->isFullyConfirmed()) {
            $user = $pending->user;
            $user->forceFill([
                'email' => $pending->new_email,
                'email_verified_at' => now(),
            ])->save();
            $pending->delete();

            return $this->result(
                'Correo actualizado',
                "El correo de la cuenta se actualizó correctamente a {$user->email}."
            );
        }

        return $this->result(
            'Confirmación recibida',
            "Recibimos tu confirmación del {$label}. Falta que se confirme el otro correo para aplicar el cambio."
        );
    }

    protected function result(string $title, string $message)
    {
        return view('account.action-result', [
            'title' => $title,
            'message' => $message,
        ]);
    }
}
