<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página inicial do painel administrativo (Administrador e Funcionário).
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('painel.inicio', ['usuario' => $request->user()]);
    }
}
