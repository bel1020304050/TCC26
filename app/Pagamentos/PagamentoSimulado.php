<?php

namespace App\Pagamentos;

use App\Models\Pagamento;
use Illuminate\Support\Str;

/**
 * Provedor de pagamento SIMULADO (MVP do TCC).
 * Funciona como o ambiente de testes de um provedor real:
 * - PIX: gera um código "copia e cola" fictício e fica Pendente até o cliente clicar em "Simular pagamento recebido".
 * - Cartão: cartões de teste — final 0000 = Recusado; final 9999 = provedor fora do ar; outro número = Aprovado.
 * - Boleto: gera uma linha digitável fictícia e fica Pendente até o Administrador confirmar a compensação.
 */
class PagamentoSimulado implements ProvedorPagamento
{
    public const FINAL_RECUSADO = '0000';
    public const FINAL_INDISPONIVEL = '9999';

    public function cobrar(Pagamento $pagamento, array $dados): RespostaProvedor
    {
        $referencia = 'SIM-'.strtoupper(Str::random(10));

        return match ($pagamento->forma) {
            'pix' => new RespostaProvedor(Pagamento::PENDENTE, $referencia, 'Aguardando o pagamento do PIX.', [
                'pix_codigo' => $this->codigoPix($pagamento, $referencia),
            ]),
            'boleto' => new RespostaProvedor(Pagamento::PENDENTE, $referencia, 'Aguardando a compensação do boleto.', [
                'boleto_linha' => $this->linhaDigitavel($pagamento),
                'boleto_vencimento' => now()->addDays(3)->toDateString(),
            ]),
            'cartao' => $this->cartao($dados, $referencia),
        };
    }

    private function cartao(array $dados, string $referencia): RespostaProvedor
    {
        $numero = preg_replace('/\D/', '', $dados['cartao_numero'] ?? '');
        $final = substr($numero, -4);

        if ($final === self::FINAL_INDISPONIVEL) {
            throw new ProvedorIndisponivel('O serviço de pagamento não respondeu.');
        }

        // Só bandeira e final saem daqui; número completo, validade e CVV são descartados
        $guardar = ['cartao_bandeira' => $this->bandeira($numero), 'cartao_final' => $final];

        if ($final === self::FINAL_RECUSADO) {
            return new RespostaProvedor(Pagamento::RECUSADO, $referencia, 'Pagamento recusado pela operadora do cartão.', $guardar);
        }

        return new RespostaProvedor(Pagamento::APROVADO, $referencia, 'Pagamento aprovado.', $guardar);
    }

    private function bandeira(string $numero): string
    {
        return match (true) {
            str_starts_with($numero, '4') => 'Visa',
            (bool) preg_match('/^5[1-5]/', $numero) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $numero) => 'Amex',
            (bool) preg_match('/^(4011|4312|4389|5041|5066|5067|6277|6362|6363|6504|6505|6516)/', $numero) => 'Elo',
            default => 'Cartão',
        };
    }

    // Código no formato do "PIX copia e cola", mas fictício (não paga nada de verdade)
    private function codigoPix(Pagamento $pagamento, string $referencia): string
    {
        $valor = number_format((float) $pagamento->valor, 2, '.', '');

        return "00020126580014BR.GOV.BCB.PIX0136mary-modas-simulado@pix.teste5204000053039865406{$valor}"
            ."5802BR5910MARY MODAS6008UMUARAMA62140510{$referencia}6304".strtoupper(substr(md5($referencia), 0, 4));
    }

    // 47 dígitos no formato de boleto bancário, gerados a partir do pedido (fictício)
    private function linhaDigitavel(Pagamento $pagamento): string
    {
        $base = str_pad((string) $pagamento->pedido_id, 10, '0', STR_PAD_LEFT);
        $valor = str_pad((string) round((float) $pagamento->valor * 100), 10, '0', STR_PAD_LEFT);
        $digitos = substr('23790'.$base.'9'.random_int(1000000000, 9999999999).'1'.random_int(1000, 9999).'0'.$valor, 0, 47);
        $digitos = str_pad($digitos, 47, '0');

        return substr($digitos, 0, 5).'.'.substr($digitos, 5, 5).' '.substr($digitos, 10, 5).'.'.substr($digitos, 15, 6).' '
            .substr($digitos, 21, 5).'.'.substr($digitos, 26, 6).' '.substr($digitos, 32, 1).' '.substr($digitos, 33, 14);
    }
}
