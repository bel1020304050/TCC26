<?php

use App\Http\Middleware\EncerraSessaoInativa;
use App\Http\Middleware\ExigeTrocaDeSenha;
use App\Http\Middleware\RecusaCamposEmLista;
use App\Http\Middleware\SemCacheParaLogado;
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
        // Apelido usado nas rotas: ->middleware('perfil:admin,gerente')
        $middleware->alias([
            'perfil' => VerificaPerfil::class,
        ]);

        // Roda em todas as páginas: derruba a sessão de quem foi inativado
        // Segurança: campo enviado como lista (email[]=x) vira vazio, em vez de causar erro 500
        $middleware->prependToGroup('web', RecusaCamposEmLista::class);

        $middleware->appendToGroup('web', EncerraSessaoInativa::class);

        // Páginas de quem está logado não ficam em cache no navegador (botão Voltar depois de sair)
        $middleware->appendToGroup('web', SemCacheParaLogado::class);

        // Roda em todas as páginas: quem está com senha provisória precisa criar a própria senha antes de continuar
        $middleware->appendToGroup('web', ExigeTrocaDeSenha::class);

        // Visitante tentando abrir página restrita vai para o login
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Usuário já logado tentando abrir login/cadastro vai para a sua área
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Se a validação falhar, estes campos NÃO voltam preenchidos na tela (nem ficam na sessão)
        $exceptions->dontFlash(['password', 'password_confirmation', 'senha_atual', 'senha_encerrar', 'cartao_numero', 'cartao_cvv', 'cartao_validade']);
    })->create();
