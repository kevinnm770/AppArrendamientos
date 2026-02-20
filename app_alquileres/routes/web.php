<?php

use App\Http\Controllers\AdemdumController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\lessorController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\roomerController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

//Auth::routes(['register' => false]);

Route::prefix('auth')->name('auth.')->middleware('guest')->group(function () {
    // Login
    Route::get('/login', [LoginController::class, 'index'])->name('login');

    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.authenticate');

    // Registro (Sign in)
    Route::get('/register', [RegisterController::class, 'index'])->name('register');

    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('auth.login');
})->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'lessor'])->group(function () {

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

        Route::get('/{agreementId}/edit', [AgreementController::class, 'edit'])->name('edit');
        Route::patch('/{agreementId}/edit', [AgreementController::class, 'update'])->name('edit.update');
        Route::get('/{agreementId}/view', [AgreementController::class, 'view'])->name('view');

        Route::post('/{agreementId}/delete-token', [AgreementController::class, 'sendDeleteToken'])->name('delete-token');
        Route::delete('/{agreementId}', [AgreementController::class, 'delete'])->name('delete');
    });


    Route::prefix('agreements/{agreementId}/ademdums')->name('ademdums.')->middleware('auth')->group(function () {
        Route::get('/', [AdemdumController::class, 'index'])->name('index');
        Route::post('/', [AdemdumController::class, 'store'])->name('store');

        Route::get('/{ademdumId}/edit', [AdemdumController::class, 'edit'])->name('edit');
        Route::patch('/{ademdumId}/edit', [AdemdumController::class, 'update'])->name('edit.update');
        Route::get('/{ademdumId}/view', [AdemdumController::class, 'view'])->name('view');
        Route::delete('/{ademdumId}', [AdemdumController::class, 'delete'])->name('delete');
    });
});

Route::prefix('tenant')->name('tenant.')->middleware(['auth', 'roomer'])->group(function () {

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

        Route::get('/{agreementId}/view', [AgreementController::class, 'view'])->name('view');
        Route::patch('/{agreementId}/accept', [AgreementController::class, 'accept'])->name('accept');
    });

    Route::prefix('agreements/{agreementId}/ademdums')->name('ademdums.')->middleware('auth')->group(function () {
        Route::get('/{ademdumId}/view', [AdemdumController::class, 'view'])->name('view');
    });
});
