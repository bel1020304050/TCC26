<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Painel\InicioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Loja (área pública e do cliente)
|--------------------------------------------------------------------------
*/
Route::view('/', 'loja.inicio')->name('loja.inicio');

/*
|--------------------------------------------------------------------------
| Autenticação — story 3.13 (login/logout) e 3.9 (criar conta)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->name('login.store');
    Route::get('/criar-conta', [RegisterController::class, 'create'])->name('register');
    Route::post('/criar-conta', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/sair', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Painel administrativo — só equipe interna (seção 2.2)
|--------------------------------------------------------------------------
*/
Route::prefix('painel')->name('painel.')->middleware(['auth', 'perfil:admin,funcionario'])->group(function () {
    Route::get('/', InicioController::class)->name('inicio');

    // Módulos só do Administrador. Por enquanto é um aviso; o CRUD entra na Fase 4.
    Route::middleware('perfil:admin')->group(function () {
        Route::view('/usuarios', 'painel.em-breve', ['modulo' => 'Usuários', 'fase' => 4])->name('usuarios.index');
    });
});
