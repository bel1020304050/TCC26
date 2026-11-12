<?php

namespace Tests\Feature\Painel;

use App\Models\Auditoria;
use App\Models\Carrinho;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

/**
 * Auditoria: motivo obrigatório para inativar/reativar, eventos registrados e relatório só do Administrador.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gerente;
    private User $atendente;
    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Mary Oliveira']);
        $this->gerente = User::factory()->gerente()->create(['name' => 'Bruno Costa']);
        $this->atendente = User::factory()->atendente()->create(['name' => 'Rafael Lima']);
        $this->cliente = User::factory()->create(['name' => 'Ana Souza', 'password' => 'segredo123']);
    }

    private function pedidoDoCliente(int $quantidade = 1, ?Produto $produto = null): Pedido
    {
        $produto ??= Produto::factory()->create(['preco' => 100, 'estoque' => 10]);
        Carrinho::doCliente($this->cliente)->itens()->create(['produto_id' => $produto->id, 'quantidade' => $quantidade]);
        $this->actingAs($this->cliente)->post('/pedidos');

        return Pedido::latest('id')->first();
    }

    private function pagarPix(Pedido $pedido, ?User $cliente = null): void
    {
        $cliente ??= $this->cliente;
        $this->actingAs($cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'pix']);
        $pix = Pagamento::where('pedido_id', $pedido->id)->latest('id')->first();
        $this->actingAs($cliente)->post("/meus-pedidos/{$pedido->id}/pagamento/{$pix->id}/simular-pix");
    }

    private function relatorio(string $consulta = '')
    {
        return $this->actingAs($this->admin)->get('/painel/relatorios/auditoria?de='.today()->subDay()->toDateString().'&ate='.today()->toDateString().$consulta);
    }

    // ---------- Motivo obrigatório ----------

    public static function cadastros(): array
    {
        return [
            'produto' => ['produtos', fn () => Produto::factory()->create()],
            'categoria' => ['categorias', fn () => Categoria::factory()->create()],
            'fornecedor' => ['fornecedores', fn () => Fornecedor::factory()->create()],
            'promoção' => ['promocoes', fn () => Promocao::factory()->create()],
            'usuário' => ['usuarios', fn () => User::factory()->atendente()->create()],
        ];
    }

    /** Regra: inativar e reativar exigem motivo de pelo menos 10 caracteres, conferido no servidor */
    #[\PHPUnit\Framework\Attributes\DataProvider('cadastros')]
    public function test_inativar_e_reativar_exigem_motivo(string $rota, \Closure $criar): void
    {
        $registro = $criar();
        $url = "/painel/{$rota}/{$registro->id}";

        $this->actingAs($this->admin)->patch("{$url}/inativar")->assertSessionHasErrors(['motivo' => 'Escreva o motivo (pelo menos 10 caracteres).']);
        $this->actingAs($this->admin)->patch("{$url}/inativar", ['motivo' => 'curto'])->assertSessionHasErrors(['motivo' => 'Escreva o motivo com pelo menos 10 caracteres.']);
        $this->actingAs($this->admin)->patch("{$url}/inativar", ['motivo' => '           '])->assertSessionHasErrors('motivo'); // só espaços
        $this->assertSame('ativo', $registro->fresh()->status);

        $this->actingAs($this->admin)->patch("{$url}/inativar", ['motivo' => 'Saiu de linha no fornecedor', '_modal' => 'x'])->assertSessionHasNoErrors();
        $this->assertSame('inativo', $registro->fresh()->status);
        $this->actingAs($this->admin)->patch("{$url}/reativar", ['motivo' => 'Voltou para a coleção nova'])->assertSessionHasNoErrors();
        $this->assertSame('ativo', $registro->fresh()->status);

        $this->assertSame(['Saiu de linha no fornecedor', 'Voltou para a coleção nova'],
            Auditoria::whereIn('acao', ['inativou', 'reativou'])->orderBy('id')->pluck('motivo')->all());
    }

    // Quem não tem permissão recebe "acesso negado", não um erro de motivo
    public function test_sem_permissao_continua_403_mesmo_sem_motivo(): void
    {
        $produto = Produto::factory()->create();
        $this->actingAs($this->atendente)->patch("/painel/produtos/{$produto->id}/inativar")->assertForbidden();
    }

    public function test_janela_tem_campo_motivo_e_botao_travado(): void
    {
        $produto = Produto::factory()->create();
        $this->actingAs($this->admin)->get('/painel/produtos')
            ->assertSee('name="motivo"', false)->assertSee('data-motivo-botao disabled', false)->assertSee('js/motivo.js', false);

        // Depois de um erro do servidor, a mesma janela volta aberta com a mensagem
        $modal = "modal-status-produto-{$produto->id}";
        $this->actingAs($this->admin)->from('/painel/produtos')
            ->patch("/painel/produtos/{$produto->id}/inativar", ['motivo' => 'curto', '_modal' => $modal]);
        $html = $this->actingAs($this->admin)->get('/painel/produtos')->getContent();
        $this->assertMatchesRegularExpression('/id="'.$modal.'"[^>]*data-reabrir/', $html);
        $this->assertStringContainsString('pelo menos 10 caracteres', $html);
    }

    // Editar não pede motivo, mas registra o antes e o depois
    public function test_editar_sem_motivo_registra_antes_e_depois(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Vestidos', 'descricao' => 'Antiga']);
        $this->actingAs($this->gerente)->put("/painel/categorias/{$categoria->id}", ['nome' => 'Vestidos', 'descricao' => 'Nova coleção'])
            ->assertSessionHasNoErrors();

        $registro = Auditoria::where('acao', 'editou')->sole();
        $this->assertNull($registro->motivo);
        $this->assertSame('gerente', $registro->perfil);
        $this->relatorio()->assertSee('Descrição: Antiga → Nova coleção')->assertSee('Bruno Costa (Gerente)');
    }

    // ---------- Eventos registrados ----------

    public function test_compra_completa_fica_registrada(): void
    {
        $pedido = $this->pedidoDoCliente();
        $this->pagarPix($pedido);

        $this->assertDatabaseHas('auditorias', ['acao' => 'criou', 'entidade' => 'pedidos', 'entidade_id' => $pedido->id, 'user_id' => $this->cliente->id, 'perfil' => 'cliente']);
        // PIX confirmado pelo provedor: quem aprova é o "Sistema"
        $this->assertDatabaseHas('auditorias', ['acao' => 'pagamento_aprovado', 'entidade_id' => $pedido->id, 'user_id' => null, 'perfil' => 'sistema']);

        $this->relatorio('&area=pedidos')
            ->assertSee('Ana Souza (Cliente)')->assertSee('Pagamento aprovado')->assertSee('Sistema')
            ->assertSee('PIX · R$ 100,00')->assertSee('Pedido '.$pedido->numero());
    }

    public function test_cartao_recusado_fica_registrado(): void
    {
        $pedido = $this->pedidoDoCliente();
        $this->actingAs($this->cliente)->post("/meus-pedidos/{$pedido->id}/pagamento", ['forma' => 'cartao',
            'cartao_numero' => '5555 4444 3333 0000', 'cartao_nome' => 'ANA SOUZA', 'cartao_validade' => '12/'.now()->addYears(2)->format('y'), 'cartao_cvv' => '123']);

        $registro = Auditoria::where('acao', 'pagamento_recusado')->sole();
        $this->assertSame('sistema', $registro->perfil);
        $this->assertStringNotContainsString('5555', json_encode($registro->detalhes)); // nunca o número do cartão
    }

    // Cancelamento de pedido pago: quem cancelou, motivo e o estorno
    public function test_cancelamento_com_estorno_fica_registrado(): void
    {
        $pedido = $this->pedidoDoCliente();
        $this->pagarPix($pedido);

        $this->actingAs($this->gerente)->patch("/painel/pedidos/{$pedido->id}/cancelar", ['motivo' => 'Cliente desistiu da compra']);

        $cancelou = Auditoria::where('acao', 'cancelou')->sole();
        $this->assertSame($this->gerente->id, $cancelou->user_id);
        $this->assertSame('Cliente desistiu da compra', $cancelou->motivo);
        $this->assertDatabaseHas('auditorias', ['acao' => 'pagamento_estornado', 'entidade_id' => $pedido->id, 'user_id' => $this->gerente->id]);

        $this->relatorio('&acao=cancelou')->assertSee('Pago → Cancelado')->assertSee('Cliente desistiu da compra');
    }

    // Duas pessoas pagam a última peça: o segundo pedido é cancelado pelo "Sistema"
    public function test_cancelamento_automatico_aparece_como_sistema(): void
    {
        $ultima = Produto::factory()->create(['estoque' => 1, 'preco' => 50]);
        $pedidoA = $this->pedidoDoCliente(1, $ultima);
        $outro = User::factory()->create();
        Carrinho::doCliente($outro)->itens()->create(['produto_id' => $ultima->id, 'quantidade' => 1]);
        $this->actingAs($outro)->post('/pedidos');
        $pedidoB = Pedido::latest('id')->first();

        $this->actingAs($outro)->post("/meus-pedidos/{$pedidoB->id}/pagamento", ['forma' => 'pix']);
        $this->pagarPix($pedidoA);
        $pixB = Pagamento::where('pedido_id', $pedidoB->id)->sole();
        $this->actingAs($outro)->post("/meus-pedidos/{$pedidoB->id}/pagamento/{$pixB->id}/simular-pix");

        $cancelou = Auditoria::where('acao', 'cancelou')->sole();
        $this->assertNull($cancelou->user_id);
        $this->assertSame('sistema', $cancelou->perfil);
        $this->assertStringContainsString('estoque insuficiente', $cancelou->motivo);
        $this->relatorio('&usuario=sistema&acao=cancelou')->assertSee('Sistema')->assertSee('estoque insuficiente');
    }

    public function test_conta_criada_fica_registrada(): void
    {
        $this->post('/criar-conta', [
            'name' => 'Bia Lima', 'email' => 'bia@email.com', 'cpf' => '529.982.247-25', 'telefone' => '',
            'password' => 'segredo123', 'password_confirmation' => 'segredo123',
            'cep' => '87501-000', 'rua' => 'Rua A', 'numero' => '1', 'complemento' => '', 'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
        ]);
        $bia = User::where('email', 'bia@email.com')->sole();
        $this->assertDatabaseHas('auditorias', ['acao' => 'criou', 'entidade' => 'users', 'entidade_id' => $bia->id, 'user_id' => $bia->id, 'perfil' => 'cliente']);
    }

    // LGPD: cliente aparece pelo nome, mas sem os valores dos dados pessoais
    public function test_cliente_editando_dados_registra_so_quais_campos(): void
    {
        $this->actingAs($this->cliente)->put('/minha-conta', [
            'name' => 'Ana Souza', 'email' => $this->cliente->email, 'cpf' => $this->cliente->cpf, 'telefone' => '(44) 98888-7777',
            'cep' => '87502-000', 'rua' => 'Rua Nova Secreta', 'numero' => '99', 'complemento' => '', 'bairro' => 'Zona 2', 'cidade' => 'Umuarama', 'uf' => 'PR',
        ]);

        $registro = Auditoria::where('acao', 'editou')->sole();
        $this->assertSame(['campos' => ['Telefone', 'Endereço']], $registro->detalhes);
        $this->assertStringNotContainsString('Rua Nova Secreta', json_encode($registro->detalhes));
        $this->relatorio()->assertSee('Alterou: Telefone, Endereço')->assertSee('Ana Souza (Cliente)')->assertDontSee('Rua Nova Secreta');
    }

    // LGPD: depois de encerrar a conta, o nome some da auditoria
    public function test_conta_encerrada_vira_cliente_removido_na_auditoria(): void
    {
        $pedido = $this->pedidoDoCliente();
        $this->actingAs($this->cliente)->patch("/meus-pedidos/{$pedido->id}/cancelar");
        $this->actingAs($this->cliente)->delete('/minha-conta', ['senha_encerrar' => 'segredo123']);

        $this->relatorio()->assertSee('Cliente removido (Cliente)')->assertDontSee('Ana Souza');
    }

    // ---------- Relatório ----------

    public function test_so_o_admin_ve_o_relatorio_e_as_exportacoes(): void
    {
        foreach ([$this->gerente, $this->atendente, $this->cliente] as $usuario) {
            foreach (['', '?exportar=csv', '?exportar=pdf'] as $sufixo) {
                $this->actingAs($usuario)->get('/painel/relatorios/auditoria'.$sufixo)->assertForbidden();
            }
        }
        $this->actingAs($this->gerente)->get('/painel/relatorios')->assertDontSee('Auditoria');
        $this->actingAs($this->admin)->get('/painel/relatorios')->assertSee('Auditoria');
        $this->actingAs($this->admin)->get('/painel/relatorios/auditoria')->assertOk()->assertSee('Relatório de auditoria');
    }

    public function test_filtros_e_exportacoes(): void
    {
        $produto = Produto::factory()->create(['nome' => 'Saia Midi', 'preco' => 100]);
        $this->actingAs($this->gerente)->patch("/painel/produtos/{$produto->id}/inativar", ['motivo' => 'Peça com defeito de fábrica']);
        $this->actingAs($this->admin)->patch("/painel/produtos/{$produto->id}/reativar", ['motivo' => 'Defeito corrigido pelo fornecedor']);

        $this->relatorio("&usuario={$this->gerente->id}")
            ->assertSee('Peça com defeito de fábrica')->assertDontSee('Defeito corrigido pelo fornecedor')
            ->assertSee('Ativo → Inativo')->assertSee('Saia Midi');
        $this->relatorio('&acao=reativou')->assertSee('Defeito corrigido pelo fornecedor')->assertDontSee('Peça com defeito de fábrica');
        $this->relatorio('&area=categorias')->assertSee('Nenhum');

        $csv = $this->relatorio('&exportar=csv')->streamedContent();
        $this->assertStringContainsString('Data/hora;Quem;Ação;Área;Registro;"O que mudou";Motivo', $csv);
        $this->assertStringContainsString('Peça com defeito de fábrica', $csv);

        $pdf = $this->relatorio('&exportar=pdf')->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    // Regra: somente leitura (nem pelo código, nem por alguma rota)
    public function test_auditoria_e_somente_leitura(): void
    {
        $produto = Produto::factory()->create();
        $this->actingAs($this->admin)->patch("/painel/produtos/{$produto->id}/inativar", ['motivo' => 'Saiu de linha no fornecedor']);
        $registro = Auditoria::sole();

        try {
            $registro->update(['motivo' => 'Adulterado']);
            $this->fail('Conseguiu alterar a auditoria');
        } catch (LogicException) {
        }
        try {
            $registro->delete();
            $this->fail('Conseguiu apagar a auditoria');
        } catch (LogicException) {
        }
        $this->assertSame('Saiu de linha no fornecedor', $registro->fresh()->motivo);

        $rotasQueMudamAuditoria = collect(Route::getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'auditoria') && array_diff($r->methods(), ['GET', 'HEAD']));
        $this->assertCount(0, $rotasQueMudamAuditoria);
    }
}
