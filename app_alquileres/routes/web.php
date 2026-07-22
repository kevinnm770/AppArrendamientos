<?php

use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AdemdumController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\lessorController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PublicPropertyController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\roomerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/rentals', [PublicPropertyController::class, 'index'])->name('public.properties.index');

//Auth::routes(['register' => false]);

Route::prefix('auth')->name('auth.')->middleware('guest')->group(function () {
    // Login
    Route::get('/login', [LoginController::class, 'index'])->name('login');

    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.authenticate');

    // Registro (Sign in)
    Route::get('/register', [RegisterController::class, 'index'])->name('register');

    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    // Recuperación de contraseña
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

// Seguridad de la cuenta: cambio de contraseña y de correo con doble confirmación
Route::post('/account/password-change/request', [AccountSecurityController::class, 'requestPasswordChange'])->name('account.password-change.request');
Route::get('/account/password-change/{id}/confirm', [AccountSecurityController::class, 'confirmPasswordChange'])->name('account.password-change.confirm');

Route::post('/account/email-change/request', [AccountSecurityController::class, 'requestEmailChange'])->name('account.email-change.request');
Route::get('/account/email-change/{id}/confirm-current', [AccountSecurityController::class, 'confirmEmailCurrent'])->name('account.email-change.confirm-current');
Route::get('/account/email-change/{id}/confirm-new', [AccountSecurityController::class, 'confirmEmailNew'])->name('account.email-change.confirm-new');
Route::get('/account/email-change/{id}/cancel', [AccountSecurityController::class, 'cancelEmailChange'])->name('account.email-change.cancel');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('auth.login');
})->middleware('auth')->name('logout');

// Verificación de correo electrónico
Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->name('verification.send');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'lessor'])->group(function () {

    // Inicio
    Route::get('/', [lessorController::class, 'index'])->name('index');

    // Configuraciones de cuenta
    Route::prefix('configuration')->name('configuration.')->middleware('auth')->group(function () {
        //Ventana de configuracion
        Route::get('/', [UserController::class, 'index'])->name('index');

        //Datos del usuario
        Route::patch('/user', [UserController::class, 'update'])->name('user.update');

        //Datos del lessor
        Route::patch('/lessor', [lessorController::class, 'update'])->name('lessor.update');
    });

    // Admin de propiedades
    Route::prefix('properties')->name('properties.')->middleware('auth')->group(function () {
        //Ventana de propiedades
        Route::get('/', [PropertyController::class, 'index'])->name('index');

        //Registro de propiedad
        Route::get('/register', [PropertyController::class, 'register'])->name('register');

        Route::post('/register', [PropertyController::class, 'store'])->name('register.store');

        //Editar propiedad
        Route::get('/edit/{id_prop}', [PropertyController::class, 'edit'])->name('edit');

        Route::patch('/edit/{id_prop}', [PropertyController::class, 'update'])->name('edit.update');

        //Eliminar propiedad
        Route::patch('/edit/delete/{id_prop}', [PropertyController::class, 'delete'])->name('edit.delete');
    });

    // Contratos
    Route::prefix('agreements')->name('agreements.')->middleware('auth')->group(function () {
        //Ventana de contratos
        Route::get('/', [AgreementController::class, 'index'])->name('index');

        //Registro de contrato
        Route::get('/register', [AgreementController::class, 'register'])->name('register');
        Route::get('/roomer-by-id-number/{idNumber}', [AgreementController::class, 'roomerByIdNumber'])
            ->name('roomer-by-id-number');

        Route::post('/register', [AgreementController::class, 'store'])->name('register.store');
        Route::get('/{agreementId}/signed-doc/download', [AgreementController::class, 'downloadSignedDoc'])->name('signed-doc.download');
        Route::get('/{agreementId}/edit', [AgreementController::class, 'edit'])->name('edit');
        Route::patch('/{agreementId}/edit', [AgreementController::class, 'update'])->name('edit.update');
        Route::get('/{agreementId}/view', [AgreementController::class, 'view'])->name('view');
        Route::patch('/{agreementId}/canceling', [AgreementController::class, 'canceling'])->name('canceling');
        Route::patch('/{agreementId}/canceling-response', [AgreementController::class, 'cancelingResponse'])->name('canceling-response');

        Route::post('/{agreementId}/delete-token', [AgreementController::class, 'sendDeleteToken'])->name('delete-token');
        Route::delete('/{agreementId}', [AgreementController::class, 'delete'])->name('delete');
    });

    // Facturas
    Route::prefix('invoices')->name('invoices.')->middleware('auth')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::post('/{invoiceId}/electronic/send', [InvoiceController::class, 'sendElectronic'])->name('electronic.send');
        Route::post('/{invoiceId}/electronic/retry', [InvoiceController::class, 'retryElectronic'])->name('electronic.retry');
        Route::post('/{invoiceId}/electronic/check-status', [InvoiceController::class, 'checkElectronicStatus'])->name('electronic.check-status');
    });

    Route::prefix('agreements/{agreementId}/ademdums')->name('ademdums.')->middleware('auth')->group(function () {
        Route::get('/', [AdemdumController::class, 'index'])->name('index');
        Route::post('/', [AdemdumController::class, 'store'])->name('store');

        Route::get('/{ademdumId}/edit', [AdemdumController::class, 'edit'])->name('edit');
        Route::patch('/{ademdumId}/edit', [AdemdumController::class, 'update'])->name('edit.update');
        Route::get('/{ademdumId}/view', [AdemdumController::class, 'view'])->name('view');
        Route::get('/{ademdumId}/signed-doc/download', [AdemdumController::class, 'downloadSignedDoc'])->name('signed-doc.download');
        Route::patch('/{ademdumId}/canceling', [AdemdumController::class, 'canceling'])->name('canceling');
        Route::patch('/{ademdumId}/canceling-response', [AdemdumController::class, 'cancelingResponse'])->name('canceling-response');
        Route::delete('/{ademdumId}', [AdemdumController::class, 'delete'])->name('delete');
    });

    //Notificaciones
    Route::prefix('agreements/notifications')->name('notifications.')->middleware('auth')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/push-feed', [NotificationController::class, 'pushFeed'])->name('push-feed');
        Route::get('/{notificationId}/view', [NotificationController::class, 'view'])->name('view');
    });
});

Route::prefix('tenant')->name('tenant.')->middleware(['auth', 'verified', 'roomer'])->group(function () {

    // Inicio
    Route::get('/', [roomerController::class, 'index'])->name('index');

    // Configuraciones de cuenta
    Route::prefix('configuration')->name('configuration.')->middleware('auth')->group(function () {
        //Ventana de configuracion
        Route::get('/', [UserController::class, 'index'])->name('index');

        //Datos del usuario
        Route::patch('/user', [UserController::class, 'update'])->name('user.update');

        //Datos del roomer
        Route::patch('/roomer', [roomerController::class, 'update'])->name('roomer.update');
    });

    // Contratos
    Route::prefix('agreements')->name('agreements.')->middleware('auth')->group(function () {
        //Ventana de contratos
        Route::get('/', [AgreementController::class, 'index'])->name('index');
        Route::get('/{agreementId}/signed-doc/download', [AgreementController::class, 'downloadSignedDoc'])->name('signed-doc.download');
        Route::get('/{agreementId}/view', [AgreementController::class, 'view'])->name('view');
        Route::patch('/{agreementId}/accept', [AgreementController::class, 'accept'])->name('accept');
        Route::patch('/{agreementId}/canceling-response', [AgreementController::class, 'cancelingResponse'])->name('canceling-response');
    });


    // Facturas
    Route::prefix('invoices')->name('invoices.')->middleware('auth')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
    });

    Route::prefix('agreements/{agreementId}/ademdums')->name('ademdums.')->middleware('auth')->group(function () {
        Route::get('/{ademdumId}/view', [AdemdumController::class, 'view'])->name('view');
        Route::get('/{ademdumId}/signed-doc/download', [AdemdumController::class, 'downloadSignedDoc'])->name('signed-doc.download');
        Route::patch('/{ademdumId}/accept', [AdemdumController::class, 'accept'])->name('accept');
        Route::patch('/{ademdumId}/canceling-response', [AdemdumController::class, 'cancelingResponse'])->name('canceling-response');
    });

    //Notificaciones
    Route::prefix('agreements/notifications')->name('notifications.')->middleware('auth')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/push-feed', [NotificationController::class, 'pushFeed'])->name('push-feed');
        Route::get('/{notificationId}/view', [NotificationController::class, 'view'])->name('view');
    });
});
