<?php

namespace Tests\Feature\Loja;

use App\Models\Carrinho;
use App\Models\Categoria;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carrinho — story 3.30 (apoio).
 */
class CarrinhoTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = User::factory()->create();
    }

    private function produto(array $dados = []): Produto
    {
        return Produto::factory()->create(array_merge(['nome' => 'Vestido Floral', 'tamanho' => 'M', 'cor' => 'Preto', 'preco' => 129.90, 'estoque' => 8], $dados));
    }

    private function adicionar(Produto $produto)
    {
        return $this->actingAs($this->cliente)->from('/')->post('/carrinho/itens', ['produto_id' => $produto->id]);
    }

    public function test_visitante_vai_para_o_login_e_equipe_nao_tem_carrinho(): void
    {
        $this->get('/carrinho')->assertRedirect('/entrar');
        $this->actingAs(User::factory()->funcionario()->create())->get('/carrinho')->assertForbidden();
    }

    // CT-3-30-01 (parte do carrinho; o cupom chega na Fase 8)
    public function test_adiciona_produto_com_quantidade_1_e_mostra_totais(): void
    {
        $vestido = $this->produto();
        $blusa = $this->produto(['nome' => 'Blusa Social', 'tamanho' => 'P', 'cor' => 'Branco', 'preco' => 89.90, 'estoque' => 12]);

        $this->adicionar($vestido)->assertRedirect('/')->assertSessionHas('sucesso');
        $this->adicionar($blusa);

        $carrinho = Carrinho::doCliente($this->cliente);
        $this->assertSame(1, $carrinho->itens()->where('produto_id', $vestido->id)->value('quantidade'));
        $this->assertSame(['subtotal' => 219.8, 'desconto' => 0.0, 'total' => 219.8], $carrinho->totais());

        $this->actingAs($this->cliente)->get('/carrinho')
            ->assertOk()
            ->assertSee('Vestido Floral')
            ->assertSee('Tamanho M · Cor Preto')
            ->assertSee('Disponível: 8')
            ->assertSee('R$ 219,80');
    }

    public function test_adicionar_de_novo_soma_uma_unidade(): void
    {
        $vestido = $this->produto();

        $this->adicionar($vestido);
        $this->adicionar($vestido);

        $this->assertSame(2, Carrinho::doCliente($this->cliente)->itens()->sole()->quantidade);
    }

    public function test_nao_adiciona_produto_inativo_sem_estoque_ou_de_categoria_inativa(): void
    {
        $inativo = $this->produto(['status' => 'inativo']);
        $semEstoque = $this->produto(['nome' => 'Saia', 'estoque' => 0]);
        $categoriaInativa = $this->produto(['nome' => 'Jaqueta', 'categoria_id' => Categoria::factory()->create(['status' => 'inativo'])->id]);

        foreach ([$inativo, $semEstoque, $categoriaInativa] as $produto) {
            $this->adicionar($produto)->assertSessionHas('erro', 'Este produto não está disponível para compra.');
        }

        $this->assertSame(0, Carrinho::doCliente($this->cliente)->itens()->count());
    }

    // CT-3-30-02
    public function test_nao_passa_do_estoque_disponivel(): void
    {
        $vestido = $this->produto(['estoque' => 8]);
        $this->adicionar($vestido);
        $item = Carrinho::doCliente($this->cliente)->itens()->sole();

        $this->actingAs($this->cliente)->from('/carrinho')->patch("/carrinho/itens/{$item->id}", ['quantidade' => 9])
            ->assertSessionHas('erro', 'Quantidade indisponível: só há 8 unidade(s) de Vestido Floral (M / Preto).');
        $this->assertSame(1, $item->fresh()->quantidade);

        $this->actingAs($this->cliente)->patch("/carrinho/itens/{$item->id}", ['quantidade' => 8]);
        $this->assertSame(8, $item->fresh()->quantidade);

        // Pela vitrine também não passa
        $this->adicionar($vestido)->assertSessionHas('erro');
        $this->assertSame(8, $item->fresh()->quantidade);
    }

    public function test_diminui_e_remove_item(): void
    {
        $vestido = $this->produto();
        $this->adicionar($vestido);
        $this->adicionar($vestido);
        $item = Carrinho::doCliente($this->cliente)->itens()->sole();

        $this->actingAs($this->cliente)->patch("/carrinho/itens/{$item->id}", ['quantidade' => 1]);
        $this->assertSame(1, $item->fresh()->quantidade);

        $this->actingAs($this->cliente)->patch("/carrinho/itens/{$item->id}", ['quantidade' => 0])->assertSessionHasErrors('quantidade');

        $this->actingAs($this->cliente)->delete("/carrinho/itens/{$item->id}")->assertSessionHas('sucesso');
        $this->assertModelMissing($item);
    }

    public function test_cliente_nao_mexe_no_carrinho_de_outro(): void
    {
        $this->adicionar($this->produto());
        $item = Carrinho::doCliente($this->cliente)->itens()->sole();
        $outro = User::factory()->create();

        $this->actingAs($outro)->patch("/carrinho/itens/{$item->id}", ['quantidade' => 2])->assertForbidden();
        $this->actingAs($outro)->delete("/carrinho/itens/{$item->id}")->assertForbidden();
        $this->assertModelExists($item);
    }

    public function test_carrinho_em_pagamento_fica_bloqueado(): void
    {
        $vestido = $this->produto();
        $this->adicionar($vestido);
        $carrinho = Carrinho::doCliente($this->cliente);
        $carrinho->status = Carrinho::EM_PAGAMENTO;
        $carrinho->save();
        $item = $carrinho->itens()->sole();

        $this->adicionar($this->produto(['nome' => 'Outro']))->assertSessionHas('erro');
        $this->actingAs($this->cliente)->patch("/carrinho/itens/{$item->id}", ['quantidade' => 2])->assertSessionHas('erro');
        $this->actingAs($this->cliente)->delete("/carrinho/itens/{$item->id}")->assertSessionHas('erro');

        $this->assertSame(1, $item->fresh()->quantidade);
        $this->assertSame(1, $carrinho->itens()->count());
    }

    public function test_preco_vem_do_servidor_e_acompanha_o_produto(): void
    {
        $vestido = $this->produto(['preco' => 100]);
        $this->actingAs($this->cliente)->post('/carrinho/itens', ['produto_id' => $vestido->id, 'preco' => 1]);

        $vestido->update(['preco' => 120]);

        $this->assertSame(120.0, Carrinho::doCliente($this->cliente)->totais()['total']);
    }

    public function test_vitrine_mostra_botao_certo_para_cada_perfil(): void
    {
        $this->produto();

        $this->get('/')->assertSee('Entrar para comprar')->assertDontSee('Adicionar ao carrinho');
        $this->actingAs($this->cliente)->get('/')->assertSee('Adicionar ao carrinho');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertDontSee('Adicionar ao carrinho')->assertDontSee('Entrar para comprar');
    }
}
