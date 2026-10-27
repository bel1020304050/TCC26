<?php

namespace Tests\Feature\Loja;

use App\Models\Carrinho;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedidos do cliente — stories 3.18 (criar) e 3.19 (consultar e acompanhar).
 */
class PedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private Produto $vestido;

    private Produto $blusa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = User::factory()->create([
            'cep' => '87501-000', 'rua' => 'Avenida Brasil', 'numero' => '120', 'complemento' => 'Apto 32',
            'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
        ]);
        $this->vestido = Produto::factory()->create(['nome' => 'Vestido Floral', 'tamanho' => 'M', 'cor' => 'Preto', 'preco' => 129.90, 'estoque' => 8]);
        $this->blusa = Produto::factory()->create(['nome' => 'Blusa Social', 'tamanho' => 'P', 'cor' => 'Branco', 'preco' => 89.90, 'estoque' => 12]);
    }

    private function encherCarrinho(array $itens = null): Carrinho
    {
        $carrinho = Carrinho::doCliente($this->cliente);
        foreach ($itens ?? [[$this->vestido, 1], [$this->blusa, 1]] as [$produto, $quantidade]) {
            $carrinho->itens()->create(['produto_id' => $produto->id, 'quantidade' => $quantidade]);
        }

        return $carrinho;
    }

    // CT-3-18-01
    public function test_cria_pedido_com_carrinho_valido(): void
    {
        $carrinho = $this->encherCarrinho();

        $this->actingAs($this->cliente)->get('/pedido/confirmar')
            ->assertOk()
            ->assertSee('Resumo do carrinho')
            ->assertSee('Avenida Brasil, 120')
            ->assertSee('R$ 219,80');

        $resposta = $this->actingAs($this->cliente)->post('/pedidos');

        $pedido = Pedido::sole();
        $resposta->assertRedirect(route('loja.pedidos.show', $pedido));

        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $pedido->status);
        $this->assertSame('219.80', $pedido->subtotal);
        $this->assertSame('0.00', $pedido->desconto);
        $this->assertSame('219.80', $pedido->total);
        $this->assertSame(['87501-000', 'Avenida Brasil', '120', 'Apto 32', 'Centro', 'Umuarama', 'PR'],
            array_values($pedido->only(['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'])));

        $this->assertDatabaseHas('pedido_itens', [
            'pedido_id' => $pedido->id, 'nome' => 'Vestido Floral', 'tamanho' => 'M', 'cor' => 'Preto',
            'quantidade' => 1, 'preco_unitario' => 129.90, 'subtotal' => 129.90,
        ]);
        $this->assertDatabaseHas('pedido_historicos', ['pedido_id' => $pedido->id, 'status' => 'aguardando_pagamento', 'user_id' => $this->cliente->id]);

        // Carrinho bloqueado e estoque intacto (a baixa só acontece no pagamento, 3.31)
        $this->assertSame(Carrinho::EM_PAGAMENTO, $carrinho->fresh()->status);
        $this->assertSame(8, $this->vestido->fresh()->estoque);
        $this->assertSame(12, $this->blusa->fresh()->estoque);
    }

    // RNF.01 da 3.18: cliques repetidos geram um único pedido
    public function test_cliques_repetidos_geram_um_so_pedido(): void
    {
        $this->encherCarrinho();

        $this->actingAs($this->cliente)->post('/pedidos');
        $this->actingAs($this->cliente)->post('/pedidos')->assertRedirect(route('loja.pedidos.show', Pedido::sole()));

        $this->assertSame(1, Pedido::count());
    }

    // CT-3-18-02
    public function test_nao_cria_pedido_com_item_sem_estoque_suficiente(): void
    {
        $this->vestido->update(['estoque' => 2]);
        $carrinho = $this->encherCarrinho([[$this->vestido, 3]]);

        $this->actingAs($this->cliente)->post('/pedidos')
            ->assertRedirect(route('loja.pedidos.confirmar'))
            ->assertSessionHasErrors(['estoque' => 'Vestido Floral (M / Preto): você pediu 3, mas só há 2 disponível(is). Ajuste a quantidade.']);

        $this->assertSame(0, Pedido::count());
        $this->assertSame(Carrinho::ABERTO, $carrinho->fresh()->status);

        $this->actingAs($this->cliente)->get('/pedido/confirmar')->assertSee('só há 2 disponível(is)');
    }

    public function test_nao_cria_pedido_com_produto_inativado_depois(): void
    {
        $this->encherCarrinho();
        $this->blusa->forceFill(['status' => 'inativo'])->save();

        $this->actingAs($this->cliente)->post('/pedidos')->assertSessionHasErrors('estoque');
        $this->assertSame(0, Pedido::count());
    }

    public function test_carrinho_vazio_nao_gera_pedido(): void
    {
        $this->actingAs($this->cliente)->post('/pedidos')->assertRedirect('/carrinho')->assertSessionHas('erro', 'Seu carrinho está vazio.');
        $this->actingAs($this->cliente)->get('/pedido/confirmar')->assertRedirect('/carrinho');
        $this->assertSame(0, Pedido::count());
    }

    public function test_pedido_guarda_preco_e_endereco_do_momento_da_compra(): void
    {
        $this->encherCarrinho();
        $this->actingAs($this->cliente)->post('/pedidos');

        $this->vestido->update(['preco' => 199.90, 'nome' => 'Vestido Novo']);
        $this->cliente->update(['rua' => 'Rua Nova', 'cidade' => 'Maringá']);

        $pedido = Pedido::sole()->load('itens');
        $this->assertSame('219.80', $pedido->total);
        $this->assertSame('Avenida Brasil', $pedido->rua);
        $this->assertSame('Umuarama', $pedido->cidade);
        $this->assertTrue($pedido->itens->contains('nome', 'Vestido Floral'));
    }

    public function test_equipe_e_visitante_nao_criam_pedido(): void
    {
        $this->post('/pedidos')->assertRedirect('/entrar');
        $this->actingAs(User::factory()->admin()->create())->post('/pedidos')->assertForbidden();
    }

    // 3.19 – lista só os próprios pedidos, do mais recente ao mais antigo
    public function test_meus_pedidos_lista_so_os_do_cliente(): void
    {
        $this->encherCarrinho();
        $this->actingAs($this->cliente)->post('/pedidos');
        $meu = Pedido::sole();

        $outro = User::factory()->create();
        $pedidoDoOutro = $this->pedidoDe($outro);

        $this->actingAs($this->cliente)->get('/meus-pedidos')
            ->assertOk()
            ->assertSee($meu->numero())
            ->assertSee('Aguardando Pagamento')
            ->assertDontSee('#'.$pedidoDoOutro->id.'<');
    }

    // CT-3-19-01
    public function test_detalhes_mostram_itens_endereco_pagamento_e_historico(): void
    {
        $this->encherCarrinho();
        $this->actingAs($this->cliente)->post('/pedidos');
        $pedido = Pedido::sole();
        $pedido->forma_pagamento = 'pix';
        $pedido->registrarStatus(Pedido::PAGO);

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$pedido->id}")
            ->assertOk()
            ->assertSee('Vestido Floral — M / Preto')
            ->assertSee('Avenida Brasil, 120 – Apto 32')
            ->assertSee('PIX')
            ->assertSee('Histórico de status')
            ->assertSee('Aguardando Pagamento')
            ->assertSee('Pago');
    }

    // CT-3-19-02
    public function test_nao_ve_pedido_de_outro_cliente_pela_url(): void
    {
        $pedidoDoOutro = $this->pedidoDe(User::factory()->create());

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$pedidoDoOutro->id}")
            ->assertForbidden()
            ->assertDontSee($pedidoDoOutro->rua);
    }

    public function test_carrinho_bloqueado_mostra_link_para_o_pedido(): void
    {
        $this->encherCarrinho();
        $this->actingAs($this->cliente)->post('/pedidos');

        $this->actingAs($this->cliente)->get('/carrinho')
            ->assertSee('está bloqueado para alterações')
            ->assertDontSee('Finalizar compra');
    }

    private function pedidoDe(User $cliente): Pedido
    {
        $pedido = new Pedido;
        $pedido->user_id = $cliente->id;
        $pedido->subtotal = $pedido->total = 59.90;
        foreach (['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $cliente->{$campo};
        }
        $pedido->save();
        $pedido->historico()->create(['status' => Pedido::AGUARDANDO_PAGAMENTO, 'user_id' => $cliente->id]);

        return $pedido;
    }
}
