<?php

namespace Tests\Feature\Loja;

use App\Models\Auditoria;
use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AsaasFalso;
use Tests\TestCase;

/**
 * Fase 18: pagamento pelo Asaas (sandbox), com o Asaas imitado (AsaasFalso) — nada sai para a internet.
 */
class AsaasTest extends TestCase
{
    use RefreshDatabase;

    private AsaasFalso $asaas;

    private User $cliente;

    private Produto $vestido;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asaas = AsaasFalso::ligar();
        $this->cliente = User::factory()->create(['cpf' => '529.982.247-25']);
        $this->vestido = Produto::factory()->create(['preco' => 100, 'estoque' => 5]);
        $this->pedido = $this->novoPedido();
    }

    private function novoPedido(): Pedido
    {
        Carrinho::doCliente($this->cliente)->itens()->create(['produto_id' => $this->vestido->id, 'quantidade' => 2]);
        $this->actingAs($this->cliente)->post('/pedidos');

        return Pedido::latest('id')->first();
    }

    private function pagar(string $forma, ?Pedido $pedido = null)
    {
        $pedido ??= $this->pedido;

        return $this->actingAs($this->cliente)->from("/meus-pedidos/{$pedido->id}/pagamento")
            ->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => $forma]);
    }

    private function url(Pagamento $pagamento, string $acao): string
    {
        return "/meus-pedidos/{$this->pedido->id}/pagamento/{$pagamento->id}/{$acao}";
    }

    public function test_pix_mostra_qr_code_e_copia_e_cola_reais_do_asaas(): void
    {
        $this->pagar('pix')->assertRedirect("/meus-pedidos/{$this->pedido->id}/pagamento");

        $pagamento = Pagamento::sole();
        $this->assertSame(Pagamento::PENDENTE, $pagamento->status);
        $this->assertStringStartsWith('pay_', $pagamento->referencia);
        $this->assertStringContainsString('PIX-ASAAS', $pagamento->pix_codigo);
        $this->assertSame(base64_encode('PNG-FALSO'), $pagamento->pix_qr);

        // A cobrança foi criada com o valor do pedido e ligada a ele
        $cobranca = $this->asaas->ultimaCobranca();
        $this->assertSame(200.0, (float) $cobranca['value']);
        $this->assertSame('PIX', $cobranca['billingType']);
        $this->assertSame("pedido-{$this->pedido->id}-pagamento-{$pagamento->id}", $cobranca['externalReference']);

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->assertSee('Pagamento pelo Asaas')
            ->assertDontSee('nenhum dinheiro é movimentado')
            ->assertSee('data:image/png;base64,'.base64_encode('PNG-FALSO'), false)
            ->assertSee('esta página atualiza sozinha')
            ->assertSee(json_encode(route('loja.pagamento.status', [$this->pedido, $pagamento])), false)
            ->assertDontSee('Verificar pagamento')
            ->assertSee('Simular pagamento (sandbox)');
    }

    public function test_cliente_do_asaas_e_criado_uma_vez_e_reaproveitado(): void
    {
        $this->pagar('pix');
        $this->pagar('boleto'); // segunda tentativa no mesmo pedido

        $this->assertCount(1, $this->asaas->clientes);
        $this->assertSame(array_key_first($this->asaas->clientes), $this->cliente->fresh()->asaas_cliente_id);
        $this->assertSame('52998224725', reset($this->asaas->clientes)['cpfCnpj']);
        $this->assertTrue(reset($this->asaas->clientes)['notificationDisabled']);
    }

    public function test_cliente_apagado_no_asaas_e_criado_de_novo(): void
    {
        $this->cliente->forceFill(['asaas_cliente_id' => 'cus_que_nao_existe'])->save();

        $this->pagar('pix')->assertSessionHas('sucesso');

        $this->assertNotSame('cus_que_nao_existe', $this->cliente->fresh()->asaas_cliente_id);
        $this->assertSame(Pagamento::PENDENTE, Pagamento::sole()->status);
    }

    // Consulta automática que a tela faz a cada 5 segundos
    private function consultar(Pagamento $pagamento, ?User $quem = null)
    {
        return $this->actingAs($quem ?? $this->cliente)->getJson($this->url($pagamento, 'status'));
    }

    public function test_consulta_automatica_de_pagamento_pendente_nao_muda_nada(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();

        $this->consultar($pagamento)->assertOk()->assertExactJson(['status' => Pagamento::PENDENTE]);

        $this->assertSame(Pagamento::PENDENTE, $pagamento->fresh()->status);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
    }

    public function test_pix_pago_no_asaas_e_verificado_vira_pedido_pago(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();
        $this->asaas->mudarStatus($pagamento->referencia, 'RECEIVED'); // cliente pagou no banco

        $this->consultar($pagamento)->assertOk()
            ->assertJson(['status' => Pagamento::APROVADO, 'ir_para' => url("/meus-pedidos/{$this->pedido->id}/concluido")]);

        $this->assertSame(Pedido::PAGO, $this->pedido->fresh()->status);
        $this->assertSame(3, $this->vestido->fresh()->estoque);
    }

    public function test_simular_pix_no_sandbox_confirma_no_asaas_e_aprova(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();

        $this->actingAs($this->cliente)->post($this->url($pagamento, 'simular-pix'))
            ->assertRedirect("/meus-pedidos/{$this->pedido->id}/concluido");

        $this->assertContains("POST /sandbox/payment/{$pagamento->referencia}/confirm", $this->asaas->chamadas);
        $this->assertSame(Pagamento::APROVADO, $pagamento->fresh()->status);
        $this->assertSame(Pedido::PAGO, $this->pedido->fresh()->status);
    }

    public function test_simular_e_bloqueado_fora_do_sandbox(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();
        config(['mary.pagamento.asaas.url' => 'https://api.asaas.com/v3']); // produção

        $this->actingAs($this->cliente)->from("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->post($this->url($pagamento, 'simular-pix'))->assertSessionHas('erro', 'Simular pagamento só é permitido no sandbox do Asaas.');

        $this->assertSame(Pagamento::PENDENTE, $pagamento->fresh()->status);
    }

    public function test_boleto_tem_linha_digitavel_pdf_e_gerente_confirma_no_sandbox(): void
    {
        $this->pagar('boleto');
        $pagamento = Pagamento::sole();
        $this->assertSame('00190000090123456789012345678901234567890123', $pagamento->boleto_linha);
        $this->assertStringContainsString('/b/pdf/', $pagamento->link_pagamento);
        $this->assertNotNull($pagamento->boleto_vencimento);

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")->assertSee('Abrir boleto (PDF)');

        $gerente = User::factory()->gerente()->create();
        $this->actingAs($gerente)->post("/painel/pagamentos/{$pagamento->id}/confirmar-boleto")->assertSessionHas('sucesso');

        $this->assertSame('RECEIVED', $this->asaas->cobrancas[$pagamento->referencia]['status']);
        $this->assertSame(Pedido::PAGO, $this->pedido->fresh()->status);
        $this->assertSame($gerente->id, $pagamento->fresh()->confirmado_por);
    }

    public function test_cartao_vai_para_a_fatura_do_asaas_sem_dados_do_cartao(): void
    {
        // No Asaas a loja não pede o cartão: só a forma
        $this->pagar('cartao')->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        $pagamento = Pagamento::sole();
        $this->assertSame(Pagamento::PENDENTE, $pagamento->status);
        $this->assertSame("https://sandbox.asaas.com/i/{$pagamento->referencia}", $pagamento->link_pagamento);
        $this->assertSame('CREDIT_CARD', $this->asaas->ultimaCobranca()['billingType']);

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->assertSee('Pagar com cartão no Asaas')
            ->assertSee('target="_blank"', false)
            ->assertSee('esta página atualiza sozinha')
            ->assertDontSee('name="cartao_numero"', false);

        // Cliente pagou na fatura: a verificação traz bandeira e final, nada mais
        $this->asaas->mudarStatus($pagamento->referencia, 'CONFIRMED', ['creditCard' => ['creditCardNumber' => '4444', 'creditCardBrand' => 'VISA']]);
        $this->consultar($pagamento)->assertJson(['ir_para' => url("/meus-pedidos/{$this->pedido->id}/concluido")]);

        $pagamento->refresh();
        $this->assertSame(Pagamento::APROVADO, $pagamento->status);
        $this->assertSame('Visa final 4444', $pagamento->detalhe());
    }

    public function test_cliente_sem_cpf_recebe_mensagem_clara(): void
    {
        $this->cliente->forceFill(['cpf' => null])->save();

        $this->pagar('pix')->assertSessionHas('erro', fn ($m) => str_contains($m, 'informe o seu CPF em Minha conta'));

        $this->assertSame(Pagamento::RECUSADO, Pagamento::sole()->status);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
    }

    public function test_asaas_fora_do_ar_nao_derruba_a_tela(): void
    {
        $this->asaas->foraDoAr = true;

        $this->pagar('pix')->assertSessionHas('erro', fn ($m) => str_contains($m, 'temporariamente indisponível'));
        $this->assertSame(Pagamento::RECUSADO, Pagamento::sole()->status);
    }

    public function test_chave_errada_mostra_a_mensagem_do_asaas(): void
    {
        config(['mary.pagamento.asaas.chave' => 'chave-errada']);

        $this->pagar('pix')->assertSessionHas('erro', fn ($m) => str_contains($m, 'A chave de API fornecida é inválida'));
    }

    public function test_conta_sem_chave_pix_ainda_gera_a_cobranca_com_a_fatura(): void
    {
        $this->asaas->semChavePix = true;

        $this->pagar('pix');

        $pagamento = Pagamento::sole();
        $this->assertSame(Pagamento::PENDENTE, $pagamento->status);
        $this->assertNull($pagamento->pix_qr);
        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")->assertSee('Abrir a fatura do Asaas');
    }

    public function test_nova_tentativa_cancela_a_cobranca_antiga_no_asaas(): void
    {
        $this->pagar('pix');
        $antigo = Pagamento::sole();

        $this->pagar('boleto');

        $this->assertSame('DELETED', $this->asaas->cobrancas[$antigo->referencia]['status']);
        $this->assertSame(Pagamento::RECUSADO, $antigo->fresh()->status);
    }

    public function test_cancelar_pedido_pago_estorna_no_asaas(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();
        $this->actingAs($this->cliente)->post($this->url($pagamento, 'simular-pix'));

        $gerente = User::factory()->gerente()->create();
        $this->actingAs($gerente)->from("/painel/pedidos/{$this->pedido->id}")
            ->patch("/painel/pedidos/{$this->pedido->id}/cancelar", ['motivo' => 'Cliente desistiu'])->assertSessionHas('sucesso');

        $this->assertSame('REFUNDED', $this->asaas->cobrancas[$pagamento->referencia]['status']);
        $this->assertSame(Pagamento::ESTORNADO, $pagamento->fresh()->status);
        $this->assertSame(5, $this->vestido->fresh()->estoque);
    }

    public function test_cobranca_vencida_no_asaas_vira_recusada(): void
    {
        $this->pagar('boleto');
        $pagamento = Pagamento::sole();
        $this->asaas->mudarStatus($pagamento->referencia, 'OVERDUE');

        // A tela é levada de volta ao pagamento, com a mensagem de recusa guardada para mostrar
        $this->consultar($pagamento)->assertJson(['status' => Pagamento::RECUSADO, 'ir_para' => url("/meus-pedidos/{$this->pedido->id}/pagamento")])
            ->assertSessionHas('erro', fn ($m) => str_contains($m, 'venceu sem pagamento'));

        $this->assertSame(Pagamento::RECUSADO, $pagamento->fresh()->status);
        $this->assertSame('A cobrança venceu sem pagamento.', $pagamento->fresh()->mensagem);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
    }

    public function test_outro_cliente_nao_consulta_pagamento_alheio(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();

        $this->consultar($pagamento, User::factory()->create())->assertForbidden();
    }

    public function test_asaas_fora_do_ar_na_consulta_automatica_continua_esperando(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();
        $this->asaas->foraDoAr = true;

        $this->consultar($pagamento)->assertOk()->assertExactJson(['status' => Pagamento::PENDENTE]);
        $this->assertSame(Pagamento::PENDENTE, $pagamento->fresh()->status);
    }

    public function test_modo_simulado_nao_tem_consulta_automatica(): void
    {
        config(['mary.pagamento.provedor' => 'simulado']);
        $this->pagar('pix');

        $this->actingAs($this->cliente)->get("/meus-pedidos/{$this->pedido->id}/pagamento")
            ->assertSee('Ambiente de pagamento simulado')
            ->assertSee('final <code>0000</code>', false)
            ->assertDontSee('atualiza sozinha')
            ->assertDontSee('/status', false);
    }

    // ---------- Webhook ----------

    private function webhook(string $referencia, ?string $token = 'token-webhook-teste', string $evento = 'PAYMENT_RECEIVED')
    {
        return $this->postJson('/webhooks/asaas', ['event' => $evento, 'payment' => ['id' => $referencia, 'status' => 'RECEIVED']],
            $token ? ['asaas-access-token' => $token] : []);
    }

    public function test_webhook_sem_token_ou_com_token_errado_e_recusado(): void
    {
        $this->pagar('pix');
        $referencia = Pagamento::sole()->referencia;

        $this->webhook($referencia, null)->assertUnauthorized();
        $this->webhook($referencia, 'token-errado')->assertUnauthorized();
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
    }

    public function test_webhook_nao_confia_no_payload_e_consulta_o_asaas(): void
    {
        $this->pagar('pix');
        $referencia = Pagamento::sole()->referencia;

        // O aviso diz "RECEIVED", mas lá no Asaas a cobrança continua PENDING: nada muda
        $this->webhook($referencia)->assertOk()->assertJson(['status' => Pagamento::PENDENTE]);
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $this->pedido->fresh()->status);
        $this->assertContains("GET /payments/{$referencia}", $this->asaas->chamadas);
    }

    public function test_webhook_aprova_e_repetido_nao_baixa_estoque_duas_vezes(): void
    {
        $this->pagar('pix');
        $pagamento = Pagamento::sole();
        $this->asaas->mudarStatus($pagamento->referencia, 'RECEIVED');

        $this->webhook($pagamento->referencia)->assertOk()->assertJson(['status' => Pagamento::APROVADO]);
        $this->webhook($pagamento->referencia)->assertOk(); // o Asaas pode reenviar

        $this->assertSame(Pedido::PAGO, $this->pedido->fresh()->status);
        $this->assertSame(3, $this->vestido->fresh()->estoque); // 5 - 2, uma vez só
        $this->assertSame(1, Auditoria::where('acao', 'pagamento_aprovado')->count());
    }

    public function test_webhook_de_cobranca_desconhecida_responde_200(): void
    {
        $this->webhook('pay_de_outra_loja')->assertOk()->assertJson(['acao' => 'ignorado']);
    }

    public function test_webhook_sem_token_configurado_recusa_tudo(): void
    {
        config(['mary.pagamento.asaas.webhook_token' => null]);

        $this->webhook('pay_qualquer', '')->assertUnauthorized();
    }

    // ---------- Comando php artisan asaas:testar ----------

    public function test_comando_asaas_testar_passa_com_o_sandbox(): void
    {
        $this->artisan('asaas:testar')
            ->expectsOutputToContain('Conectou e a chave foi aceita.')
            ->expectsOutputToContain('QR Code gerado.')
            ->expectsOutputToContain('Linha digitável gerada.')
            ->expectsOutputToContain('Link da fatura:')
            ->expectsOutputToContain('Status no Asaas: RECEIVED')
            ->expectsOutputToContain('Cobranças e cliente de teste removidos.')
            ->expectsOutputToContain('Tudo certo!')
            ->assertSuccessful();

        $this->assertSame([], $this->asaas->clientes); // limpou o cliente de teste
    }

    public function test_comando_com_chave_errada_falha_com_dica(): void
    {
        config(['mary.pagamento.asaas.chave' => 'chave-errada']);

        $this->artisan('asaas:testar')
            ->expectsOutputToContain('Asaas respondeu 401: A chave de API fornecida é inválida. A chave está errada ou é de produção; use a chave do sandbox.')
            ->assertFailed();
    }

    public function test_comando_sem_chave_avisa_o_que_fazer(): void
    {
        config(['mary.pagamento.asaas.chave' => null]);

        $this->artisan('asaas:testar')->expectsOutputToContain('ASAAS_API_KEY está vazia')->assertFailed();
        $this->assertSame([], $this->asaas->chamadas);
    }

    public function test_comando_nao_mostra_a_chave_inteira(): void
    {
        $this->artisan('asaas:testar')->doesntExpectOutputToContain('chave-de-teste ')->expectsOutputToContain('Chave encontrada: chave-de...');
    }
}
