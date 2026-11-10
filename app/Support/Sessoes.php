<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sessões abertas de um usuário (as sessões ficam na tabela "sessions").
 */
final class Sessoes
{
    /**
     * Segurança: depois de trocar a senha, desconecta os outros aparelhos (outro computador, celular),
     * mantendo só o aparelho que fez a troca. Se alguém tinha descoberto a senha antiga, perde o acesso na hora.
     */
    public static function encerrarOutras(User $usuario, Request $request): void
    {
        DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $request->session()->regenerate(); // o aparelho atual ganha um id de sessão novo
    }
}
