<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail "Confirme seu e-mail" enviado ao cliente depois do cadastro (ou da troca de e-mail).
 * Reaproveita o link assinado do Laravel (expira em 60 minutos); só troca o texto para português.
 */
class VerificarEmail extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme seu e-mail – Mary Modas')
            ->greeting('Olá!')
            ->line('Recebemos o cadastro da sua conta na Mary Modas. Para finalizar compras, confirme que este e-mail é seu.')
            ->action('Confirmar e-mail', $url)
            ->line('O link vale por 60 minutos. Se expirar, peça um novo na página do carrinho.')
            ->line('Se você não criou esta conta, ignore este e-mail.')
            ->salutation('Equipe Mary Modas');
    }
}
