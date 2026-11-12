<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    public const MOTIVO_MINIMO = 10;

    /**
     * Validação: motivo obrigatório para inativar e reativar (vai para a auditoria).
     * Chamado depois do Gate::authorize, para quem não tem permissão receber "acesso negado" e não um erro de formulário.
     * O "modal" volta junto com o erro para a tela reabrir a janela certa.
     */
    protected function motivo(Request $request): string
    {
        $dados = $request->validate(
            ['motivo' => ['required', 'string', 'min:'.self::MOTIVO_MINIMO, 'max:500']],
            [
                'motivo.required' => 'Escreva o motivo (pelo menos '.self::MOTIVO_MINIMO.' caracteres).',
                'motivo.min' => 'Escreva o motivo com pelo menos '.self::MOTIVO_MINIMO.' caracteres.',
                'motivo.max' => 'O motivo pode ter no máximo 500 caracteres.',
            ],
        );

        return trim($dados['motivo']);
    }
}
