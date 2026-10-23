<?php

namespace Tests\Feature\Loja;

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes da story 3.2 – Visualizar e Pesquisar Produtos.
 */
class VitrineTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $saias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saias = Categoria::factory()->create(['nome' => 'Saias']);
    }

    public function test_vitrine_abre_sem_login(): void // RNF.02 da 3.2
    {
        $this->get('/')->assertOk()->assertSee('Pesquisar produtos');
    }

    // CT-3-2-01: pesquisa ignora maiúsculas/minúsculas e acha parte do nome
    public function test_pesquisa_encontra_produto(): void
    {
        Produto::factory()->create(['nome' => 'Saia Midi', 'categoria_id' => $this->saias->id, 'tamanho' => 'M', 'cor' => 'Bege', 'preco' => 119.90, 'estoque' => 6]);
        Produto::factory()->create(['nome' => 'Blusa Básica', 'categoria_id' => $this->saias->id, 'estoque' => 5]);

        $this->get('/?busca=SAIA')
            ->assertOk()
            ->assertSee('Saia Midi')
            ->assertSee('R$ 119,90')
            ->assertSee('Bege')
            ->assertDontSee('Blusa Básica');
    }

    // CT-3-2-02
    public function test_pesquisa_sem_resultado(): void
    {
        $this->get('/?busca=inexistente')->assertSee('Nenhum produto encontrado');
    }

    public function test_so_mostra_produtos_ativos_com_estoque_e_categoria_ativa(): void
    {
        $jaquetas = Categoria::factory()->inativa()->create(['nome' => 'Jaquetas']);

        Produto::factory()->create(['nome' => 'Saia Visivel', 'categoria_id' => $this->saias->id, 'estoque' => 3]);
        Produto::factory()->create(['nome' => 'Saia Sem Estoque', 'categoria_id' => $this->saias->id, 'estoque' => 0]);
        Produto::factory()->inativo()->create(['nome' => 'Saia Inativa', 'categoria_id' => $this->saias->id, 'estoque' => 5]);
        Produto::factory()->create(['nome' => 'Jaqueta Escondida', 'categoria_id' => $jaquetas->id, 'estoque' => 5]);

        $this->get('/')
            ->assertSee('Saia Visivel')
            ->assertDontSee('Saia Sem Estoque')
            ->assertDontSee('Saia Inativa')
            ->assertDontSee('Jaqueta Escondida')
            ->assertDontSee('Jaquetas'); // categoria inativa fora do filtro
    }

    public function test_filtros_de_categoria_tamanho_e_cor(): void
    {
        $vestidos = Categoria::factory()->create(['nome' => 'Vestidos']);
        Produto::factory()->create(['nome' => 'Saia Jeans', 'categoria_id' => $this->saias->id, 'tamanho' => '38', 'cor' => 'Azul', 'estoque' => 2]);
        Produto::factory()->create(['nome' => 'Vestido Floral', 'categoria_id' => $vestidos->id, 'tamanho' => 'M', 'cor' => 'Preto', 'estoque' => 2]);

        $this->get("/?categoria={$vestidos->id}")->assertSee('Vestido Floral')->assertDontSee('Saia Jeans');
        $this->get('/?tamanho=38')->assertSee('Saia Jeans')->assertDontSee('Vestido Floral');
        $this->get('/?cor=Preto')->assertSee('Vestido Floral')->assertDontSee('Saia Jeans');
    }

    public function test_ordenacao_por_preco(): void
    {
        Produto::factory()->create(['nome' => 'Saia Cara', 'categoria_id' => $this->saias->id, 'preco' => 300, 'estoque' => 1]);
        Produto::factory()->create(['nome' => 'Saia Barata', 'categoria_id' => $this->saias->id, 'preco' => 50, 'estoque' => 1]);

        $this->get('/?ordem=menor_preco')->assertSeeInOrder(['Saia Barata', 'Saia Cara']);
        $this->get('/?ordem=maior_preco')->assertSeeInOrder(['Saia Cara', 'Saia Barata']);
    }

    public function test_pagina_com_no_maximo_20_produtos(): void // RNF.01 da 3.2
    {
        Produto::factory()->count(25)->create(['categoria_id' => $this->saias->id, 'estoque' => 1]);

        $resposta = $this->get('/');
        $this->assertCount(20, $resposta->viewData('produtos'));
        $resposta->assertSee('25 produto(s) encontrado(s)');
    }

    public function test_produto_sem_imagem_usa_imagem_padrao(): void
    {
        Produto::factory()->create(['categoria_id' => $this->saias->id, 'estoque' => 1, 'imagem' => null]);

        $this->get('/')->assertSee('img/produto-sem-imagem.svg');
    }
}
