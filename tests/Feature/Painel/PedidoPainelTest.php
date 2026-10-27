<?php

namespace Tests\Feature\Painel;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Consulta de pedidos na área administrativa — story 3.19 (Figura 21).
 */
class PedidoPainelTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(User $cliente, string $status, string $data): Pedido
    {
        $pedido = new Pedido;
        $pedido->user_id = $cliente->id;
        $pedido->status = $status;
        $pedido->subtotal = $pedido->total = 100;
        foreach (['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $cliente->{$campo};
        }
        $pedido->created_at = Carbon::parse($data);
        $pedido->save();

        return $pedido;
    }

    public function test_funcionario_e_admin_veem_todos_os_pedidos(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Souza']);
        $bia = User::factory()->create(['name' => 'Bia Lima']);
        $this->pedido($ana, Pedido::AGUARDANDO_PAGAMENTO, '2026-10-19');
        $this->pedido($bia, Pedido::ENTREGUE, '2026-10-05');

        foreach ([User::factory()->funcionario()->create(), User::factory()->admin()->create()] as $equipe) {
            $this->actingAs($equipe)->get('/painel/pedidos')
                ->assertOk()->assertSee('Ana Souza')->assertSee('Bia Lima');
        }
    }

    public function test_pesquisa_por_numero_e_por_cliente(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Souza']);
        $bia = User::factory()->create(['name' => 'Bia Lima']);
        $pedidoAna = $this->pedido($ana, Pedido::PAGO, '2026-10-17');
        $this->pedido($bia, Pedido::PAGO, '2026-10-17');
        $func = User::factory()->funcionario()->create();

        $this->actingAs($func)->get('/painel/pedidos?busca=%23'.$pedidoAna->id)->assertSee('Ana Souza')->assertDontSee('Bia Lima');
        $this->actingAs($func)->get('/painel/pedidos?busca=bia')->assertSee('Bia Lima')->assertDontSee('Ana Souza');
    }

    public function test_filtra_por_status_e_periodo(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Souza']);
        $bia = User::factory()->create(['name' => 'Bia Lima']);
        $this->pedido($ana, Pedido::ENTREGUE, '2026-09-28');
        $this->pedido($bia, Pedido::AGUARDANDO_PAGAMENTO, '2026-10-19');
        $func = User::factory()->funcionario()->create();

        $this->actingAs($func)->get('/painel/pedidos?status=entregue')->assertSee('Ana Souza')->assertDontSee('Bia Lima');
        $this->actingAs($func)->get('/painel/pedidos?de=2026-10-01&ate=2026-10-31')->assertSee('Bia Lima')->assertDontSee('Ana Souza');
        $this->actingAs($func)->get('/painel/pedidos?de=data-errada')->assertOk()->assertSee('Ana Souza');
    }

    public function test_detalhes_mostram_historico_com_responsavel(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Souza']);
        $rafael = User::factory()->funcionario()->create(['name' => 'Rafael Martins']);
        $pedido = $this->pedido($ana, Pedido::AGUARDANDO_PAGAMENTO, '2026-10-17');
        $pedido->historico()->create(['status' => Pedido::AGUARDANDO_PAGAMENTO, 'user_id' => $ana->id]);
        $pedido->registrarStatus(Pedido::PAGO);
        $pedido->registrarStatus(Pedido::EM_SEPARACAO, $rafael);

        $this->actingAs($rafael)->get("/painel/pedidos/{$pedido->id}")
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertSee('Em Separação')
            ->assertSee('Rafael Martins')
            ->assertSee('Sistema');
    }

    public function test_cliente_nao_acessa_pedidos_do_painel(): void
    {
        $cliente = User::factory()->create();
        $pedido = $this->pedido($cliente, Pedido::PAGO, '2026-10-17');

        $this->actingAs($cliente)->get('/painel/pedidos')->assertForbidden();
        $this->actingAs($cliente)->get("/painel/pedidos/{$pedido->id}")->assertForbidden();
    }
}
