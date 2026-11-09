<?php

namespace Tests\Feature\Painel;

use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Página inicial do painel: indicadores do dia e últimos pedidos.
 */
class InicioPainelTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(string $status, float $total, ?string $data = null): Pedido
    {
        $cliente = User::factory()->create(['name' => 'Ana Souza']);
        $pedido = new Pedido;
        $pedido->user_id = $cliente->id;
        $pedido->status = $status;
        $pedido->subtotal = $pedido->total = $total;
        foreach (['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $cliente->{$campo} ?? 'x';
        }
        $pedido->created_at = $data ?? now();
        $pedido->save();

        return $pedido;
    }

    public function test_mostra_indicadores_e_ultimos_pedidos(): void
    {
        $this->pedido(Pedido::AGUARDANDO_PAGAMENTO, 50);
        $this->pedido(Pedido::PAGO, 100);
        $this->pedido(Pedido::ENTREGUE, 200);
        $this->pedido(Pedido::ENTREGUE, 999, now()->subMonths(2)->toDateString()); // fora do mês
        $this->pedido(Pedido::CANCELADO, 70);                                      // não conta
        Produto::factory()->create(['estoque' => 3]);
        Produto::factory()->create(['estoque' => 30]);

        $this->actingAs(User::factory()->admin()->create())->get('/painel')
            ->assertOk()
            ->assertSeeInOrder(['Aguardando pagamento', '1', 'Pedidos para separar', '1', 'Produtos com estoque baixo', '1', 'Vendas do mês', 'R$ 300,00'])
            ->assertSee('Últimos pedidos')
            ->assertSee('Ana Souza')
            ->assertDontSee('Andamento do sistema');
    }

    public function test_gerente_e_atendente_veem_o_resumo_com_atalho_para_vendas(): void
    {
        foreach ([User::factory()->gerente()->create(), User::factory()->atendente()->create()] as $usuario) {
            $this->actingAs($usuario)->get('/painel')
                ->assertOk()
                ->assertSee('Vendas do mês')
                ->assertSee(route('painel.relatorios.vendas'));
        }
    }
}
