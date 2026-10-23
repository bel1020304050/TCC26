<?php

namespace Tests\Feature\Painel;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Produto;
use App\Models\User;
use App\Support\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes do CRUD de Categorias — stories 3.14 a 3.17.
 */
class CategoriaTest extends TestCase
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

    // CT-3-14-01
    public function test_admin_cadastra_categoria(): void
    {
        $this->actingAs($this->admin)
            ->post('/painel/categorias', ['nome' => 'Acessórios', 'descricao' => 'Bolsas e cintos', 'status' => 'ativo'])
            ->assertRedirect(route('painel.categorias.index'))
            ->assertSessionHas('sucesso');

        $categoria = Categoria::where('nome', 'Acessórios')->sole();
        $this->assertTrue($categoria->isAtivo());
        $this->assertDatabaseHas('auditorias', ['acao' => 'criou', 'entidade' => 'categorias', 'entidade_id' => $categoria->id, 'user_id' => $this->admin->id]);
    }

    // CT-3-14-02: nome repetido, sem diferenciar maiúsculas/minúsculas e incluindo inativas
    public function test_nome_repetido_e_recusado(): void
    {
        Categoria::factory()->inativa()->create(['nome' => 'Vestidos']);

        $this->actingAs($this->admin)
            ->post('/painel/categorias', ['nome' => 'vestidos', 'status' => 'ativo'])
            ->assertSessionHasErrors(['nome' => 'Já existe uma categoria com este nome.']);

        $this->assertSame(1, Categoria::count());
    }

    public function test_nome_e_status_inicial_sao_obrigatorios(): void
    {
        $this->actingAs($this->admin)
            ->post('/painel/categorias', ['nome' => '', 'status' => 'qualquer'])
            ->assertSessionHasErrors(['nome', 'status']);
    }

    // CT-3-15-01: funcionário consulta, mas sem botões de alteração
    public function test_funcionario_consulta_sem_botoes_de_alteracao(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Vestidos']);
        Produto::factory()->count(2)->create(['categoria_id' => $categoria->id]);
        Produto::factory()->inativo()->create(['categoria_id' => $categoria->id]);

        $this->actingAs($this->funcionario)->get('/painel/categorias')
            ->assertOk()
            ->assertSee('Vestidos')
            ->assertSeeInOrder(['Vestidos', '2', '3']) // 2 ativos, 3 vinculados
            ->assertDontSee('Nova categoria')
            ->assertDontSee(route('painel.categorias.edit', $categoria));
    }

    public function test_funcionario_nao_cadastra_nem_edita_nem_inativa(): void
    {
        $categoria = Categoria::factory()->create();

        $this->actingAs($this->funcionario)->get('/painel/categorias/novo')->assertForbidden();
        $this->actingAs($this->funcionario)->post('/painel/categorias', ['nome' => 'X', 'status' => 'ativo'])->assertForbidden();
        $this->actingAs($this->funcionario)->put("/painel/categorias/{$categoria->id}", ['nome' => 'Y'])->assertForbidden();
        $this->actingAs($this->funcionario)->patch("/painel/categorias/{$categoria->id}/inativar")->assertForbidden();

        $this->assertTrue($categoria->fresh()->isAtivo());
    }

    public function test_cliente_nao_acessa_categorias_do_painel(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel/categorias')->assertForbidden();
    }

    // CT-3-15-02
    public function test_pesquisa_sem_resultado(): void
    {
        Categoria::factory()->create(['nome' => 'Vestidos']);

        $this->actingAs($this->admin)->get('/painel/categorias?busca=inexistente')
            ->assertOk()
            ->assertSee('Nenhuma categoria encontrada');
    }

    public function test_filtro_por_status(): void
    {
        Categoria::factory()->create(['nome' => 'Saias']);
        Categoria::factory()->inativa()->create(['nome' => 'Jaquetas']);

        $this->actingAs($this->admin)->get('/painel/categorias?status=inativo')
            ->assertSee('Jaquetas')
            ->assertDontSee('Saias');
    }

    // CT-3-16-01: editar mantém os produtos vinculados
    public function test_editar_mantem_produtos_e_nao_muda_status(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Vestidos', 'descricao' => 'Antiga']);
        $produto = Produto::factory()->create(['categoria_id' => $categoria->id]);

        $this->actingAs($this->admin)
            ->put("/painel/categorias/{$categoria->id}", ['nome' => 'Vestidos', 'descricao' => 'Nova descrição', 'status' => 'inativo'])
            ->assertRedirect(route('painel.categorias.index'));

        $categoria->refresh();
        $this->assertSame('Nova descrição', $categoria->descricao);
        $this->assertTrue($categoria->isAtivo()); // status ignorado na edição
        $this->assertSame($categoria->id, $produto->fresh()->categoria_id);
        $this->assertSame('Antiga', Auditoria::where('acao', 'editou')->sole()->detalhes['antes']['descricao']);
    }

    // CT-3-16-02
    public function test_renomear_para_nome_de_outra_categoria_e_recusado(): void
    {
        Categoria::factory()->create(['nome' => 'Vestidos']);
        $saias = Categoria::factory()->create(['nome' => 'Saias']);

        $this->actingAs($this->admin)
            ->put("/painel/categorias/{$saias->id}", ['nome' => 'VESTIDOS'])
            ->assertSessionHasErrors('nome');

        $this->assertSame('Saias', $saias->fresh()->nome);
    }

    public function test_manter_o_proprio_nome_na_edicao_e_permitido(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Vestidos']);

        $this->actingAs($this->admin)
            ->put("/painel/categorias/{$categoria->id}", ['nome' => 'Vestidos'])
            ->assertSessionHasNoErrors();
    }

    // CT-3-17-01
    public function test_inativa_categoria_sem_produtos_ativos(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Acessórios']);
        Produto::factory()->inativo()->create(['categoria_id' => $categoria->id]); // inativo não bloqueia

        $this->actingAs($this->admin)
            ->patch("/painel/categorias/{$categoria->id}/inativar")
            ->assertSessionHas('sucesso');

        $this->assertSame(Status::INATIVO, $categoria->fresh()->status);
        $this->assertDatabaseHas('auditorias', ['acao' => 'inativou', 'entidade_id' => $categoria->id]);
    }

    // CT-3-17-02
    public function test_nao_inativa_categoria_com_produtos_ativos(): void
    {
        $categoria = Categoria::factory()->create(['nome' => 'Vestidos']);
        Produto::factory()->count(2)->create(['categoria_id' => $categoria->id]);

        $this->actingAs($this->admin)
            ->from('/painel/categorias')
            ->patch("/painel/categorias/{$categoria->id}/inativar")
            ->assertRedirect('/painel/categorias')
            ->assertSessionHas('erro');

        $this->assertTrue($categoria->fresh()->isAtivo());
        $this->assertStringContainsString('2 produto(s) ativo(s)', session('erro'));
    }

    // Melhoria da Fase 3: com produtos ativos, o modal mostra só o aviso e "Entendi"
    public function test_modal_de_categoria_com_produtos_ativos_nao_tem_botao_de_confirmar(): void
    {
        $vestidos = Categoria::factory()->create(['nome' => 'Vestidos']);
        Produto::factory()->count(2)->create(['categoria_id' => $vestidos->id]);
        $acessorios = Categoria::factory()->create(['nome' => 'Acessórios']);

        $html = $this->actingAs($this->admin)->get('/painel/categorias')->assertOk()->getContent();

        $this->assertStringContainsString('Entendi', $html);
        $this->assertStringNotContainsString('action="'.route('painel.categorias.inativar', $vestidos).'"', $html);
        $this->assertStringContainsString('action="'.route('painel.categorias.inativar', $acessorios).'"', $html);
    }

    public function test_reativa_categoria(): void
    {
        $categoria = Categoria::factory()->inativa()->create();

        $this->actingAs($this->admin)->patch("/painel/categorias/{$categoria->id}/reativar")->assertSessionHas('sucesso');

        $this->assertTrue($categoria->fresh()->isAtivo());
        $this->assertDatabaseHas('auditorias', ['acao' => 'reativou', 'entidade_id' => $categoria->id]);
    }
}
