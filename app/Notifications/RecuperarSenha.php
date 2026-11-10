<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail "Redefinir senha" do cliente ("Esqueci minha senha").
 * Segurança: o link leva um código (token) de uso único que expira em 60 minutos.
 */
class RecuperarSenha extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Redefinir sua senha – Mary Modas')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a senha da sua conta na Mary Modas.')
            ->action('Criar nova senha', $url)
            ->line('O link vale por 60 minutos e só pode ser usado uma vez.')
            ->line('Se não foi você que pediu, ignore este e-mail: a sua senha continua a mesma.')
            ->salutation('Equipe Mary Modas');
    }
}
