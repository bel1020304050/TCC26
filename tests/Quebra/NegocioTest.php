<?php

namespace Tests\Quebra;

use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bateria 4: regras de negócio no limite (sequenciais). A concorrência de verdade roda fora, com requisições em paralelo.
 */
class NegocioTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;
    private User $admin;
    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->produto = Produto::factory()->create(['preco' => 100, 'estoque' => 5]);
    }

    private function pedidoCom(int $qtd = 1, ?Produto $produto = null): Pedido
    {
        Carrinho::doCliente($this->cliente)->itens()->create(['produto_id' => ($produto ?? $this->produto)->id, 'quantidade' => $qtd]);
        $this->actingAs($this->cliente)->post('/pedidos');

        return Pedido::latest('id')->first();
    }

    private function pagarBoleto(Pedido $pedido): Pagamento
    {
        $this->actingAs($this->cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'boleto']);

        return Pagamento::where('pedido_id', $pedido->id)->latest('id')->first();
    }

    // Desconto em reais maior que o total não pode deixar o pedido negativo
    public function test_desconto_maior_que_o_total_nao_deixa_total_negativo(): void
    {
        Promocao::factory()->create(['modo' => Promocao::AUTOMATICA, 'tipo' => Promocao::VALOR, 'valor' => 500, 'codigo' => null]);
        $pedido = $this->pedidoCom();
        $this->assertGreaterThanOrEqual(0, (float) $pedido->total, "total = {$pedido->total}");
        $this->assertLessThanOrEqual((float) $pedido->subtotal, (float) $pedido->desconto);
    }

    // Cupom que vence entre aplicar e finalizar não pode dar desconto
    public function test_cupom_vencido_depois_de_aplicado_nao_da_desconto(): void
    {
        $cupom = Promocao::factory()->create(['modo' => Promocao::CUPOM, 'codigo' => 'TESTE10', 'tipo' => Promocao::PERCENTUAL, 'valor' => 10]);
        Carrinho::doCliente($this->cliente)->itens()->create(['produto_id' => $this->produto->id, 'quantidade' => 1]);
        $this->actingAs($this->cliente)->post('/carrinho/cupom', ['cupom' => 'teste10'])->assertSessionHas('sucesso');

        $cupom->update(['data_fim' => now()->subDay(), 'data_inicio' => now()->subDays(5)]);
        $this->actingAs($this->cliente)->post('/pedidos'); // sem passar pelo carrinho de novo
        $pedido = Pedido::latest('id')->first();

        $this->assertSame('0.00', $pedido?->desconto, 'O cupom vencido ainda deu desconto');
    }

    public function test_quantidade_maluca_no_carrinho(): void
    {
        $item = Carrinho::doCliente($this->cliente)->itens()->create(['produto_id' => $this->produto->id, 'quantidade' => 1]);
        foreach (['0', '-3', '1000', '2.5', 'abc'] as $q) {
            $this->actingAs($this->cliente)->patch("/carrinho/itens/{$item->id}", ['quantidade' => $q]);
        }
        $this->assertSame(1, $item->fresh()->quantidade);

        // Mais do que o estoque (5): recusa com mensagem e mantém a quantidade
        $this->actingAs($this->cliente)->patch("/carrinho/itens/{$item->id}", ['quantidade' => 50])->assertSessionHas('erro');
        $this->assertSame(1, $item->fresh()->quantidade);
    }

    // Adicionar o mesmo produto muitas vezes não passa do estoque
    public function test_adicionar_muitas_vezes_nao_passa_do_estoque(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->cliente)->post('/carrinho/itens', ['produto_id' => $this->produto->id]);
        }
        $this->assertLessThanOrEqual(5, Carrinho::doCliente($this->cliente)->itens()->sum('quantidade'));
    }

    public function test_produto_inativo_ou_inexistente_nao_entra_no_carrinho(): void
    {
        $inativo = Produto::factory()->create(['estoque' => 3]);
        $inativo->forceFill(['status' => 'inativo'])->save();
        $this->actingAs($this->cliente)->post('/carrinho/itens', ['produto_id' => $inativo->id]);
        $this->actingAs($this->cliente)->post('/carrinho/itens', ['produto_id' => 999999]);
        $semEstoque = Produto::factory()->create(['estoque' => 0]);
        $this->actingAs($this->cliente)->post('/carrinho/itens', ['produto_id' => $semEstoque->id]);
        $this->assertSame(0, Carrinho::doCliente($this->cliente)->itens()->count());
    }

    // Confirmar o mesmo boleto duas vezes baixa o estoque uma vez só
    public function test_confirmar_boleto_duas_vezes(): void
    {
        $pedido = $this->pedidoCom(2);
        $boleto = $this->pagarBoleto($pedido);
        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto");
        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto");
        $this->assertSame(3, $this->produto->fresh()->estoque);
        $this->assertSame(Pedido::PAGO, $pedido->fresh()->status);
    }

    // Cancelar duas vezes devolve o estoque uma vez só
    public function test_cancelar_pedido_pago_duas_vezes(): void
    {
        $pedido = $this->pedidoCom(2);
        $boleto = $this->pagarBoleto($pedido);
        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto");
        $this->actingAs($this->admin)->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => 'Cliente desistiu da compra']);
        $this->actingAs($this->admin)->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => 'Cliente desistiu da compra']);
        $this->assertSame(5, $this->produto->fresh()->estoque);
        $this->assertSame(1, Pagamento::where('pedido_id', $pedido->id)->where('status', Pagamento::ESTORNADO)->count());
    }

    // Boleto de pedido cancelado não pode ser confirmado depois
    public function test_boleto_de_pedido_cancelado_nao_aprova(): void
    {
        $pedido = $this->pedidoCom(1);
        $boleto = $this->pagarBoleto($pedido);
        $this->actingAs($this->cliente)->patch("/meus-pedidos/{$pedido->id}/cancelar");
        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto");
        $this->assertSame(Pedido::CANCELADO, $pedido->fresh()->status);
        $this->assertSame(5, $this->produto->fresh()->estoque);
    }

    // Regra: o pedido já feito vale, mesmo se a loja inativar o produto antes do pagamento (a venda já foi aceita)
    public function test_produto_inativado_antes_do_pagamento(): void
    {
        $pedido = $this->pedidoCom(1);
        $this->produto->forceFill(['status' => 'inativo'])->save();
        $boleto = $this->pagarBoleto($pedido);
        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto");

        $this->assertSame(Pedido::PAGO, $pedido->fresh()->status);
        $this->assertSame(Pagamento::APROVADO, $boleto->fresh()->status);
        $this->assertSame(4, $this->produto->fresh()->estoque);
    }

    // Último item: dois pedidos aguardando, só um pode ser aprovado
    public function test_ultima_peca_com_dois_pedidos(): void
    {
        $peca = Produto::factory()->create(['estoque' => 1, 'preco' => 50]);
        $pedidoA = $this->pedidoCom(1, $peca);
        $boletoA = $this->pagarBoleto($pedidoA);

        $outro = User::factory()->create();
        Carrinho::doCliente($outro)->itens()->create(['produto_id' => $peca->id, 'quantidade' => 1]);
        $this->actingAs($outro)->post('/pedidos');
        $pedidoB = Pedido::latest('id')->first();
        $this->actingAs($outro)->post("/meus-pedidos/{$pedidoB->id}/pagamento", ['forma' => 'boleto']);
        $boletoB = Pagamento::where('pedido_id', $pedidoB->id)->sole();

        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boletoA->id}/confirmar-boleto");
        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boletoB->id}/confirmar-boleto");

        $this->assertSame(0, $peca->fresh()->estoque);
        $this->assertSame(1, Pedido::whereIn('id', [$pedidoA->id, $pedidoB->id])->where('status', Pedido::PAGO)->count());
    }

    // Avançar status pulando etapas ou voltando
    public function test_status_nao_pula_nem_volta(): void
    {
        $pedido = $this->pedidoCom();
        $boleto = $this->pagarBoleto($pedido);
        $this->actingAs($this->admin)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto");
        foreach (['entregue', 'aguardando_pagamento', 'cancelado', 'inventado', ''] as $s) {
            $this->actingAs($this->admin)->patch("/painel/pedidos/{$pedido->id}/status", ['status' => $s]);
        }
        $this->assertSame(Pedido::PAGO, $pedido->fresh()->status);
    }

    // Regra: não dá para encerrar a conta com pedido em andamento
    public function test_encerrar_conta_com_pedido_pendente(): void
    {
        $this->cliente->update(['password' => 'segredo123']);
        $pedido = $this->pedidoCom();
        $this->pagarBoleto($pedido);

        $this->actingAs($this->cliente)->delete('/minha-conta', ['senha_encerrar' => 'segredo123'])
            ->assertSessionHas('erro', fn ($m) => str_contains($m, 'pedido(s) em andamento'));

        $this->assertSame('ativo', $this->cliente->fresh()->status);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $pedido->fresh()->status);
    }

    // Promoção com datas e valores no limite
    public function test_promocao_no_limite(): void
    {
        $base = ['nome' => 'X', 'modo' => 'automatica', 'tipo' => 'percentual', 'aplicacao' => 'geral', 'status' => 'ativo'];
        $this->actingAs($this->admin)->post('/painel/promocoes', $base + ['valor' => 10, 'data_inicio' => '2026-12-10', 'data_fim' => '2026-12-01'])->assertSessionHasErrors('data_fim');
        $this->actingAs($this->admin)->post('/painel/promocoes', $base + ['valor' => 0, 'data_inicio' => '2026-12-01', 'data_fim' => '2026-12-10'])->assertSessionHasErrors('valor');
        $this->actingAs($this->admin)->post('/painel/promocoes', $base + ['valor' => 101, 'data_inicio' => '2026-12-01', 'data_fim' => '2026-12-10'])->assertSessionHasErrors('valor');
        $this->actingAs($this->admin)->post('/painel/promocoes', $base + ['valor' => -5, 'data_inicio' => '2026-12-01', 'data_fim' => '2026-12-10'])->assertSessionHasErrors('valor');
        $this->assertSame(0, Promocao::count());
    }

    // Produto com preço/estoque no limite
    public function test_produto_no_limite(): void
    {
        $base = ['nome' => 'Blusa', 'categoria_id' => $this->produto->categoria_id, 'fornecedor_id' => $this->produto->fornecedor_id,
            'tamanho' => 'M', 'cor' => 'Azul', 'descricao' => 'x', 'status' => 'ativo'];
        // Valores inválidos: recusados no campo certo
        foreach ([['preco' => '0', 'estoque' => '1', 'campo' => 'preco'], ['preco' => '-10', 'estoque' => '1', 'campo' => 'preco'],
            ['preco' => '99999999999', 'estoque' => '1', 'campo' => 'preco'], ['preco' => '10', 'estoque' => '-1', 'campo' => 'estoque'],
            ['preco' => '10', 'estoque' => '1.5', 'campo' => 'estoque']] as $v) {
            $this->actingAs($this->admin)->post('/painel/produtos', $base + $v)->assertSessionHasErrors($v['campo']);
        }
        $this->assertFalse(Produto::where('nome', 'Blusa')->exists());

        // Preço com vírgula (jeito brasileiro) é aceito e salvo com ponto
        $this->actingAs($this->admin)->post('/painel/produtos', $base + ['preco' => '10,50', 'estoque' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('10.50', Produto::where('nome', 'Blusa')->value('preco'));
    }
}
