<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($this->dashboardRouteName($request->user()));
        }

        return view('auth.verify-notice');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()
            ->route($this->dashboardRouteName($request->user()))
            ->with('success', 'Tu correo ha sido verificado correctamente.');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($this->dashboardRouteName($request->user()));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    protected function dashboardRouteName($user): string
    {
        return $user->isLessor() ? 'admin.index' : 'tenant.index';
    }
}
