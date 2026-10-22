<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Controle de acesso por perfil (seção 2.2 da documentação).
 */
class PerfisAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_loja_abre_para_visitante(): void
    {
        $this->get('/')->assertOk()->assertSee('Mary Modas');
    }

    public function test_visitante_e_mandado_para_o_login_ao_abrir_o_painel(): void
    {
        $this->get('/painel')->assertRedirect(route('login'));
    }

    public function test_cliente_nao_acessa_o_painel(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($cliente)->get('/painel')->assertForbidden();
    }

    public function test_funcionario_ve_menu_sem_usuarios(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->actingAs($func)->get('/painel')
            ->assertOk()
            ->assertSee('Produtos')
            ->assertDontSee(route('painel.usuarios.index')); // sem o link de Usuários no menu
    }

    // Mesmo pela URL o Funcionário é barrado (CT-3-6-02)
    public function test_funcionario_nao_acessa_usuarios_pela_url(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->actingAs($func)->get('/painel/usuarios')->assertForbidden();
    }

    public function test_administrador_ve_e_acessa_usuarios(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/painel')->assertOk()->assertSee(route('painel.usuarios.index'));
        $this->actingAs($admin)->get('/painel/usuarios')->assertOk();
    }

    // RNF.01 da 3.8: quem é inativado perde a sessão na próxima requisição
    public function test_usuario_inativado_durante_a_sessao_e_desconectado(): void
    {
        $func = User::factory()->funcionario()->create();
        $this->actingAs($func)->get('/painel')->assertOk();

        $func->forceFill(['status' => User::STATUS_INATIVO])->save();

        $this->get('/painel')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
