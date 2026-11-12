<?php

namespace Tests\Quebra;

use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bateria 2: tentar acessar o que não é seu e mandar campos escondidos.
 */
class SegurancaTest extends TestCase
{
    use Cenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarCenario();
    }

    // Cliente B tentando mexer nos pedidos do cliente A pela URL
    public function test_cliente_nao_mexe_em_pedido_de_outro(): void
    {
        $b = $this->u['cliente_b'];
        $p = $this->m['pendente'];
        $this->actingAs($b)->get("/meus-pedidos/{$p->id}")->assertForbidden();
        $this->actingAs($b)->get("/meus-pedidos/{$p->id}/pagamento")->assertForbidden();
        $this->actingAs($b)->post("/meus-pedidos/{$p->id}/pagamento", ['forma' => 'pix'])->assertForbidden();
        $this->actingAs($b)->patch("/meus-pedidos/{$p->id}/cancelar")->assertForbidden();
        $this->actingAs($b)->get("/meus-pedidos/{$this->m['pago']->id}/concluido")->assertForbidden();
        $this->actingAs($b)->post("/meus-pedidos/{$this->m['pago']->id}/pagamento/{$this->m['pix']->id}/simular-pix")->assertForbidden();
        $this->assertSame(Pedido::AGUARDANDO_PAGAMENTO, $p->fresh()->status);
    }

    // Cliente A tentando usar o PIX de outro pedido dentro da URL do próprio pedido
    public function test_nao_confirma_pagamento_de_outro_pedido_pela_url(): void
    {
        $outroPedido = $this->m['pendente'];
        $this->actingAs($this->u['cliente'])->post("/meus-pedidos/{$outroPedido->id}/pagamento", ['forma' => 'pix']);
        $pixDoPendente = Pagamento::where('pedido_id', $outroPedido->id)->where('forma', 'pix')->latest('id')->first();

        // URL do pedido pago + pagamento de outro pedido
        $this->actingAs($this->u['cliente'])
            ->post("/meus-pedidos/{$this->m['pago']->id}/pagamento/{$pixDoPendente->id}/simular-pix")->assertNotFound();
        $this->assertSame(Pagamento::PENDENTE, $pixDoPendente->fresh()->status);
    }

    public function test_cliente_nao_mexe_no_carrinho_de_outro(): void
    {
        $item = $this->m['item']; // do cliente B
        $this->actingAs($this->u['cliente'])->patch("/carrinho/itens/{$item->id}", ['quantidade' => 9])->assertForbidden();
        $this->actingAs($this->u['cliente'])->delete("/carrinho/itens/{$item->id}")->assertForbidden();
        $this->assertSame(1, $item->fresh()->quantidade);
    }

    public function test_cliente_nao_cancela_pedido_pago(): void
    {
        // O sistema bloqueia com mensagem na tela (não com 403); o que importa é o pedido continuar Pago
        $this->actingAs($this->u['cliente'])->patch("/meus-pedidos/{$this->m['pago']->id}/cancelar")->assertSessionHas('erro');
        $this->assertSame(Pedido::PAGO, $this->m['pago']->fresh()->status);
    }

    // Atendente chamando direto ações de Gerente/Admin
    public function test_atendente_nao_faz_acoes_de_gestao(): void
    {
        $a = $this->u['atendente'];
        $this->actingAs($a)->patch("/painel/pedidos/{$this->m['pago']->id}/cancelar", ['motivo' => 'teste de invasão'])->assertSessionHas('erro');
        $this->actingAs($a)->post("/painel/pagamentos/{$this->m['boleto']->id}/confirmar-boleto")->assertForbidden();
        $this->actingAs($a)->patch("/painel/categorias/{$this->m['categoria']->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();
        $this->actingAs($a)->patch("/painel/produtos/{$this->m['produto']->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();
        $this->actingAs($a)->post("/painel/usuarios/{$this->u['gerente']->id}/redefinir-senha")->assertForbidden();
        $this->actingAs($a)->post('/painel/usuarios', ['name' => 'X', 'email' => 'x@marymodas.com.br', 'perfil' => 'admin', 'status' => 'ativo'])->assertForbidden();
        $this->assertSame(Pedido::PAGO, $this->m['pago']->fresh()->status);
        $this->assertSame(Pagamento::PENDENTE, $this->m['boleto']->fresh()->status);
    }

    // Atendente pode editar produto, mas não pode inativá-lo escondendo "status" no formulário de edição
    public function test_atendente_nao_inativa_produto_pelo_formulario_de_edicao(): void
    {
        $p = $this->m['produto'];
        $this->actingAs($this->u['atendente'])->put("/painel/produtos/{$p->id}", [
            'nome' => $p->nome, 'categoria_id' => $p->categoria_id, 'fornecedor_id' => $p->fornecedor_id, 'tamanho' => $p->tamanho,
            'cor' => $p->cor, 'preco' => '100', 'estoque' => '50', 'descricao' => 'x', 'status' => 'inativo',
        ]);
        $this->assertSame('ativo', $p->fresh()->status);
    }

    // Campos escondidos no "Criar conta"
    public function test_criar_conta_ignora_campos_de_permissao(): void
    {
        $this->post('/criar-conta', [
            'name' => 'Hacker', 'email' => 'hacker@email.com', 'cpf' => '529.982.247-25', 'telefone' => '',
            'password' => 'segredo123', 'password_confirmation' => 'segredo123',
            'cep' => '87501-000', 'rua' => 'Rua A', 'numero' => '1', 'complemento' => '', 'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
            'perfil' => 'admin', 'status' => 'ativo', 'senha_provisoria' => '0', 'email_verified_at' => now()->toDateTimeString(), 'encerrada_em' => null,
        ]);
        $u = User::where('email', 'hacker@email.com')->sole();
        $this->assertTrue($u->isCliente());
        $this->assertNull($u->email_verified_at);
    }

    public function test_meus_dados_ignora_campos_de_permissao(): void
    {
        $c = $this->u['nao_verificado'];
        $this->actingAs($c)->put('/minha-conta', [
            'name' => $c->name, 'email' => $c->email, 'cpf' => $c->cpf, 'telefone' => '', 'cep' => '87501-000', 'rua' => 'Rua A', 'numero' => '1',
            'complemento' => '', 'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
            'perfil' => 'admin', 'status' => 'inativo', 'email_verified_at' => now()->toDateTimeString(),
        ]);
        $c = $c->fresh();
        $this->assertTrue($c->isCliente());
        $this->assertSame('ativo', $c->status);
        $this->assertNull($c->email_verified_at);
    }

    public function test_minha_conta_da_equipe_ignora_perfil(): void
    {
        $a = $this->u['atendente'];
        $this->actingAs($a)->put('/painel/minha-conta', ['name' => 'Novo Nome', 'telefone' => '', 'perfil' => 'admin', 'email' => 'outro@marymodas.com.br']);
        $this->assertTrue($a->fresh()->isAtendente());
        $this->assertSame($a->email, $a->fresh()->email);
    }

    // Admin editando alguém com campos escondidos
    public function test_edicao_de_usuario_ignora_senha_e_status_escondidos(): void
    {
        $alvo = $this->u['atendente'];
        $hash = $alvo->password;
        $this->actingAs($this->u['admin'])->put("/painel/usuarios/{$alvo->id}", [
            'name' => $alvo->name, 'email' => $alvo->email, 'telefone' => '', 'perfil' => 'atendente',
            'status' => 'inativo', 'senha_provisoria' => '1', 'password' => 'x12345678', 'password_confirmation' => 'x12345678',
        ]);
        $alvo = $alvo->fresh();
        $this->assertSame('ativo', $alvo->status);
        $this->assertFalse($alvo->senha_provisoria);
        $this->assertSame($hash, $alvo->password);
    }

    // Valores do pedido vêm do servidor, nunca do formulário
    public function test_pedido_ignora_total_e_desconto_enviados(): void
    {
        $c = $this->u['cliente_b'];
        $this->actingAs($c)->post('/pedidos', ['total' => '0.01', 'desconto' => '999', 'subtotal' => '0.01', 'user_id' => $this->u['cliente']->id]);
        $pedido = Pedido::where('user_id', $c->id)->sole();
        $this->assertSame('100.00', $pedido->subtotal);
        $this->assertSame((float) $pedido->subtotal - (float) $pedido->desconto, (float) $pedido->total);
    }

    public function test_pagamento_ignora_valor_enviado_e_forma_invalida(): void
    {
        $c = $this->u['cliente_b'];
        $this->actingAs($c)->post('/pedidos');
        $pedido = Pedido::where('user_id', $c->id)->sole();

        $this->actingAs($c)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'dinheiro'])->assertSessionHasErrors();
        $this->actingAs($c)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'pix', 'valor' => '0.01', 'status' => 'aprovado']);
        $pix = Pagamento::where('pedido_id', $pedido->id)->sole();
        $this->assertSame(Pagamento::PENDENTE, $pix->status);
        $this->assertSame((float) $pedido->total, (float) $pix->valor);
    }

    // Pagar de novo um pedido que já está pago não pode criar outra cobrança
    public function test_nao_paga_duas_vezes(): void
    {
        $pago = $this->m['pago'];
        $antes = Pagamento::where('pedido_id', $pago->id)->count();
        $this->actingAs($this->u['cliente'])->post("/meus-pedidos/{$pago->id}/pagamento", ['forma' => 'pix']);
        $this->actingAs($this->u['cliente'])->post("/meus-pedidos/{$pago->id}/pagamento/{$this->m['pix']->id}/simular-pix");
        $this->assertSame($antes, Pagamento::where('pedido_id', $pago->id)->count());
        $this->assertSame(Pagamento::APROVADO, $this->m['pix']->fresh()->status);
    }

    // Senha provisória: nenhuma ação passa, nem por POST direto
    public function test_senha_provisoria_nao_escapa_por_post(): void
    {
        $p = $this->u['provisoria'];
        $this->actingAs($p)->put('/painel/minha-conta/senha', ['senha_atual' => 'password', 'password' => 'nova12345', 'password_confirmation' => 'nova12345'])
            ->assertRedirect(route('senha-provisoria.edit'));
        $this->actingAs($p)->get('/painel/relatorios/vendas?exportar=csv')->assertRedirect(route('senha-provisoria.edit'));
        $this->assertTrue($p->fresh()->senha_provisoria);
    }

    // Encerrar a conta exige a senha
    public function test_encerrar_conta_exige_senha(): void
    {
        $c = $this->u['cliente_b'];
        $this->actingAs($c)->delete('/minha-conta')->assertSessionHasErrors();
        $this->actingAs($c)->delete('/minha-conta', ['senha_encerrar' => 'errada'])->assertSessionHasErrors();
        $this->assertSame('ativo', $c->fresh()->status);
    }

    // Último Administrador não pode ser rebaixado nem inativado
    public function test_ultimo_admin_nao_perde_o_acesso(): void
    {
        $admin = $this->u['admin'];
        $this->actingAs($admin)->patch("/painel/usuarios/{$admin->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertStatus(403);
        $this->actingAs($admin)->put("/painel/usuarios/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'telefone' => '', 'perfil' => 'gerente']);
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertSame('ativo', $admin->fresh()->status);
    }

    // Login não pode redirecionar para site de fora depois de entrar (open redirect)
    public function test_login_nao_redireciona_para_fora(): void
    {
        $destino = $this->post('/entrar?redirect=https://site-malicioso.com', [
            'email' => $this->u['cliente_b']->email, 'password' => 'password', 'redirect' => 'https://site-malicioso.com',
        ])->headers->get('Location');

        $this->assertStringNotContainsString('site-malicioso', (string) $destino);
        $this->assertStringStartsWith(config('app.url'), (string) $destino);
    }

    // Cliente encerrado não volta a entrar; e senha antiga não funciona depois de encerrar
    public function test_conta_encerrada_nao_entra(): void
    {
        $c = User::factory()->encerrado()->create(['password' => 'segredo123']);
        $this->post('/entrar', ['email' => $c->email, 'password' => 'segredo123'])->assertSessionHasErrors();
        $this->assertGuest();
    }
}
