<?php

namespace Tests\Feature\Painel;

use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\User;
use App\Support\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes do CRUD de Fornecedores — stories 3.26 a 3.29.
 */
class FornecedorTest extends TestCase
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

    private function dados(array $troca = []): array
    {
        return array_merge([
            'nome' => 'Confecções Aurora Ltda',
            'documento' => '12.345.678/0001-95',
            'telefone' => '(44) 3622-1000',
            'email' => 'contato@aurora.com.br',
            'status' => 'ativo',
        ], $troca);
    }

    // CT-3-26-01
    public function test_admin_cadastra_fornecedor_com_cnpj(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados())
            ->assertRedirect(route('painel.fornecedores.index'))
            ->assertSessionHas('sucesso');

        $fornecedor = Fornecedor::sole();
        $this->assertSame('12345678000195', $fornecedor->documento);           // salvo só com números
        $this->assertSame('12.345.678/0001-95', $fornecedor->documentoFormatado());
    }

    public function test_cpf_valido_tambem_e_aceito(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '529.982.247-25']))
            ->assertSessionHasNoErrors();
    }

    public function test_cpf_cnpj_e_contatos_sao_opcionais(): void
    {
        $this->actingAs($this->admin)
            ->post('/painel/fornecedores', ['nome' => 'Bijus Maringá Ltda', 'documento' => '', 'telefone' => '', 'email' => '', 'status' => 'ativo'])
            ->assertSessionHasNoErrors();

        $this->assertNull(Fornecedor::sole()->documento);
    }

    public function test_varios_fornecedores_sem_documento_sao_permitidos(): void
    {
        Fornecedor::factory()->create(['documento' => null]);

        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Fornecedor::count());
    }

    public function test_documento_invalido_e_recusado(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '12.345.678/0001-00']))
            ->assertSessionHasErrors(['documento' => 'Informe um CPF ou CNPJ válido.']);

        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '111.111.111-11']))
            ->assertSessionHasErrors('documento');
    }

    // CT-3-26-02: CNPJ duplicado, mesmo digitado sem pontuação e mesmo com o outro inativo
    public function test_cnpj_duplicado_e_recusado(): void
    {
        Fornecedor::factory()->inativo()->create(['documento' => '12345678000195']);

        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '12345678000195']))
            ->assertSessionHasErrors(['documento' => 'Este documento já pertence a outro fornecedor.']);
    }

    public function test_email_invalido_e_recusado(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['email' => 'nao-e-email']))
            ->assertSessionHasErrors('email');
    }

    // CT-3-27-01: funcionário pesquisa, sem botões de alteração
    public function test_funcionario_pesquisa_por_nome_sem_botoes(): void
    {
        $aurora = Fornecedor::factory()->create(['nome' => 'Confecções Aurora Ltda']);
        Fornecedor::factory()->create(['nome' => 'Malharia Boa Vista Ltda']);

        $this->actingAs($this->funcionario)->get('/painel/fornecedores?busca=aurora')
            ->assertOk()
            ->assertSee('Confecções Aurora Ltda')
            ->assertDontSee('Malharia Boa Vista')
            ->assertDontSee('Novo fornecedor')
            ->assertDontSee(route('painel.fornecedores.edit', $aurora));
    }

    public function test_pesquisa_por_cnpj_com_ou_sem_pontuacao(): void
    {
        Fornecedor::factory()->create(['nome' => 'Aurora', 'documento' => '12345678000195']);

        $this->actingAs($this->admin)->get('/painel/fornecedores?busca=12.345.678')->assertSee('Aurora');
        $this->actingAs($this->admin)->get('/painel/fornecedores?busca=12345678000195')->assertSee('Aurora');
    }

    // CT-3-27-02
    public function test_pesquisa_documento_inexistente(): void
    {
        Fornecedor::factory()->create(['documento' => '12345678000195']);

        $this->actingAs($this->admin)->get('/painel/fornecedores?busca=98765432000198')
            ->assertSee('Nenhum fornecedor encontrado');
    }

    public function test_funcionario_e_cliente_nao_alteram(): void
    {
        $fornecedor = Fornecedor::factory()->create();

        $this->actingAs($this->funcionario)->get('/painel/fornecedores/novo')->assertForbidden();
        $this->actingAs($this->funcionario)->get("/painel/fornecedores/{$fornecedor->id}/editar")->assertForbidden();
        $this->actingAs($this->funcionario)->patch("/painel/fornecedores/{$fornecedor->id}/inativar")->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/painel/fornecedores')->assertForbidden();
    }

    // CT-3-28-01
    public function test_editar_telefone_mantem_produtos(): void
    {
        $fornecedor = Fornecedor::factory()->create(['documento' => '12345678000195']);
        $produto = Produto::factory()->create(['fornecedor_id' => $fornecedor->id]);

        $this->actingAs($this->admin)
            ->put("/painel/fornecedores/{$fornecedor->id}", $this->dados(['telefone' => '(44) 3622-1050']))
            ->assertRedirect(route('painel.fornecedores.index'));

        $this->assertSame('(44) 3622-1050', $fornecedor->fresh()->telefone);
        $this->assertSame($fornecedor->id, $produto->fresh()->fornecedor_id);
    }

    // CT-3-28-02
    public function test_editar_para_documento_de_outro_fornecedor_e_recusado(): void
    {
        Fornecedor::factory()->create(['documento' => '98765432000198']);
        $aurora = Fornecedor::factory()->create(['documento' => '12345678000195']);

        $this->actingAs($this->admin)
            ->put("/painel/fornecedores/{$aurora->id}", $this->dados(['documento' => '98.765.432/0001-98']))
            ->assertSessionHasErrors(['documento' => 'Este documento já pertence a outro fornecedor.']);
    }

    // CT-3-29-01
    public function test_inativa_fornecedor_sem_produtos_ativos(): void
    {
        $fornecedor = Fornecedor::factory()->create();

        $this->actingAs($this->admin)->patch("/painel/fornecedores/{$fornecedor->id}/inativar")->assertSessionHas('sucesso');

        $this->assertSame(Status::INATIVO, $fornecedor->fresh()->status);
        $this->assertDatabaseHas('auditorias', ['acao' => 'inativou', 'entidade' => 'fornecedores', 'entidade_id' => $fornecedor->id]);
    }

    // CT-3-29-02
    public function test_nao_inativa_fornecedor_com_produtos_ativos(): void
    {
        $fornecedor = Fornecedor::factory()->create();
        Produto::factory()->count(4)->create(['fornecedor_id' => $fornecedor->id]);

        $this->actingAs($this->admin)->patch("/painel/fornecedores/{$fornecedor->id}/inativar")->assertSessionHas('erro');

        $this->assertTrue($fornecedor->fresh()->isAtivo());
    }

    public function test_reativa_fornecedor(): void
    {
        $fornecedor = Fornecedor::factory()->inativo()->create();

        $this->actingAs($this->admin)->patch("/painel/fornecedores/{$fornecedor->id}/reativar")->assertSessionHas('sucesso');

        $this->assertTrue($fornecedor->fresh()->isAtivo());
    }
}
