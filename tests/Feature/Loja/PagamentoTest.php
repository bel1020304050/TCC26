<?php

namespace Tests\Feature\Loja;

use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use App\Services\FinalizarCompra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Processo de Finalizar Compra / Pagamento (provedor simulado).
 */
class PagamentoTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private Produto $vestido;

    private Produto $blusa;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = User::factory()->create();
        $this->vestido = Produto::factory()->create(['nome' => 'Vestido Floral', 'tamanho' => 'M', 'cor' => 'Preto', 'preco' => 129.90, 'estoque' => 8]);
        $this->blusa = Produto::factory()->create(['nome' => 'Blusa Social', 'tamanho' => 'P', 'cor' => 'Branco', 'preco' => 89.90, 'estoque' => 12]);

        // Pedido criado pelo fluxo real (carrinho → confirmar pedido)
        $carrinho = Carrinho::doCliente($this->cliente);
        $carrinho->itens()->create(['produto_id' => $this->vestido->id, 'quantidade' => 2]);
        $carrinho->itens()->create(['produto_id' => $this->blusa->id, 'quantidade' => 1]);
        $this->actingAs($this->cliente)->post('/pedidos');
        $this->pedido = Pedido::sole();
    }

    private function pagar(string $forma, array $cartao = [])
    {
        return $this->actingAs($this->cliente)
            ->from("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->post("/meus-pedidos/{$this->pedido->id}/pagamento", ['forma' => $forma] + $cartao);
    }

    private function cartao(string $numero): array
    {
        return ['cartao_numero' => $numero, 'cartao_nome' => 'ANA SOUZA', 'cartao_validade' => '12/'.now()->addYears(2)->format('y'), 'cartao_cvv' => '123'];
    }

    private function assertPedidoPagoComEstoqueBaixado(): void
    {
        $this->assertSame(Pedido::PAGO, $this->pedido->fresh()->status);
        $this->assertSame(6, $this->vestido->fresh()->estoque);  // 8 - 2
        $this->assertSame(11, $this->blusa->fresh()->estoque);   // 12 - 1
        $carrinho = $this->pedido->carrinho()->first();
        $this->assertSame(Carrinho::FINALIZADO, $carrinho->status);
        $this->assertSame(0, $carrinho->itens()->count());       // carrinho limpo
        $this->assertDatabaseHas('pedido_historicos', ['pedido_id' => $this->pedido->id, 'status' => 'pago']);
    }

    public function test_tela_de_pagamento_mostra_resumo_e_as_tres_formas(): void
    {
        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->assertOk()
            ->assertSee('PIX')->assertSee('Cartão de Crédito')->assertSee('Boleto Bancário')
            ->assertSee('R$ 349,70'); // 2 × 129,90 + 89,90
    }

    // CT-3-31-01: PIX aprovado
    public function test_pix_fica_pendente_e_ao_ser_pago_finaliza_a_compra(): void
    {
        $this->pagar('pix')->assertRedirect("/meus-pedidos/{$this->pedido->id}/pagamento");

        $pix = Pagamento::sole();
        $this->assertSame(Pagamento::PENDENTE, $pix->status);
        $this->assertSame('349.70', $pix->valor);
        $this->assertNotEmpty($pix->pix_codigo);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
        $this->assertSame(8, $this->vestido->fresh()->estoque); // ainda não baixou

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")->assertSee('Simular pagamento recebido');

        $this->actingAs($this->cliente)->post("/meus-pedidos/{$this->pedido->id}/pagamento/{$pix->id}/simular-pix")
            ->assertRedirect("/meus-pedidos/{$this->pedido->id}/concluido");

        $this->assertSame(Pagamento::APROVADO, $pix->fresh()->status);
        $this->assertSame('pix', $this->pedido->fresh()->forma_pagamento);
        $this->assertPedidoPagoComEstoqueBaixado();

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/concluido")->assertOk()->assertSee('Pagamento aprovado!');
    }

    public function test_cartao_aprovado_finaliza_na_hora_e_nao_guarda_dados_do_cartao(): void
    {
        $this->pagar('cartao', $this->cartao('4111 1111 1111 1111'))
            ->assertRedirect("/meus-pedidos/{$this->pedido->id}/concluido");

        $pagamento = Pagamento::sole();
        $this->assertSame(Pagamento::APROVADO, $pagamento->status);
        $this->assertSame('Visa', $pagamento->cartao_bandeira);
        $this->assertSame('1111', $pagamento->cartao_final);
        $this->assertPedidoPagoComEstoqueBaixado();

        // Número completo e CVV não aparecem em nenhuma coluna
        $linha = json_encode(DB::table('pagamentos')->first());
        $this->assertStringNotContainsString('4111111111111111', $linha);
        $this->assertStringNotContainsString('"123"', $linha);
    }

    // CT-3-31-02: cartão recusado
    public function test_cartao_recusado_nao_baixa_estoque_e_permite_tentar_de_novo(): void
    {
        $this->pagar('cartao', $this->cartao('5555 4444 3333 0000'))
            ->assertRedirect("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->assertSessionHas('erro');

        $this->assertSame(Pagamento::RECUSADO, Pagamento::sole()->status);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
        $this->assertSame(8, $this->vestido->fresh()->estoque);
        $this->assertSame(Carrinho::EM_PAGAMENTO, $this->pedido->carrinho()->first()->status);

        // Nova tentativa no MESMO pedido
        $this->pagar('cartao', $this->cartao('5555 4444 3333 1111'))->assertRedirect("/meus-pedidos/{$this->pedido->id}/concluido");

        $this->assertSame(1, Pedido::count());
        $this->assertSame(2, Pagamento::count());
        $this->assertPedidoPagoComEstoqueBaixado();
    }

    // Provedor fora do ar: mensagem clara e o pedido continua aguardando
    public function test_provedor_fora_do_ar_mostra_mensagem_e_mantem_o_pedido(): void
    {
        $this->pagar('cartao', $this->cartao('4111 1111 1111 9999'))
            ->assertSessionHas('erro', 'O serviço de pagamento está temporariamente indisponível. Tente de novo em instantes; o seu pedido continua reservado para pagamento.');

        $this->assertSame(Pagamento::RECUSADO, Pagamento::sole()->status);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
        $this->assertSame(1, Pedido::count());
    }

    public function test_dados_do_cartao_nao_voltam_preenchidos_quando_a_validacao_falha(): void
    {
        $this->pagar('cartao', ['cartao_numero' => '4111111111111111', 'cartao_cvv' => '123', 'cartao_validade' => '01/20', 'cartao_nome' => ''])
            ->assertSessionHasErrors(['cartao_nome', 'cartao_validade' => 'O cartão está vencido.']);

        $this->assertNull(session()->getOldInput('cartao_numero'));
        $this->assertNull(session()->getOldInput('cartao_cvv'));
        $this->assertSame(0, Pagamento::count());
    }

    public function test_boleto_fica_pendente_e_so_o_admin_confirma_a_compensacao(): void
    {
        $this->pagar('boleto');
        $boleto = Pagamento::sole();
        $this->assertSame(Pagamento::PENDENTE, $boleto->status);
        $this->assertNotEmpty($boleto->boleto_linha);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);

        // Cliente e Funcionário não confirmam
        $this->actingAs($this->cliente)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto")->assertForbidden();
        $this->actingAs(User::factory()->funcionario()->create())->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto")->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get("/painel/pedidos/{$this->pedido->id}")->assertSee('Confirmar compensação do boleto');
        $this->actingAs($admin)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto")->assertSessionHas('sucesso');

        $this->assertSame($admin->id, $boleto->fresh()->confirmado_por);
        $this->assertPedidoPagoComEstoqueBaixado();
        $this->assertDatabaseHas('auditorias', ['acao' => 'confirmou_boleto', 'entidade_id' => $boleto->id]);
    }

    // Idempotência: a mesma aprovação recebida duas vezes baixa o estoque uma vez só
    public function test_aprovacao_repetida_baixa_estoque_uma_unica_vez(): void
    {
        $this->pagar('pix');
        $pix = Pagamento::sole();

        $this->actingAs($this->cliente)->post("/meus-pedidos/{$this->pedido->id}/pagamento/{$pix->id}/simular-pix");
        $this->actingAs($this->cliente)->post("/meus-pedidos/{$this->pedido->id}/pagamento/{$pix->id}/simular-pix");
        app(FinalizarCompra::class)->confirmarAprovacao($pix);

        $this->assertSame(6, $this->vestido->fresh()->estoque);
        $this->assertSame(1, $this->pedido->historico()->where('status', 'pago')->count());
    }

    // Regra: sem estoque na aprovação → pedido Cancelado e pagamento Estornado
    public function test_sem_estoque_na_aprovacao_cancela_pedido_e_estorna(): void
    {
        $this->pagar('pix');
        $pix = Pagamento::sole();
        $this->vestido->update(['estoque' => 1]); // outra venda levou as peças

        $this->actingAs($this->cliente)->post("/meus-pedidos/{$this->pedido->id}/pagamento/{$pix->id}/simular-pix")
            ->assertRedirect("/meus-pedidos/{$this->pedido->id}")
            ->assertSessionHas('erro');

        $this->assertSame(Pagamento::ESTORNADO, $pix->fresh()->status);
        $this->assertSame(Pedido::CANCELADO, $this->pedido->fresh()->status);
        $this->assertSame(1, $this->vestido->fresh()->estoque); // nada baixado
        $this->assertSame(12, $this->blusa->fresh()->estoque);
        $this->assertSame(Carrinho::ABERTO, $this->pedido->carrinho()->first()->status); // volta a ser editável
    }

    public function test_estoque_revalidado_antes_de_enviar_ao_provedor(): void
    {
        $this->vestido->update(['estoque' => 1]);

        $this->pagar('cartao', $this->cartao('4111111111111111'))->assertSessionHas('erro');

        $this->assertSame(0, Pagamento::count());
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
    }

    public function test_novo_pix_ou_boleto_substitui_o_pendente_anterior(): void
    {
        $this->pagar('pix');
        $this->pagar('boleto');

        $this->assertSame(1, Pagamento::where('status', Pagamento::PENDENTE)->count());
        $this->assertSame('boleto', Pagamento::where('status', Pagamento::PENDENTE)->value('forma'));
    }

    public function test_nao_paga_pedido_de_outro_cliente_nem_pedido_ja_pago(): void
    {
        $outro = User::factory()->create();
        $this->actingAs($outro)->get("/meus-pedidos/{$this->pedido->id}/pagamento")->assertForbidden();
        $this->actingAs($outro)->post("/meus-pedidos/{$this->pedido->id}/pagamento", ['forma' => 'pix'])->assertForbidden();

        $this->pagar('cartao', $this->cartao('4111111111111111'));
        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->assertRedirect("/meus-pedidos/{$this->pedido->id}");
        $this->pagar('pix')->assertSessionHas('erro');
        $this->assertSame(1, Pagamento::count());
    }

    public function test_depois_de_pago_o_cliente_ganha_um_carrinho_novo(): void
    {
        $this->pagar('cartao', $this->cartao('4111111111111111'));

        $novo = Carrinho::doCliente($this->cliente);
        $this->assertNotSame($this->pedido->carrinho_id, $novo->id);
        $this->assertTrue($novo->isAberto());
        $this->actingAs($this->cliente)->get('/carrinho')->assertSee('Seu carrinho está vazio.');
    }
}
