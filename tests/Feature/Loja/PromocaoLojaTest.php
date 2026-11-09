<?php

namespace Tests\Feature\Loja;

use App\Models\Carrinho;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Promoções do lado do cliente: selo na vitrine, cupom no carrinho e desconto no pedido.
 */
class PromocaoLojaTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private Categoria $saias;

    private Produto $saia;

    private Produto $vestido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = User::factory()->create();
        $this->saias = Categoria::factory()->create(['nome' => 'Saias']);
        $this->saia = Produto::factory()->create(['nome' => 'Saia Midi', 'categoria_id' => $this->saias->id, 'preco' => 119.90, 'estoque' => 6]);
        $this->vestido = Produto::factory()->create(['nome' => 'Vestido Floral', 'preco' => 100.00, 'estoque' => 8]);
    }

    private function carrinho(): Carrinho
    {
        $carrinho = Carrinho::doCliente($this->cliente);
        $carrinho->itens()->create(['produto_id' => $this->saia->id, 'quantidade' => 1]);
        $carrinho->itens()->create(['produto_id' => $this->vestido->id, 'quantidade' => 1]);

        return $carrinho;
    }

    private function aplicar(string $codigo)
    {
        return $this->actingAs($this->cliente)->from('/carrinho')->post('/carrinho/cupom', ['cupom' => $codigo]);
    }

    public function test_vitrine_mostra_selo_e_preco_da_promocao_automatica(): void
    {
        Promocao::factory()->create(['nome' => 'Queima de Saias', 'valor' => 15, 'aplicacao' => 'categoria', 'categoria_id' => $this->saias->id]);
        Promocao::factory()->cupom('MARY20')->create(['valor' => 20]); // cupom não aparece na vitrine

        $this->get('/')
            ->assertSee('-15%')
            ->assertSee('R$ 101,92') // 119,90 - 15%
            ->assertDontSee('-20%');
    }

    public function test_vitrine_nao_mostra_promocao_inativa_ou_fora_do_periodo(): void
    {
        Promocao::factory()->create(['valor' => 15, 'status' => 'inativo']);
        Promocao::factory()->create(['valor' => 25, 'data_inicio' => today()->addDay(), 'data_fim' => today()->addDays(5)]);

        $this->get('/')->assertDontSee('-15%')->assertDontSee('-25%');
    }

    // CT-3-30-01 (completo, agora com cupom)
    public function test_aplica_cupom_e_atualiza_totais(): void
    {
        Promocao::factory()->cupom('MARY20')->create(['nome' => 'Semana Mary', 'valor' => 20]);
        $carrinho = $this->carrinho();

        $this->aplicar('mary20')->assertSessionHas('sucesso', 'Cupom aplicado: Semana Mary (20%).');

        $totais = $carrinho->fresh()->totais();
        $this->assertSame(219.9, $totais['subtotal']);
        $this->assertSame(43.98, $totais['desconto']);
        $this->assertSame(175.92, $totais['total']);

        $this->actingAs($this->cliente)->get('/carrinho')->assertSee('MARY20')->assertSee('- R$ 43,98')->assertSee('R$ 175,92');
    }

    // CT-3-25-02
    public function test_cupom_inativo_nao_e_aceito(): void
    {
        Promocao::factory()->cupom('MARY20')->create(['status' => 'inativo']);
        $carrinho = $this->carrinho();

        $this->aplicar('MARY20')->assertSessionHas('erro', 'O cupom MARY20 não está disponível.');

        $this->assertNull($carrinho->fresh()->cupom);
        $this->assertSame(0.0, $carrinho->fresh()->totais()['desconto']);
    }

    public function test_cupom_inativado_depois_sai_do_carrinho_com_aviso(): void
    {
        $promocao = Promocao::factory()->cupom('MARY20')->create();
        $carrinho = $this->carrinho();
        $this->aplicar('MARY20');

        $promocao->status = 'inativo';
        $promocao->save();

        $this->actingAs($this->cliente)->get('/carrinho')->assertSee('O cupom MARY20 não está mais disponível e foi removido do seu carrinho.');
        $this->assertNull($carrinho->fresh()->cupom);
    }

    public function test_descontos_nao_se_somam_vale_o_maior(): void
    {
        // Automática 15% só em Saias (= 17,99) x cupom 20% geral (= 43,98): vale o cupom
        Promocao::factory()->create(['nome' => 'Queima de Saias', 'valor' => 15, 'aplicacao' => 'categoria', 'categoria_id' => $this->saias->id]);
        Promocao::factory()->cupom('MARY20')->create(['nome' => 'Semana Mary', 'valor' => 20]);
        $carrinho = $this->carrinho();

        $this->assertSame(17.99, $carrinho->totais()['desconto']); // sem cupom: só a automática

        $this->aplicar('MARY20');
        $totais = $carrinho->fresh()->totais();
        $this->assertSame(43.98, $totais['desconto']);
        $this->assertSame('Semana Mary', $totais['promocao']->nome);
    }

    public function test_cupom_menor_que_a_automatica_avisa_que_vale_o_maior(): void
    {
        Promocao::factory()->create(['nome' => 'Queima Geral', 'valor' => 30]);
        Promocao::factory()->cupom('PEQUENO')->create(['valor' => 5]);
        $this->carrinho();

        $this->aplicar('PEQUENO')->assertSessionHas('sucesso', 'Cupom PEQUENO válido, mas a promoção Queima Geral dá um desconto maior. Só o maior desconto é aplicado.');
    }

    public function test_valor_fixo_nao_deixa_total_negativo(): void
    {
        Promocao::factory()->cupom('DEZ')->create(['tipo' => 'valor', 'valor' => 500, 'aplicacao' => 'categoria', 'categoria_id' => $this->saias->id]);
        $carrinho = $this->carrinho();
        $this->aplicar('DEZ');

        $totais = $carrinho->fresh()->totais();
        $this->assertSame(119.9, $totais['desconto']); // limitado ao valor das saias
        $this->assertSame(100.0, $totais['total']);
    }

    public function test_pedido_guarda_desconto_e_cupom_e_nao_muda_se_a_promocao_mudar(): void
    {
        $promocao = Promocao::factory()->cupom('MARY20')->create(['valor' => 20]);
        $this->carrinho();
        $this->aplicar('MARY20');

        $this->actingAs($this->cliente)->post('/pedidos');
        $pedido = Pedido::sole();
        $this->assertSame('43.98', $pedido->desconto);
        $this->assertSame('175.92', $pedido->total);
        $this->assertSame('MARY20', $pedido->cupom);
        $this->assertSame($promocao->id, $pedido->promocao_id);

        $promocao->valor = 10;
        $promocao->status = 'inativo';
        $promocao->save();
        $this->assertSame('175.92', $pedido->fresh()->total);

        // O pagamento cobra o valor congelado
        $this->actingAs($this->cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'pix']);
        $this->assertSame('175.92', $pedido->pagamentos()->sole()->valor);
    }

    public function test_remove_cupom(): void
    {
        Promocao::factory()->cupom('MARY20')->create();
        $carrinho = $this->carrinho();
        $this->aplicar('MARY20');

        $this->actingAs($this->cliente)->delete('/carrinho/cupom')->assertSessionHas('sucesso', 'Cupom removido.');
        $this->assertNull($carrinho->fresh()->cupom);
    }
}
