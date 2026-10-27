<?php

namespace Database\Seeders;

use App\Models\Carrinho;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Pedidos e carrinho de exemplo da cliente Ana (mesmos dados dos wireframes 3.19 e 3.30).
 * Só roda com APP_ENV=local. Os pedidos antigos são "históricos": servem para ver as telas
 * de consulta; o estoque dos produtos não é alterado por este seeder.
 */
class PedidosTesteSeeder extends Seeder
{
    public function run(): void
    {
        $ana = User::where('email', 'ana@email.com')->first();
        $mary = User::where('email', 'mary@marymodas.com.br')->first();
        $rafael = User::where('email', 'rafael@marymodas.com.br')->first();
        if (! $ana || Pedido::where('user_id', $ana->id)->exists()) {
            return; // já semeado
        }

        $produto = fn (string $nome, string $tamanho) => Produto::where('nome', $nome)->where('tamanho', $tamanho)->firstOrFail();

        // Pedido cancelado antes do pagamento
        $this->pedido($ana, [[$produto('Blusa Social', 'P'), 1]], null, [
            [Pedido::AGUARDANDO_PAGAMENTO, '-27 days 15:00', $ana],
            [Pedido::CANCELADO, '-27 days 18:20', $mary, 'Cliente desistiu da compra.'],
        ]);

        // Pedido entregue, com o histórico completo
        $this->pedido($ana, [[$produto('Blusa Básica', 'P'), 1], [$produto('Blusa Social', 'P'), 1]], 'pix', [
            [Pedido::AGUARDANDO_PAGAMENTO, '-20 days 10:12', $ana],
            [Pedido::PAGO, '-20 days 10:15', null],
            [Pedido::EM_SEPARACAO, '-19 days 09:30', $rafael],
            [Pedido::ENVIADO, '-18 days 14:05', $rafael],
            [Pedido::ENTREGUE, '-16 days 16:40', $rafael],
        ]);

        // Carrinho aberto com 2 itens, pronto para testar "Finalizar compra"
        $carrinho = Carrinho::create(['user_id' => $ana->id]);
        $carrinho->itens()->create(['produto_id' => $produto('Vestido Floral', 'M')->id, 'quantidade' => 1]);
        $carrinho->itens()->create(['produto_id' => $produto('Blusa Social', 'P')->id, 'quantidade' => 1]);
    }

    /**
     * @param  array<int, array{0: Produto, 1: int}>  $itens
     * @param  array<int, array>  $historico  [status, quando, responsável, observação?]
     */
    private function pedido(User $cliente, array $itens, ?string $forma, array $historico): void
    {
        $criadoEm = Carbon::parse($historico[0][1]);
        $carrinho = Carrinho::create(['user_id' => $cliente->id]);
        $carrinho->status = Carrinho::FINALIZADO;
        $carrinho->save();

        $subtotal = collect($itens)->sum(fn ($i) => round((float) $i[0]->preco * $i[1], 2));

        $pedido = new Pedido;
        $pedido->user_id = $cliente->id;
        $pedido->carrinho_id = $carrinho->id;
        $pedido->subtotal = $subtotal;
        $pedido->desconto = 0;
        $pedido->total = $subtotal;
        $pedido->forma_pagamento = $forma;
        foreach (['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $cliente->{$campo};
        }
        $pedido->status = end($historico)[0];
        $pedido->created_at = $criadoEm;
        $pedido->updated_at = Carbon::parse(end($historico)[1]);
        $pedido->save();

        foreach ($itens as [$produto, $quantidade]) {
            $pedido->itens()->create([
                'produto_id' => $produto->id, 'nome' => $produto->nome, 'tamanho' => $produto->tamanho, 'cor' => $produto->cor,
                'quantidade' => $quantidade, 'preco_unitario' => $produto->preco, 'subtotal' => round((float) $produto->preco * $quantidade, 2),
            ]);
        }

        foreach ($historico as $registro) {
            $linha = $pedido->historico()->create([
                'status' => $registro[0], 'user_id' => $registro[2]?->id, 'observacao' => $registro[3] ?? null,
            ]);
            $linha->created_at = Carbon::parse($registro[1]);
            $linha->save();
        }
    }
}
