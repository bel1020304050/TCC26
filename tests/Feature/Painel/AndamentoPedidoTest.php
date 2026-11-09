<?php

namespace Tests\Feature\Painel;

use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Andamento do pedido: atualizar status e cancelar.
 * Os pedidos são criados e pagos pelo fluxo real (carrinho → pedido → cartão aprovado).
 */
class AndamentoPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private User $funcionario;

    private User $admin;

    private Produto $vestido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = User::factory()->create();
        $this->funcionario = User::factory()->funcionario()->create(['name' => 'Rafael Martins']);
        $this->admin = User::factory()->admin()->create();
        $this->vestido = Produto::factory()->create(['preco' => 100, 'estoque' => 8]);
    }

    private function pedidoAguardando(): Pedido
    {
        Carrinho::doCliente($this->cliente)->itens()->create(['produto_id' => $this->vestido->id, 'quantidade' => 2]);
        $this->actingAs($this->cliente)->post('/pedidos');

        return Pedido::latest('id')->first();
    }

    private function pedidoPago(): Pedido
    {
        $pedido = $this->pedidoAguardando();
        $this->actingAs($this->cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", [
            'forma' => 'cartao', 'cartao_numero' => '4111111111111111', 'cartao_nome' => 'ANA',
            'cartao_validade' => '12/'.now()->addYears(2)->format('y'), 'cartao_cvv' => '123',
        ]);
        $this->assertSame(6, $this->vestido->fresh()->estoque);

        return $pedido->fresh();
    }

    private function mudarStatus(User $quem, Pedido $pedido, string $novo)
    {
        return $this->actingAs($quem)->from("/painel/pedidos/{$pedido->id}")->patch("/painel/pedidos/{$pedido->id}/status", ['status' => $novo]);
    }

    // CT-3-20-01
    public function test_funcionario_avanca_pago_para_em_separacao_com_historico(): void
    {
        $pedido = $this->pedidoPago();

        $this->mudarStatus($this->funcionario, $pedido, Pedido::EM_SEPARACAO)->assertSessionHas('sucesso');

        $this->assertSame(Pedido::EM_SEPARACAO, $pedido->fresh()->status);
        $this->assertDatabaseHas('pedido_historicos', [
            'pedido_id' => $pedido->id, 'status_anterior' => 'pago', 'status' => 'em_separacao', 'user_id' => $this->funcionario->id,
        ]);
        $this->assertDatabaseHas('auditorias', ['acao' => 'alterou_status', 'entidade' => 'pedidos', 'entidade_id' => $pedido->id]);

        // Aparece para o cliente
        $this->actingAs($this->cliente)->get("/meus-pedidos/{$pedido->id}")->assertSee('Em Separação');
    }

    public function test_fluxo_completo_ate_entregue(): void
    {
        $pedido = $this->pedidoPago();

        foreach ([Pedido::EM_SEPARACAO, Pedido::ENVIADO, Pedido::ENTREGUE] as $proximo) {
            $this->mudarStatus($this->funcionario, $pedido, $proximo)->assertSessionHas('sucesso');
        }

        $this->assertSame(Pedido::ENTREGUE, $pedido->fresh()->status);
        $this->mudarStatus($this->admin, $pedido, Pedido::ENTREGUE)->assertSessionHas('erro');
    }

    // CT-3-20-02
    public function test_nao_pula_etapas_nem_volta(): void
    {
        $pedido = $this->pedidoPago();

        $this->mudarStatus($this->funcionario, $pedido, Pedido::ENTREGUE)
            ->assertSessionHas('erro', 'Transição não permitida: o próximo status permitido é Em Separação.');
        $this->mudarStatus($this->funcionario, $pedido, Pedido::AGUARDANDO_PAGAMENTO)->assertSessionHas('erro');

        $this->assertSame(Pedido::PAGO, $pedido->fresh()->status);
    }

    public function test_pago_nao_e_marcado_manualmente(): void
    {
        $pedido = $this->pedidoAguardando();

        $this->mudarStatus($this->admin, $pedido, Pedido::PAGO)
            ->assertSessionHas('erro', '"Pago" é definido automaticamente pela confirmação do pagamento.');
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $pedido->fresh()->status);
    }

    public function test_cliente_nao_atualiza_status(): void
    {
        $pedido = $this->pedidoPago();

        $this->mudarStatus($this->cliente, $pedido, Pedido::EM_SEPARACAO)->assertForbidden();
    }

    public function test_tela_oferece_so_o_proximo_status(): void
    {
        $pedido = $this->pedidoPago();

        $this->actingAs($this->funcionario)->get('/painel/pedidos')
            ->assertSee('Atualizar status')
            ->assertSee('<option value="em_separacao">Em Separação</option>', false)
            ->assertDontSee('<option value="entregue">', false);
    }

    // CT-3-21-01
    public function test_cliente_cancela_pedido_aguardando_e_carrinho_volta_a_abrir(): void
    {
        $pedido = $this->pedidoAguardando();
        $this->actingAs($this->cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'pix']); // PIX pendente

        $this->actingAs($this->cliente)->patch("/meus-pedidos/{$pedido->id}/cancelar", ['motivo' => ''])
            ->assertRedirect("/meus-pedidos/{$pedido->id}")
            ->assertSessionHas('sucesso');

        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->status);
        $this->assertSame(Carrinho::ABERTO, $pedido->carrinho()->first()->status);
        $this->assertSame(Pagamento::RECUSADO, Pagamento::sole()->status); // PIX deixou de valer
        $this->assertSame(8, $this->vestido->fresh()->estoque);
        $this->assertDatabaseHas('pedido_historicos', ['pedido_id' => $pedido->id, 'status' => 'cancelado', 'user_id' => $this->cliente->id]);
        $this->assertDatabaseHas('auditorias', ['acao' => 'cancelou', 'entidade_id' => $pedido->id]);

        // Permanece no histórico do cliente
        $this->actingAs($this->cliente)->get('/meus-pedidos')->assertSee($pedido->numero())->assertSee('Cancelado');
    }

    public function test_pix_pago_depois_do_cancelamento_nao_baixa_estoque(): void
    {
        $pedido = $this->pedidoAguardando();
        $this->actingAs($this->cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'pix']);
        $pix = Pagamento::sole();
        $this->actingAs($this->cliente)->patch("/meus-pedidos/{$pedido->id}/cancelar")->assertSessionHas('sucesso');

        $this->actingAs($this->cliente)->post("/meus-pedidos/{$pedido->id}/pagamento/{$pix->id}/simular-pix");

        $this->assertSame(8, $this->vestido->fresh()->estoque);
        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->status);
    }

    // CT-3-21-02
    public function test_funcionario_nao_cancela_pedido_pago(): void
    {
        $pedido = $this->pedidoPago();

        $this->actingAs($this->funcionario)->from("/painel/pedidos/{$pedido->id}")
            ->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => 'Cliente pediu'])
            ->assertSessionHas('erro', 'Pedidos pagos só podem ser cancelados pelo Administrador, com estorno.');

        $this->assertSame(Pedido::PAGO, $pedido->fresh()->status);
        $this->actingAs($this->funcionario)->get("/painel/pedidos/{$pedido->id}")
            ->assertSee('Pedidos pagos só podem ser cancelados pelo Administrador')
            ->assertDontSee('Confirmar cancelamento');
    }

    public function test_admin_cancela_pedido_pago_com_estorno_e_devolucao_ao_estoque(): void
    {
        $pedido = $this->pedidoPago();
        $this->mudarStatus($this->funcionario, $pedido, Pedido::EM_SEPARACAO);

        $this->actingAs($this->admin)->from("/painel/pedidos/{$pedido->id}")
            ->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => 'Cliente desistiu por telefone'])
            ->assertSessionHas('sucesso');

        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->status);
        $this->assertSame(Pagamento::ESTORNADO, Pagamento::where('pedido_id', $pedido->id)->sole()->status);
        $this->assertSame(8, $this->vestido->fresh()->estoque); // 6 + 2 devolvidas
        $this->assertDatabaseHas('pedido_historicos', [
            'pedido_id' => $pedido->id, 'status_anterior' => 'em_separacao', 'status' => 'cancelado',
            'user_id' => $this->admin->id, 'observacao' => 'Motivo: Cliente desistiu por telefone',
        ]);
    }

    public function test_motivo_obrigatorio_para_a_equipe(): void
    {
        $pedido = $this->pedidoAguardando();

        $this->actingAs($this->funcionario)->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $pedido->fresh()->status);

        $this->actingAs($this->funcionario)->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => 'Pedido duplicado'])
            ->assertSessionHas('sucesso');
        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->status);
    }

    public function test_enviado_entregue_e_cancelado_nao_podem_ser_cancelados(): void
    {
        $pedido = $this->pedidoPago();
        $this->mudarStatus($this->funcionario, $pedido, Pedido::EM_SEPARACAO);
        $this->mudarStatus($this->funcionario, $pedido, Pedido::ENVIADO);

        $this->actingAs($this->admin)->from("/painel/pedidos/{$pedido->id}")
            ->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => 'Tentativa'])
            ->assertSessionHas('erro');
        $this->assertSame(Pedido::ENVIADO, $pedido->fresh()->status);
        $this->assertSame(6, $this->vestido->fresh()->estoque);
    }

    public function test_cliente_nao_cancela_pedido_pago_nem_de_outro_cliente(): void
    {
        $pedido = $this->pedidoPago();

        $this->actingAs($this->cliente)->from("/meus-pedidos/{$pedido->id}")
            ->patch("/meus-pedidos/{$pedido->id}/cancelar")->assertSessionHas('erro');
        $this->assertSame(Pedido::PAGO, $pedido->fresh()->status);

        $outro = $this->pedidoAguardando();
        $this->actingAs(User::factory()->create())->patch("/meus-pedidos/{$outro->id}/cancelar")->assertForbidden();
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $outro->fresh()->status);
    }
}
