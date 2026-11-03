<?php

namespace Tests\Feature\Painel;

use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Relatórios — stories 3.32 (vendas), 3.33 (estoque) e 3.34 (clientes).
 */
class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->funcionario = User::factory()->funcionario()->create();
    }

    private function pedido(User $cliente, string $status, float $total, string $data, ?string $forma = null): Pedido
    {
        $pedido = new Pedido;
        $pedido->user_id = $cliente->id;
        $pedido->status = $status;
        $pedido->subtotal = $pedido->total = $total;
        $pedido->forma_pagamento = $forma;
        foreach (['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $cliente->{$campo} ?? 'x';
        }
        $pedido->created_at = Carbon::parse($data);
        $pedido->save();

        return $pedido;
    }

    private function vendasOutubro(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Souza']);
        $carlos = User::factory()->create(['name' => 'Carlos Lima']);
        $this->pedido($ana, Pedido::AGUARDANDO_PAGAMENTO, 175.84, '2026-10-19');
        $this->pedido($carlos, Pedido::PAGO, 259.80, '2026-10-17', 'pix');
        $this->pedido($carlos, Pedido::EM_SEPARACAO, 129.90, '2026-10-12', 'cartao');
        $this->pedido($ana, Pedido::ENTREGUE, 149.80, '2026-10-05', 'pix');
        $this->pedido($ana, Pedido::CANCELADO, 89.90, '2026-10-02', 'pix'); // estornado: fora do faturamento
        $this->pedido($ana, Pedido::ENTREGUE, 500.00, '2026-09-20', 'pix'); // fora do período
    }

    // CT-3-32-01
    public function test_relatorio_de_vendas_mensal_conta_so_vendas_pagas(): void
    {
        $this->vendasOutubro();

        $this->actingAs($this->admin)->get('/painel/relatorios/vendas?de=2026-10-01&ate=2026-10-31')
            ->assertOk()
            ->assertSeeInOrder(['Vendas pagas no período', '3', 'Faturamento', 'R$ 539,50', 'Ticket médio', 'R$ 179,83'])
            ->assertSee('Carlos Lima')
            ->assertSee('R$ 175,84')            // aguardando aparece na lista...
            ->assertDontSee('R$ 500,00')        // ...mas setembro não
            ->assertSee('Exibindo 5 de 5');
    }

    public function test_filtros_de_status_e_forma_de_pagamento(): void
    {
        $this->vendasOutubro();

        $this->actingAs($this->admin)->get('/painel/relatorios/vendas?de=2026-10-01&ate=2026-10-31&forma=cartao')
            ->assertSee('R$ 129,90')->assertDontSee('R$ 259,80')
            ->assertSeeInOrder(['Faturamento', 'R$ 129,90']);
        $this->actingAs($this->admin)->get('/painel/relatorios/vendas?de=2026-10-01&ate=2026-10-31&status=cancelado')
            ->assertSee('R$ 89,90')->assertSeeInOrder(['Vendas pagas no período', '0']);
    }

    // CT-3-32-02
    public function test_periodo_sem_vendas_mostra_zerado_sem_erro(): void
    {
        $this->actingAs($this->admin)->get('/painel/relatorios/vendas?de=2025-01-01&ate=2025-01-31')
            ->assertOk()
            ->assertSee('Nenhum resultado para os filtros escolhidos.')
            ->assertSeeInOrder(['Vendas pagas no período', '0', 'Faturamento', 'R$ 0,00', 'Ticket médio', 'R$ 0,00']);
    }

    // RNF.02 da 3.32 e 3.34
    public function test_vendas_e_clientes_so_para_admin_inclusive_exportacao(): void
    {
        $cliente = User::factory()->create();

        foreach (['vendas', 'clientes'] as $relatorio) {
            foreach (['', '?exportar=csv', '?exportar=pdf'] as $sufixo) {
                $this->actingAs($this->funcionario)->get("/painel/relatorios/{$relatorio}{$sufixo}")->assertForbidden();
                $this->actingAs($cliente)->get("/painel/relatorios/{$relatorio}{$sufixo}")->assertForbidden();
            }
        }
        auth()->logout();
        $this->get('/painel/relatorios/vendas')->assertRedirect('/entrar');
    }

    public function test_exporta_vendas_em_csv_com_as_mesmas_colunas(): void
    {
        $this->vendasOutubro();

        $resposta = $this->actingAs($this->admin)->get('/painel/relatorios/vendas?de=2026-10-01&ate=2026-10-31&exportar=csv');
        $resposta->assertOk()->assertDownload();
        $csv = $resposta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv); // acentos certos no Excel
        $linhas = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertSame('Pedido;Data;Cliente;Status;Pagamento;Total;"Entra no faturamento"', $linhas[0]);
        $this->assertCount(6, $linhas); // cabeçalho + 5 pedidos
        $this->assertStringContainsString('"Carlos Lima";Pago;PIX;"R$ 259,80";Sim', $linhas[2]);
    }

    public function test_exporta_em_pdf_pela_pagina_de_impressao(): void
    {
        $this->vendasOutubro();

        $this->actingAs($this->admin)->get('/painel/relatorios/vendas?de=2026-10-01&ate=2026-10-31&exportar=pdf')
            ->assertOk()
            ->assertSee('Relatório de vendas')
            ->assertSee('Período: 01/10/2026 a 31/10/2026')
            ->assertSee('window.print()', false)
            ->assertSee('Carlos Lima');
    }

    // CT-3-33-01
    public function test_funcionario_gera_estoque_com_destaque_de_estoque_baixo(): void
    {
        Produto::factory()->create(['nome' => 'Saia Midi', 'estoque' => 6]);
        Produto::factory()->create(['nome' => 'Blusa Básica', 'estoque' => 25]);

        $this->actingAs($this->funcionario)->get('/painel/relatorios/estoque')
            ->assertOk()
            ->assertSeeInOrder(['Produtos listados', '2', 'Unidades em estoque', '31', 'Com estoque baixo', '1'])
            ->assertSee('mm-linha-destaque', false)
            ->assertSee('Saia Midi')->assertSee('Blusa Básica');
    }

    public function test_filtros_do_estoque_e_produtos_inativos(): void
    {
        $vestidos = Categoria::factory()->create(['nome' => 'Vestidos']);
        $aurora = Fornecedor::factory()->create(['nome' => 'Confecções Aurora']);
        Produto::factory()->create(['nome' => 'Vestido Floral', 'categoria_id' => $vestidos->id, 'fornecedor_id' => $aurora->id, 'tamanho' => 'M', 'cor' => 'Preto']);
        Produto::factory()->create(['nome' => 'Jaqueta Jeans', 'status' => 'inativo', 'tamanho' => 'G', 'cor' => 'Azul']);

        $this->actingAs($this->admin)->get("/painel/relatorios/estoque?categoria={$vestidos->id}")->assertSee('Vestido Floral')->assertDontSee('Jaqueta Jeans');
        $this->actingAs($this->admin)->get("/painel/relatorios/estoque?fornecedor={$aurora->id}&tamanho=M&cor=Preto")->assertSee('Vestido Floral')->assertDontSee('Jaqueta Jeans');
        $this->actingAs($this->admin)->get('/painel/relatorios/estoque?status=inativo')->assertSee('Jaqueta Jeans')->assertDontSee('Vestido Floral');
        $this->actingAs($this->admin)->get('/painel/relatorios/estoque')->assertSee('Jaqueta Jeans'); // "Todos" inclui inativos
    }

    // CT-3-33-02
    public function test_categoria_sem_produtos_mostra_resultado_vazio(): void
    {
        $vazia = Categoria::factory()->create(['nome' => 'Acessórios']);
        Produto::factory()->create();

        $this->actingAs($this->funcionario)->get("/painel/relatorios/estoque?categoria={$vazia->id}")
            ->assertOk()->assertSee('Nenhum resultado para os filtros escolhidos.')->assertSeeInOrder(['Produtos listados', '0']);
    }

    public function test_estoque_recusa_cliente(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel/relatorios/estoque')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/painel/relatorios/estoque?exportar=csv')->assertForbidden();
    }

    // CT-3-34-01
    public function test_relatorio_de_clientes_do_mes(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@email.com', 'created_at' => '2026-09-02 10:00']);
        $marcos = User::factory()->create(['name' => 'Marcos Pereira', 'created_at' => '2026-09-21 10:00']);
        User::factory()->create(['name' => 'Fora Do Mes', 'created_at' => '2026-08-15 10:00']);
        $this->pedido($ana, Pedido::ENTREGUE, 149.80, '2026-10-05', 'pix');
        $this->pedido($ana, Pedido::PAGO, 129.90, '2026-10-06', 'pix');
        $this->pedido($ana, Pedido::CANCELADO, 89.90, '2026-10-07', 'pix');      // não conta
        $this->pedido($marcos, Pedido::AGUARDANDO_PAGAMENTO, 50, '2026-10-08'); // não conta

        $this->actingAs($this->admin)->get('/painel/relatorios/clientes?de=2026-09-01&ate=2026-09-30')
            ->assertOk()
            ->assertSeeInOrder(['Clientes cadastrados no período', '2', 'Clientes com compras pagas', '1', 'Valor total comprado', 'R$ 279,70'])
            ->assertSeeInOrder(['Ana Souza', 'ana@email.com', '02/09/2026', '2', 'R$ 279,70'])
            ->assertSee('Marcos Pereira')
            ->assertDontSee('Fora Do Mes')
            ->assertDontSee($ana->telefone)->assertDontSee($ana->rua); // minimização (LGPD)
    }

    public function test_filtro_com_e_sem_compras_e_ordem_por_volume(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Souza', 'created_at' => '2026-09-02']);
        $joana = User::factory()->create(['name' => 'Joana Alves', 'created_at' => '2026-09-10']);
        User::factory()->create(['name' => 'Marcos Pereira', 'created_at' => '2026-09-21']);
        $this->pedido($ana, Pedido::PAGO, 100, '2026-10-01', 'pix');
        $this->pedido($joana, Pedido::PAGO, 300, '2026-10-01', 'pix');
        $url = '/painel/relatorios/clientes?de=2026-09-01&ate=2026-09-30';

        $this->actingAs($this->admin)->get("{$url}&compras=com")->assertSee('Joana Alves')->assertDontSee('Marcos Pereira');
        $this->actingAs($this->admin)->get("{$url}&compras=sem")->assertSee('Marcos Pereira')->assertDontSee('Joana Alves');
        $this->actingAs($this->admin)->get("{$url}&ordem=compras")->assertSeeInOrder(['Joana Alves', 'Ana Souza', 'Marcos Pereira']);
        $this->actingAs($this->admin)->get($url)->assertSeeInOrder(['Ana Souza', 'Joana Alves', 'Marcos Pereira']);
    }

    // Regra da 3.34: conta encerrada aparece como "Cliente removido", sem e-mail, e continua somando
    public function test_conta_encerrada_aparece_anonima_e_soma_nos_indicadores(): void
    {
        $paula = User::factory()->create(['name' => 'Paula Ramos', 'email' => 'paula@email.com', 'password' => 'senha1234', 'created_at' => today()->subDays(3)]);
        $this->pedido($paula, Pedido::ENTREGUE, 89.90, today()->subDay()->toDateString(), 'pix');
        $this->actingAs($paula)->delete('/minha-conta', ['senha_encerrar' => 'senha1234']);

        $this->actingAs($this->admin)->get('/painel/relatorios/clientes')
            ->assertSee('Cliente removido')
            ->assertDontSee('Paula Ramos')->assertDontSee('paula@email.com')->assertDontSee('conta-encerrada.invalid')
            ->assertSeeInOrder(['Valor total comprado', 'R$ 89,90']);
    }

    // CT-3-34-02
    public function test_clientes_sem_resultado(): void
    {
        $this->actingAs($this->admin)->get('/painel/relatorios/clientes?de=2025-01-01&ate=2025-01-31')
            ->assertOk()
            ->assertSee('Nenhum resultado para os filtros escolhidos.')
            ->assertSeeInOrder(['Clientes cadastrados no período', '0', 'Clientes com compras pagas', '0', 'Valor total comprado', 'R$ 0,00']);
    }

    public function test_csv_de_clientes_tem_so_as_colunas_da_tela(): void
    {
        User::factory()->create(['name' => 'Ana Souza', 'telefone' => '(44) 91234-5678']);

        $csv = $this->actingAs($this->admin)->get('/painel/relatorios/clientes?exportar=csv')->streamedContent();

        $this->assertStringContainsString('Cliente;E-mail;Cadastro;"Pedidos pagos";"Total comprado"', $csv);
        $this->assertStringNotContainsString('91234-5678', $csv);
    }

    public function test_pagina_inicial_mostra_so_os_relatorios_permitidos(): void
    {
        $this->actingAs($this->funcionario)->get('/painel/relatorios')
            ->assertOk()->assertSee('Produtos / Estoque')->assertDontSee(route('painel.relatorios.vendas'));
        $this->actingAs($this->admin)->get('/painel/relatorios')
            ->assertSee(route('painel.relatorios.vendas'))->assertSee(route('painel.relatorios.clientes'));
    }
}
