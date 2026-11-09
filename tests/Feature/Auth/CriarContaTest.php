<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro de cliente ("Criar conta").
 */
class CriarContaTest extends TestCase
{
    use RefreshDatabase;

    private function dadosValidos(array $troca = []): array
    {
        return array_merge([
            'name' => 'Ana Souza',
            'email' => 'ana@email.com',
            'cpf' => '123.456.789-09',
            'telefone' => '(44) 99999-0000',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
            'cep' => '87501000',
            'rua' => 'Avenida Brasil',
            'numero' => '120',
            'complemento' => 'Apto 32',
            'bairro' => 'Centro',
            'cidade' => 'Umuarama',
            'uf' => 'pr',
        ], $troca);
    }

    public function test_tela_de_criar_conta_abre(): void
    {
        $this->get('/criar-conta')->assertOk()->assertSee('Endereço de entrega')->assertSee('ViaCEP');
    }

    // CT-3-9-01
    public function test_cria_conta_de_cliente_com_endereco_completo(): void
    {
        $this->post('/criar-conta', $this->dadosValidos())
            ->assertRedirect(route('loja.inicio'))
            ->assertSessionHas('sucesso');

        $usuario = User::where('email', 'ana@email.com')->firstOrFail();

        $this->assertAuthenticatedAs($usuario);
        $this->assertSame(User::PERFIL_CLIENTE, $usuario->perfil);
        $this->assertSame(User::STATUS_ATIVO, $usuario->status);
        $this->assertSame('87501-000', $usuario->cep); // CEP formatado
        $this->assertSame('PR', $usuario->uf);         // UF em maiúsculas
        $this->assertSame('Apto 32', $usuario->complemento);
        $this->assertSame('12345678909', $usuario->cpf); // CPF salvo só com números
    }

    // Segurança: um campo "perfil" enviado pelo navegador é ignorado
    public function test_nao_e_possivel_se_cadastrar_como_administrador(): void
    {
        $this->post('/criar-conta', $this->dadosValidos(['perfil' => 'admin', 'status' => 'ativo']));

        $this->assertSame(User::PERFIL_CLIENTE, User::where('email', 'ana@email.com')->value('perfil'));
    }

    // CT-3-9-02
    public function test_email_duplicado_e_recusado(): void
    {
        User::factory()->create(['email' => 'ana@email.com']);

        $this->post('/criar-conta', $this->dadosValidos(['email' => 'ANA@email.com']))
            ->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.']);

        $this->assertSame(1, User::count());
    }

    public function test_complemento_e_telefone_sao_opcionais(): void
    {
        $this->post('/criar-conta', $this->dadosValidos(['complemento' => '', 'telefone' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_cep_invalido_e_recusado(): void
    {
        $this->post('/criar-conta', $this->dadosValidos(['cep' => '1234']))
            ->assertSessionHasErrors(['cep' => 'O CEP deve ter 8 números (ex.: 87501-000).']);
    }

    // Regra: CEP com 7 números é recusado; o sistema não completa com zero
    public function test_cep_com_sete_numeros_e_recusado_sem_completar(): void
    {
        $this->post('/criar-conta', $this->dadosValidos(['cep' => '8750100']))
            ->assertSessionHasErrors(['cep' => 'O CEP deve ter 8 números (ex.: 87501-000).']);
        $this->post('/criar-conta', $this->dadosValidos(['cep' => '87501-00']))
            ->assertSessionHasErrors('cep');

        $this->assertSame(0, User::count());
    }

    public function test_cpf_e_obrigatorio_e_validado(): void
    {
        $this->post('/criar-conta', $this->dadosValidos(['cpf' => '']))
            ->assertSessionHasErrors(['cpf' => 'O campo CPF é obrigatório.']);
        $this->post('/criar-conta', $this->dadosValidos(['cpf' => '123.456.789-00']))
            ->assertSessionHasErrors(['cpf' => 'Informe um CPF válido.']);
        $this->post('/criar-conta', $this->dadosValidos(['cpf' => '111.111.111-11']))
            ->assertSessionHasErrors('cpf');
        // CNPJ não serve como CPF de cliente
        $this->post('/criar-conta', $this->dadosValidos(['cpf' => '12.345.678/0001-95']))
            ->assertSessionHasErrors('cpf');

        $this->assertSame(0, User::count());
    }

    public function test_cpf_duplicado_e_recusado_mesmo_com_outra_pontuacao(): void
    {
        User::factory()->create(['cpf' => '12345678909']);

        $this->post('/criar-conta', $this->dadosValidos(['email' => 'outra@email.com', 'cpf' => '12345678909']))
            ->assertSessionHasErrors(['cpf' => 'Este CPF já está cadastrado em outra conta.']);
    }

    public function test_tela_tem_campo_cpf_com_mascara(): void
    {
        $this->get('/criar-conta')->assertSee('name="cpf"', false)->assertSee('data-mascara-documento="cpf"', false);
    }

    public function test_campos_obrigatorios_do_endereco(): void
    {
        $this->post('/criar-conta', $this->dadosValidos(['bairro' => '', 'cidade' => '', 'uf' => 'XX']))
            ->assertSessionHasErrors(['bairro', 'cidade', 'uf']);
    }

    public function test_senha_curta_ou_sem_confirmacao_e_recusada(): void
    {
        $this->post('/criar-conta', $this->dadosValidos(['password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors('password');

        $this->post('/criar-conta', $this->dadosValidos(['password_confirmation' => 'outra12345']))
            ->assertSessionHasErrors('password');
    }
}
