<?php

namespace Tests\Feature\Painel;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Promocao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro de Promoções no painel.
 */
class PromocaoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'nome' => 'Semana Mary', 'modo' => 'cupom', 'codigo' => 'mary20', 'tipo' => 'percentual', 'valor' => '20',
            'data_inicio' => today()->subDay()->toDateString(), 'data_fim' => today()->addDays(10)->toDateString(),
            'aplicacao' => 'geral', 'status' => 'ativo',
        ], $troca);
    }

    // CT-3-22-01
    public function test_cadastra_cupom_percentual_valido(): void
    {
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados())
            ->assertRedirect(route('painel.promocoes.index'))
            ->assertSessionHas('sucesso');

        $promocao = Promocao::sole();
        $this->assertSame('MARY20', $promocao->codigo); // salvo em maiúsculas
        $this->assertSame('vigente', $promocao->situacao());
        $this->assertDatabaseHas('auditorias', ['acao' => 'criou', 'entidade' => 'promocoes']);

        $this->actingAs($this->admin)->get('/painel/promocoes')->assertSee('MARY20')->assertSee('Vigente');
    }

    public function test_situacao_agendada_quando_comeca_no_futuro(): void
    {
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados([
            'data_inicio' => today()->addDays(3)->toDateString(), 'data_fim' => today()->addDays(9)->toDateString(),
        ]));

        $this->assertSame('agendada', Promocao::sole()->situacao());
    }

    // CT-3-22-02
    public function test_data_final_anterior_a_inicial_e_recusada(): void
    {
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados([
            'data_inicio' => '2026-11-20', 'data_fim' => '2026-11-10',
        ]))->assertSessionHasErrors(['data_fim' => 'A data final não pode ser anterior à data inicial.']);

        $this->assertSame(0, Promocao::count());
    }

    public function test_regras_de_modo_codigo_e_valor(): void
    {
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['codigo' => '']))
            ->assertSessionHasErrors(['codigo' => 'No modo Cupom, o código é obrigatório.']);
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['valor' => '60']))
            ->assertSessionHasErrors(['valor' => 'O desconto percentual deve ficar entre 1% e 50%.']);
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['tipo' => 'valor', 'valor' => '0']))
            ->assertSessionHasErrors(['valor' => 'O desconto em valor fixo deve ser maior que zero.']);
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['aplicacao' => 'categoria']))
            ->assertSessionHasErrors('categoria_id');

        // Formatos aceitos: "20,5", "10.5" e "R$ 10,00"
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['codigo' => 'A1', 'valor' => '10.5']))->assertSessionHasErrors('codigo');
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['codigo' => 'FMT1', 'valor' => '10.5']));
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['codigo' => 'FMT2', 'tipo' => 'valor', 'valor' => 'R$ 1.234,50']));
        $this->assertSame('10.50', Promocao::where('codigo', 'FMT1')->value('valor'));
        $this->assertSame('1234.50', Promocao::where('codigo', 'FMT2')->value('valor'));
        Promocao::query()->delete();

        // Automática ignora o código digitado
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['modo' => 'automatica', 'codigo' => 'XYZ']));
        $this->assertNull(Promocao::sole()->codigo);

        // Código único
        Promocao::factory()->cupom('MARY20')->create();
        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['nome' => 'Outra']))
            ->assertSessionHasErrors(['codigo' => 'Este código de cupom já está em uso.']);
    }

    // Segurança: o Atendente só consulta promoções
    public function test_atendente_so_consulta(): void
    {
        $func = User::factory()->atendente()->create();
        $promocao = Promocao::factory()->create(['nome' => 'Queima de Saias']);

        $this->actingAs($func)->get('/painel/promocoes')->assertOk()->assertSee('Queima de Saias')
            ->assertDontSee('Nova promoção')->assertDontSee(route('painel.promocoes.edit', $promocao));
        $this->actingAs($func)->post('/painel/promocoes', $this->dados())->assertForbidden();
        $this->actingAs($func)->get("/painel/promocoes/{$promocao->id}/editar")->assertForbidden();
        $this->actingAs($func)->patch("/painel/promocoes/{$promocao->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/painel/promocoes')->assertForbidden();
    }

    // CT-3-23-01
    public function test_filtra_por_situacao(): void
    {
        Promocao::factory()->create(['nome' => 'Promo Vigente']);
        Promocao::factory()->create(['nome' => 'Promo Futura', 'data_inicio' => today()->addDays(2), 'data_fim' => today()->addDays(9)]);
        Promocao::factory()->create(['nome' => 'Promo Velha', 'data_inicio' => today()->subDays(20), 'data_fim' => today()->subDays(10)]);
        Promocao::factory()->create(['nome' => 'Promo Desligada', 'status' => 'inativo']);

        $this->actingAs($this->admin)->get('/painel/promocoes?situacao=vigente')
            ->assertSee('Promo Vigente')->assertDontSee('Promo Futura')->assertDontSee('Promo Velha')->assertDontSee('Promo Desligada');
        $this->actingAs($this->admin)->get('/painel/promocoes?situacao=agendada')->assertSee('Promo Futura')->assertDontSee('Promo Vigente');
        $this->actingAs($this->admin)->get('/painel/promocoes?situacao=expirada')->assertSee('Promo Velha')->assertDontSee('Promo Vigente');
        $this->actingAs($this->admin)->get('/painel/promocoes?situacao=inativa')->assertSee('Promo Desligada')->assertDontSee('Promo Vigente');
    }

    // CT-3-23-02
    public function test_pesquisa_codigo_inexistente(): void
    {
        Promocao::factory()->cupom('MARY20')->create();

        $this->actingAs($this->admin)->get('/painel/promocoes?busca=nada99')->assertSee('Nenhuma promoção encontrada.');
        $this->actingAs($this->admin)->get('/painel/promocoes?busca=mary')->assertSee('MARY20');
    }

    // CT-3-24-01 (e a auditoria guarda o antes e o depois)
    public function test_prorroga_data_final_com_auditoria(): void
    {
        $promocao = Promocao::factory()->cupom('MARY20')->create(['data_fim' => today()->addDays(5)]);
        $novaData = today()->addDays(30)->toDateString();

        $this->actingAs($this->admin)->put("/painel/promocoes/{$promocao->id}", $this->dados(['data_fim' => $novaData, 'status' => null]))
            ->assertRedirect(route('painel.promocoes.index'));

        $this->assertSame($novaData, $promocao->fresh()->data_fim->toDateString());
        $detalhes = Auditoria::where('acao', 'editou')->sole()->detalhes;
        $this->assertSame(today()->addDays(5)->toDateString(), $detalhes['antes']['data_fim']);
        $this->assertSame($novaData, $detalhes['depois']['data_fim']);
    }

    // CT-3-24-02
    public function test_edicao_com_60_por_cento_e_recusada(): void
    {
        $promocao = Promocao::factory()->create(['valor' => 15]);

        $this->actingAs($this->admin)->put("/painel/promocoes/{$promocao->id}", $this->dados(['modo' => 'automatica', 'valor' => '60', 'status' => null]))
            ->assertSessionHasErrors(['valor' => 'O desconto percentual deve ficar entre 1% e 50%.']);
        $this->assertSame('15.00', $promocao->fresh()->valor);
    }

    public function test_promocao_usada_nao_muda_modo_nem_codigo_e_pedido_mantem_desconto(): void
    {
        $promocao = Promocao::factory()->cupom('MARY20')->create(['valor' => 20]);
        $cliente = User::factory()->create();
        $pedido = new Pedido;
        $pedido->user_id = $cliente->id;
        $pedido->subtotal = 100; $pedido->desconto = 20; $pedido->total = 80;
        $pedido->promocao_id = $promocao->id; $pedido->cupom = 'MARY20';
        foreach (['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $cliente->{$campo};
        }
        $pedido->save();

        $this->actingAs($this->admin)->get("/painel/promocoes/{$promocao->id}/editar")->assertSee('modo e código não podem ser alterados');

        $this->actingAs($this->admin)->put("/painel/promocoes/{$promocao->id}", $this->dados([
            'modo' => 'automatica', 'codigo' => 'OUTRO', 'valor' => '30', 'status' => null,
        ]))->assertSessionHasNoErrors();

        $promocao->refresh();
        $this->assertSame('cupom', $promocao->modo);
        $this->assertSame('MARY20', $promocao->codigo);
        $this->assertSame('30.00', $promocao->valor);
        $this->assertSame('20.00', $pedido->fresh()->desconto); // pedido não é recalculado
    }

    // CT-3-25-01
    public function test_inativa_e_reativa_promocao(): void
    {
        $promocao = Promocao::factory()->create();

        $this->actingAs($this->admin)->patch("/painel/promocoes/{$promocao->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');
        $this->assertSame('inativa', $promocao->fresh()->situacao());
        $this->assertSame(0, Promocao::validas()->count());

        $this->actingAs($this->admin)->patch("/painel/promocoes/{$promocao->id}/reativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');
        $this->assertSame('vigente', $promocao->fresh()->situacao());
        $this->assertDatabaseHas('auditorias', ['acao' => 'inativou', 'entidade' => 'promocoes']);
    }

    public function test_alvo_por_categoria_exige_categoria_ativa(): void
    {
        $inativa = Categoria::factory()->create(['status' => 'inativo']);

        $this->actingAs($this->admin)->post('/painel/promocoes', $this->dados(['aplicacao' => 'categoria', 'categoria_id' => $inativa->id]))
            ->assertSessionHasErrors(['categoria_id' => 'Escolha um item ativo.']);
    }
}
