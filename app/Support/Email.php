<?php

namespace App\Support;

use Closure;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Envio de e-mail que não derruba a tela.
 * Se o servidor de e-mail falhar (internet fora, Mailtrap com usuário/senha errados), o erro vai para o log
 * e a operação continua: a conta é criada do mesmo jeito e o cliente pode pedir "Reenviar" depois.
 */
final class Email
{
    public static function enviar(Closure $envio): bool
    {
        try {
            $envio();

            return true;
        } catch (TransportExceptionInterface $e) {
            report($e); // grava em storage/logs/laravel.log para quem for investigar

            return false;
        }
    }

    public const AVISO_FALHA = 'Não conseguimos enviar o e-mail agora. Tente "Reenviar" daqui a pouco.';
}
