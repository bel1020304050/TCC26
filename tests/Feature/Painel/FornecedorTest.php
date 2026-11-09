<?php

namespace Tests\Feature\Painel;

use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\User;
use App\Support\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro de Fornecedores no painel.
 */
class FornecedorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $atendente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->atendente = User::factory()->atendente()->create();
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'nome' => 'Confecções Aurora Ltda',
            'tipo_pessoa' => 'juridica',
            'documento' => '12.345.678/0001-95',
            'telefone' => '(44) 3622-1000',
            'email' => 'contato@aurora.com.br',
            'cep' => '87501000',
            'rua' => 'Avenida Paraná',
            'numero' => '3200',
            'complemento' => 'Sala 4',
            'bairro' => 'Centro',
            'cidade' => 'Umuarama',
            'uf' => 'pr',
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
        $this->assertSame(Fornecedor::JURIDICA, $fornecedor->tipo_pessoa);
        // Endereço padronizado: CEP com hífen e UF em maiúsculas
        $this->assertSame(['87501-000', 'Avenida Paraná', '3200', 'Sala 4', 'Centro', 'Umuarama', 'PR'],
            array_values($fornecedor->only(['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'])));
    }

    public function test_pessoa_fisica_aceita_cpf(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['tipo_pessoa' => 'fisica', 'documento' => '529.982.247-25']))
            ->assertSessionHasNoErrors();

        $this->assertSame(Fornecedor::FISICA, Fornecedor::sole()->tipo_pessoa);
        $this->assertSame('Pessoa Física', Fornecedor::sole()->tipoLabel());
    }

    // Regra: o tipo de pessoa decide o documento aceito
    public function test_documento_precisa_combinar_com_o_tipo_de_pessoa(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['tipo_pessoa' => 'fisica', 'documento' => '12.345.678/0001-95']))
            ->assertSessionHasErrors(['documento' => 'Informe um CPF válido.']);

        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['tipo_pessoa' => 'juridica', 'documento' => '529.982.247-25']))
            ->assertSessionHasErrors(['documento' => 'Informe um CNPJ válido.']);

        $this->assertSame(0, Fornecedor::count());
    }

    public function test_tipo_de_pessoa_e_obrigatorio(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['tipo_pessoa' => '']))
            ->assertSessionHasErrors(['tipo_pessoa' => 'Escolha se o fornecedor é Pessoa Física ou Pessoa Jurídica.']);
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['tipo_pessoa' => 'empresa']))
            ->assertSessionHasErrors('tipo_pessoa');
    }

    // Regra: CNPJ alfanumérico (Receita Federal, desde 07/2026) é aceito e salvo em maiúsculas
    public function test_cnpj_alfanumerico_e_aceito(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '12.abc.345/01de-35']))
            ->assertSessionHasNoErrors();

        $fornecedor = Fornecedor::sole();
        $this->assertSame('12ABC34501DE35', $fornecedor->documento);
        $this->assertSame('12.ABC.345/01DE-35', $fornecedor->documentoFormatado());

        // Dígito verificador errado é recusado, e o mesmo CNPJ não pode ser repetido
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '12.ABC.345/01DE-36']))
            ->assertSessionHasErrors(['documento' => 'Informe um CNPJ válido.']);
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['documento' => '12ABC34501DE35']))
            ->assertSessionHasErrors(['documento' => 'Este documento já pertence a outro fornecedor.']);

        // Pesquisa encontra com letras minúsculas e sem pontuação
        $this->actingAs($this->admin)->get('/painel/fornecedores?busca=12abc345')->assertSee('Confecções Aurora Ltda');
    }

    public function test_cpf_cnpj_e_contatos_sao_opcionais(): void
    {
        $this->actingAs($this->admin)
            ->post('/painel/fornecedores', $this->dados(['nome' => 'Bijus Maringá Ltda', 'documento' => '', 'telefone' => '', 'email' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Fornecedor::sole()->documento);
    }

    // O endereço do fornecedor é obrigatório (complemento continua opcional)
    public function test_endereco_e_obrigatorio(): void
    {
        $this->actingAs($this->admin)
            ->post('/painel/fornecedores', $this->dados(['cep' => '', 'rua' => '', 'numero' => '', 'bairro' => '', 'cidade' => '', 'uf' => '']))
            ->assertSessionHasErrors(['cep', 'rua', 'numero', 'bairro', 'cidade', 'uf']);

        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['complemento' => '']))
            ->assertSessionHasNoErrors();
    }

    // Regra: CEP com 7 números é recusado; o sistema não completa com zero
    public function test_cep_com_sete_numeros_e_recusado(): void
    {
        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['cep' => '8750100']))
            ->assertSessionHasErrors(['cep' => 'O CEP deve ter 8 números (ex.: 87501-000).']);

        $this->assertSame(0, Fornecedor::count());
    }

    public function test_formulario_tem_tipo_de_pessoa_endereco_e_viacep(): void
    {
        $this->actingAs($this->admin)->get('/painel/fornecedores/novo')
            ->assertOk()
            ->assertSee('Pessoa Física')->assertSee('Pessoa Jurídica')
            ->assertSee('name="cep"', false)->assertSee('js/viacep.js', false)->assertSee('js/documento.js', false);
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
            ->assertSessionHasErrors(['documento' => 'Informe um CNPJ válido.']);

        $this->actingAs($this->admin)->post('/painel/fornecedores', $this->dados(['tipo_pessoa' => 'fisica', 'documento' => '111.111.111-11']))
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
    public function test_atendente_pesquisa_por_nome_sem_botoes(): void
    {
        $aurora = Fornecedor::factory()->create(['nome' => 'Confecções Aurora Ltda']);
        Fornecedor::factory()->create(['nome' => 'Malharia Boa Vista Ltda']);

        $this->actingAs($this->atendente)->get('/painel/fornecedores?busca=aurora')
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

    public function test_atendente_e_cliente_nao_alteram(): void
    {
        $fornecedor = Fornecedor::factory()->create();

        $this->actingAs($this->atendente)->get('/painel/fornecedores/novo')->assertForbidden();
        $this->actingAs($this->atendente)->get("/painel/fornecedores/{$fornecedor->id}/editar")->assertForbidden();
        $this->actingAs($this->atendente)->patch("/painel/fornecedores/{$fornecedor->id}/inativar")->assertForbidden();
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
        $this->assertSame('Avenida Paraná', $fornecedor->fresh()->rua);
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
