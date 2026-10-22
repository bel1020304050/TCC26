<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes da story 3.13 – Login e Logout.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_de_login_abre(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Entrar');
    }

    // CT-3-13-01: funcionário entra e cai no painel
    public function test_funcionario_entra_e_vai_para_o_painel(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->post('/entrar', ['email' => $func->email, 'password' => 'password'])
            ->assertRedirect(route('painel.inicio'));

        $this->assertAuthenticatedAs($func);
        $this->get('/painel')->assertOk()->assertSee('Funcionário');
    }

    public function test_administrador_entra_e_vai_para_o_painel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/entrar', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('painel.inicio'));
    }

    public function test_cliente_entra_e_vai_para_a_loja(): void
    {
        $cliente = User::factory()->create();

        $this->post('/entrar', ['email' => $cliente->email, 'password' => 'password'])
            ->assertRedirect(route('loja.inicio'));
    }

    public function test_email_com_maiusculas_tambem_entra(): void
    {
        $cliente = User::factory()->create(['email' => 'ana@email.com']);

        $this->post('/entrar', ['email' => 'ANA@Email.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($cliente);
    }

    // CT-3-13-02: senha errada → mensagem genérica
    public function test_senha_errada_mostra_mensagem_generica(): void
    {
        $cliente = User::factory()->create();

        $this->from('/entrar')
            ->post('/entrar', ['email' => $cliente->email, 'password' => 'errada123'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_conta_inativa_nao_entra_mesmo_com_senha_certa(): void
    {
        $func = User::factory()->funcionario()->inativo()->create();

        $this->post('/entrar', ['email' => $func->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_conta_encerrada_nao_entra(): void
    {
        $cliente = User::factory()->encerrado()->create();

        $this->post('/entrar', ['email' => $cliente->email, 'password' => 'password']);

        $this->assertGuest();
    }

    // RNF.01 da 3.13: bloqueio após 5 tentativas
    public function test_bloqueia_apos_cinco_tentativas_erradas(): void
    {
        $cliente = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', ['email' => $cliente->email, 'password' => 'errada123']);
        }

        // Na 6ª tentativa, mesmo com a senha certa, fica bloqueado
        $this->post('/entrar', ['email' => $cliente->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)->post('/sair')->assertRedirect(route('loja.inicio'));

        $this->assertGuest();
    }

    public function test_usuario_logado_nao_ve_tela_de_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/entrar')->assertRedirect(route('painel.inicio'));
    }
}
