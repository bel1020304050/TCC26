<?php

use App\Http\Middleware\EncerraSessaoInativa;
use App\Http\Middleware\VerificaPerfil;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apelido usado nas rotas: ->middleware('perfil:admin,funcionario')
        $middleware->alias([
            'perfil' => VerificaPerfil::class,
        ]);

        // Roda em todas as páginas: derruba a sessão de quem foi inativado
        $middleware->appendToGroup('web', EncerraSessaoInativa::class);

        // Visitante tentando abrir página restrita vai para o login
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Usuário já logado tentando abrir login/cadastro vai para a sua área
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
