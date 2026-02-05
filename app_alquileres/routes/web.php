<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\lessorController;
use App\Http\Controllers\LoginController;
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

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {

    // Inicio
    Route::get('/', [AdminController::class, 'index'])->name('index');

    // Configuraciones de cuenta
    Route::prefix('configuration')->name('configuration.')->middleware('auth')->group(function () {
        //Ventana de configuracion
        Route::get('/', [UserController::class, 'index'])->name('index');

        //Datos del usuario
        Route::patch('/user', [UserController::class, 'update'])->name('user.update');

        //Datos del role: Lessor o roomer
        Route::patch('/lessor', [lessorController::class, 'update'])->name('lessor.update');

        Route::patch('/roomer', [roomerController::class, 'update'])->name('roomer.update');
    });
});
