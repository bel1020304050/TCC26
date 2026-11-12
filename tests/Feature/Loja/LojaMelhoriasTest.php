<?php

namespace Tests\Feature\Loja;

use App\Models\Carrinho;
use App\Models\Categoria;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use App\Support\Rastreio;
use App\Support\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Melhorias na loja: página do produto, cupons disponíveis no carrinho e código de rastreio simulado.
 */
class LojaMelhoriasTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $vestidos;
    private Produto $vestidoM;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vestidos = Categoria::factory()->create(['nome' => 'Vestidos']);
        $this->vestidoM = $this->vestido('M', 'Preto', 8);
    }

    private function vestido(string $tamanho, string $cor, int $estoque): Produto
    {
        return Produto::factory()->create([
            'nome' => 'Vestido Floral', 'categoria_id' => $this->vestidos->id, 'tamanho' => $tamanho, 'cor' => $cor,
            'preco' => 100, 'estoque' => $estoque, 'descricao' => 'Vestido midi de viscose, estampa floral.',
        ]);
    }

    // ---------- Página do produto ----------

    public function test_pagina_do_produto_para_visitante(): void
    {
        $this->get("/produtos/{$this->vestidoM->id}")
            ->assertOk()
            ->assertSee('Vestido Floral')->assertSee('Vestidos')->assertSee('Vestido midi de viscose')
            ->assertSee('R$ 100,00')
            ->assertSee(route('loja.inicio', ['categoria' => $this->vestidos->id]), false) // trilha
            ->assertSee('Entrar para comprar')
            ->assertSee('voltar=%2Fprodutos%2F'.$this->vestidoM->id, false)
            ->assertDontSee('Adicionar ao carrinho');
    }

    public function test_vitrine_leva_a_pagina_do_produto_na_mesma_aba(): void
    {
        $html = $this->get('/')->assertSee(route('loja.produto', $this->vestidoM), false)->getContent();
        $this->assertDoesNotMatchRegularExpression('#href="'.preg_quote(route('loja.produto', $this->vestidoM), '#').'"[^>]*target="_blank"#', $html);
    }

    public function test_produto_inativo_ou_de_categoria_inativa_nao_tem_pagina(): void
    {
        $this->get('/produtos/999999')->assertNotFound();

        $this->vestidoM->forceFill(['status' => Status::INATIVO])->save();
        $this->get("/produtos/{$this->vestidoM->id}")->assertNotFound();

        $outro = $this->vestido('G', 'Azul', 3);
        $this->vestidos->forceFill(['status' => Status::INATIVO])->save();
        $this->get("/produtos/{$outro->id}")->assertNotFound();
    }

    public function test_esgotado_mostra_aviso_sem_botao(): void
    {
        $this->vestidoM->update(['estoque' => 0]);
        $cliente = User::factory()->create();

        $this->actingAs($cliente)->get("/produtos/{$this->vestidoM->id}")
            ->assertOk()->assertSee('Produto indisponível no momento')->assertDontSee('Adicionar ao carrinho');
    }

    // Regra: "Últimas X unidades!" só com estoque de 5 ou menos
    public function test_ultimas_unidades_so_com_estoque_baixo(): void
    {
        $this->vestidoM->update(['estoque' => 6]);
        $this->get("/produtos/{$this->vestidoM->id}")->assertDontSee('Últimas');

        $this->vestidoM->update(['estoque' => 5]);
        $this->get("/produtos/{$this->vestidoM->id}")->assertSee('Últimas 5 unidade(s)!');

        $this->vestidoM->update(['estoque' => 1]);
        $this->get("/produtos/{$this->vestidoM->id}")->assertSee('Últimas 1 unidade(s)!');
    }

    public function test_preco_de_por_com_promocao_automatica(): void
    {
        Promocao::factory()->create(['nome' => 'Semana do Vestido', 'aplicacao' => Promocao::CATEGORIA, 'categoria_id' => $this->vestidos->id, 'valor' => 20]);

        $this->get("/produtos/{$this->vestidoM->id}")
            ->assertSeeInOrder(['de', 'R$ 100,00', 'por R$ 80,00'])->assertSee('-20%')->assertSee('Semana do Vestido');
    }

    // Cada tamanho/cor é um produto: os botões levam à página da outra variação
    public function test_botoes_de_tamanho_e_cor(): void
    {
        $p = $this->vestido('P', 'Preto', 2);
        $g = $this->vestido('G', 'Preto', 0); // esgotado
        $azulM = $this->vestido('M', 'Azul', 4);
        $this->vestido('GG', 'Azul', 4); // tamanho que só existe em azul: não aparece entre os tamanhos do preto

        $html = $this->get("/produtos/{$this->vestidoM->id}")->assertSeeInOrder(['Tamanho: M', '>P<', '>M<', '>G<', 'Cor: Preto', '>Azul<', '>Preto<'], false)->getContent();

        $this->assertStringContainsString(route('loja.produto', $p->id), $html);
        $this->assertStringContainsString(route('loja.produto', $azulM->id), $html); // Azul no mesmo tamanho M
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('loja.produto', $g->id), '#').'"[^>]*esgotada#s', $html);
        $this->assertStringNotContainsString('>GG<', $html);
        $this->assertStringContainsString('aria-current="true"', $html);
    }

    public function test_cliente_adiciona_uma_unidade_e_volta_para_a_pagina(): void
    {
        $cliente = User::factory()->create();
        $pagina = "/produtos/{$this->vestidoM->id}";

        $this->actingAs($cliente)->get($pagina)->assertSee('Adicionar ao carrinho')->assertDontSee('name="quantidade"', false);
        $this->actingAs($cliente)->from($pagina)->post('/carrinho/itens', ['produto_id' => $this->vestidoM->id])->assertRedirect($pagina);

        $this->assertSame(1, (int) Carrinho::doCliente($cliente)->itens()->sum('quantidade'));
    }

    // Dica de cupom: só cupons válidos para este produto
    public function test_dica_de_cupom(): void
    {
        $blusa = Produto::factory()->create(['estoque' => 3]);
        Promocao::factory()->cupom('VESTIDO30')->create(['aplicacao' => Promocao::CATEGORIA, 'categoria_id' => $this->vestidos->id, 'valor' => 30]);
        Promocao::factory()->cupom('BLACK25')->create(['data_inicio' => today()->addDays(10), 'data_fim' => today()->addDays(12)]); // agendado

        $this->get("/produtos/{$this->vestidoM->id}")->assertSee('Use o cupom')->assertSee('VESTIDO30')->assertDontSee('BLACK25');
        $this->get("/produtos/{$blusa->id}")->assertDontSee('Use o cupom');
    }

    // Login com volta para o produto; endereço de fora é ignorado
    public function test_entrar_para_comprar_volta_ao_produto(): void
    {
        $cliente = User::factory()->create(['password' => 'segredo123']);
        $pagina = "/produtos/{$this->vestidoM->id}";

        $this->get('/entrar?voltar='.urlencode($pagina));
        $this->post('/entrar', ['email' => $cliente->email, 'password' => 'segredo123'])->assertRedirect(url($pagina));
        auth()->logout();

        foreach (['//site-malicioso.com', 'https://site-malicioso.com', '/\\site-malicioso.com'] as $fora) {
            $this->flushSession();
            $this->get('/entrar?voltar='.urlencode($fora));
            $destino = $this->post('/entrar', ['email' => $cliente->email, 'password' => 'segredo123'])->headers->get('Location');
            $this->assertStringNotContainsString('site-malicioso', $destino, $fora);
            auth()->logout();
        }
    }

    // ---------- Cupons disponíveis no carrinho ----------

    public function test_carrinho_lista_os_cupons_ativos_e_vigentes(): void
    {
        $cliente = User::factory()->create();
        Carrinho::doCliente($cliente)->itens()->create(['produto_id' => $this->vestidoM->id, 'quantidade' => 1]);

        Promocao::factory()->cupom('MARY20')->create(['valor' => 20, 'data_fim' => today()->addDays(4)]);
        Promocao::factory()->cupom('VESTIDO30')->create(['aplicacao' => Promocao::CATEGORIA, 'categoria_id' => $this->vestidos->id, 'valor' => 30]);
        Promocao::factory()->cupom('DEZ')->create(['tipo' => Promocao::VALOR, 'valor' => 10]);
        Promocao::factory()->cupom('FUTURO')->create(['data_inicio' => today()->addDay(), 'data_fim' => today()->addDays(9)]);
        Promocao::factory()->cupom('VELHO')->create(['data_inicio' => today()->subDays(9), 'data_fim' => today()->subDay()]);
        Promocao::factory()->cupom('DESLIGADO')->create(['status' => Status::INATIVO]);
        Promocao::factory()->create(['nome' => 'Automática geral']); // não é cupom

        $this->actingAs($cliente)->get('/carrinho')
            ->assertSee('Ver cupons disponíveis (3)')
            ->assertSee('MARY20 · 20% em todos os produtos · válido até '.today()->addDays(4)->format('d/m'))
            ->assertSee('VESTIDO30 · 30% em Vestidos')
            ->assertSee('DEZ · R$ 10,00 de desconto em todos os produtos')
            ->assertSee('data-usar-cupom="MARY20"', false)
            ->assertDontSee('FUTURO')->assertDontSee('VELHO')->assertDontSee('DESLIGADO');

        // Com cupom aplicado, a lista some (o campo também)
        $this->actingAs($cliente)->post('/carrinho/cupom', ['cupom' => 'mary20']);
        $this->actingAs($cliente)->get('/carrinho')->assertDontSee('Ver cupons disponíveis');
    }

    // ---------- Código de rastreio ----------

    public function test_digito_verificador_no_padrao_dos_correios(): void
    {
        $this->assertSame(5, Rastreio::digito('12345678')); // exemplo do padrão S10 (EE 12345678 5 ..)
        $codigo = Rastreio::gerar();
        $this->assertMatchesRegularExpression('/^MM\d{9}BR$/', $codigo);
        $this->assertTrue(Rastreio::valido($codigo));
        $this->assertFalse(Rastreio::valido('MM123456780BR'));
    }

    public function test_enviado_gera_rastreio_que_o_cliente_ve(): void
    {
        $cliente = User::factory()->create();
        $atendente = User::factory()->atendente()->create();
        Carrinho::doCliente($cliente)->itens()->create(['produto_id' => $this->vestidoM->id, 'quantidade' => 1]);
        $this->actingAs($cliente)->post('/pedidos');
        $pedido = Pedido::sole();
        $this->actingAs($cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'pix']);
        $pix = Pagamento::sole();
        $this->actingAs($cliente)->post("/meus-pedidos/{$pedido->id}/pagamento/{$pix->id}/simular-pix");

        $this->actingAs($atendente)->patch("/painel/pedidos/{$pedido->id}/status", ['status' => Pedido::EM_SEPARACAO]);
        $this->assertNull($pedido->fresh()->codigo_rastreio);

        $this->actingAs($atendente)->patch("/painel/pedidos/{$pedido->id}/status", ['status' => Pedido::ENVIADO]);
        $codigo = $pedido->fresh()->codigo_rastreio;
        $this->assertTrue(Rastreio::valido($codigo));

        $this->actingAs($cliente)->get("/meus-pedidos/{$pedido->id}")->assertSee($codigo)->assertSee('simulado');
        $this->actingAs($atendente)->get("/painel/pedidos/{$pedido->id}")->assertSee($codigo);
        $this->assertDatabaseHas('pedido_historicos', ['pedido_id' => $pedido->id, 'status' => Pedido::ENVIADO, 'observacao' => "Código de rastreio (simulado): {$codigo}"]);

        // Entregue mantém o mesmo código
        $this->actingAs($atendente)->patch("/painel/pedidos/{$pedido->id}/status", ['status' => Pedido::ENTREGUE]);
        $this->assertSame($codigo, $pedido->fresh()->codigo_rastreio);
    }
}
