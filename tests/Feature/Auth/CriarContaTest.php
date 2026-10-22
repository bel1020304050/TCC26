<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes da story 3.9 – Criar Conta.
 */
class CriarContaTest extends TestCase
{
    use RefreshDatabase;

    private function dadosValidos(array $troca = []): array
    {
        return array_merge([
            'name' => 'Ana Souza',
            'email' => 'ana@email.com',
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
    }

    // RNF.01 da 3.9: "perfil" enviado pelo navegador é ignorado
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
            ->assertSessionHasErrors(['cep' => 'Informe o CEP no formato 00000-000.']);
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
