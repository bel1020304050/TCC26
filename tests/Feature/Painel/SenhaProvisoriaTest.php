<?php

namespace Tests\Feature\Painel;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Senha provisória da equipe: quem pode redefinir e a troca obrigatória no próximo login.
 */
class SenhaProvisoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gerente;
    private User $atendente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->gerente = User::factory()->gerente()->create();
        $this->atendente = User::factory()->atendente()->create(['password' => 'antiga1234']);
    }

    private function redefinir(User $quem, User $alvo)
    {
        return $this->actingAs($quem)->from('/painel/usuarios')->post("/painel/usuarios/{$alvo->id}/redefinir-senha");
    }

    public function test_gerente_redefine_senha_do_atendente(): void
    {
        DB::table('sessions')->insert(['id' => 'sessao-atendente', 'user_id' => $this->atendente->id, 'payload' => '', 'last_activity' => time()]);

        $resposta = $this->redefinir($this->gerente, $this->atendente)->assertRedirect('/painel/usuarios')->assertSessionHas('sucesso');
        $senha = $resposta->getSession()->get('senha_gerada')['senha'];

        $atendente = $this->atendente->fresh();
        $this->assertTrue($atendente->senha_provisoria);
        $this->assertTrue(Hash::check($senha, $atendente->password));
        $this->assertFalse(Hash::check('antiga1234', $atendente->password)); // a senha antiga deixou de valer
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->atendente->id]);  // sessões abertas encerradas

        // Auditoria registra quem redefiniu, sem a senha
        $registro = Auditoria::where('acao', 'redefiniu_senha')->sole();
        $this->assertSame($this->gerente->id, $registro->user_id);
        $this->assertStringNotContainsString($senha, json_encode($registro->detalhes));
    }

    // Regra: o Gerente só redefine a senha de Atendentes; a do Gerente e a do Administrador ficam com o Administrador
    public function test_gerente_nao_redefine_senha_de_gerente_nem_de_admin(): void
    {
        $outroGerente = User::factory()->gerente()->create();

        $this->redefinir($this->gerente, $outroGerente)->assertForbidden();
        $this->redefinir($this->gerente, $this->admin)->assertForbidden();
        $this->redefinir($this->gerente, $this->gerente)->assertForbidden();

        $this->redefinir($this->admin, $outroGerente)->assertSessionHas('sucesso');
        $this->assertTrue($outroGerente->fresh()->senha_provisoria);
    }

    public function test_atendente_cliente_e_a_propria_conta_nao_redefinem(): void
    {
        $outro = User::factory()->atendente()->create();

        $this->redefinir($this->atendente, $outro)->assertForbidden();
        $this->redefinir($this->admin, $this->admin)->assertForbidden();               // a própria senha muda em "Minha conta"
        $this->redefinir($this->admin, User::factory()->create())->assertForbidden(); // cliente usa "Esqueci minha senha"
        $this->redefinir($this->admin, User::factory()->atendente()->inativo()->create())->assertForbidden();
    }

    public function test_lista_mostra_o_botao_so_para_quem_pode(): void
    {
        $this->actingAs($this->gerente)->get('/painel/usuarios')
            ->assertSee(route('painel.usuarios.redefinir-senha', $this->atendente))
            ->assertDontSee(route('painel.usuarios.redefinir-senha', $this->admin));

        $this->actingAs($this->admin)->get('/painel/usuarios')
            ->assertSee(route('painel.usuarios.redefinir-senha', $this->gerente))
            ->assertDontSee(route('painel.usuarios.redefinir-senha', $this->admin));
    }

    // Quem está com senha provisória só abre a tela de criar a senha (e consegue sair)
    public function test_senha_provisoria_bloqueia_o_sistema_ate_trocar(): void
    {
        $this->atendente->forceFill(['senha_provisoria' => true])->save();

        $this->actingAs($this->atendente)->get('/painel')->assertRedirect(route('senha-provisoria.edit'));
        $this->actingAs($this->atendente)->get('/painel/produtos')->assertRedirect(route('senha-provisoria.edit'));
        $this->actingAs($this->atendente)->get('/criar-nova-senha')->assertOk()->assertSee('senha provisória');
        $this->actingAs($this->atendente)->post('/sair')->assertRedirect('/');
    }

    public function test_troca_a_senha_provisoria_e_libera_o_painel(): void
    {
        $this->atendente->forceFill(['senha_provisoria' => true])->save();

        // Não pode repetir a senha provisória
        $this->actingAs($this->atendente)->put('/criar-nova-senha', ['password' => 'antiga1234', 'password_confirmation' => 'antiga1234'])
            ->assertSessionHasErrors(['password' => 'Crie uma senha diferente da senha provisória.']);
        $this->actingAs($this->atendente)->put('/criar-nova-senha', ['password' => 'curta', 'password_confirmation' => 'curta'])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->atendente)->put('/criar-nova-senha', ['password' => 'minhaSenha9', 'password_confirmation' => 'minhaSenha9'])
            ->assertRedirect(route('painel.inicio'));

        $atendente = $this->atendente->fresh();
        $this->assertFalse($atendente->senha_provisoria);
        $this->assertTrue(Hash::check('minhaSenha9', $atendente->password));
        $this->assertDatabaseHas('auditorias', ['acao' => 'trocou_senha_provisoria', 'entidade_id' => $this->atendente->id]);
        $this->actingAs($atendente)->get('/painel')->assertOk();
    }

    public function test_sem_senha_provisoria_a_tela_de_troca_volta_para_o_inicio(): void
    {
        $this->actingAs($this->atendente)->get('/criar-nova-senha')->assertRedirect(route('painel.inicio'));
    }

    // Fluxo completo como na apresentação: admin redefine, pessoa entra com a provisória e cria a própria
    public function test_fluxo_completo_de_quem_esqueceu_a_senha(): void
    {
        $senha = $this->redefinir($this->admin, $this->atendente)->getSession()->get('senha_gerada')['senha'];
        auth()->logout();

        $this->post('/entrar', ['email' => $this->atendente->email, 'password' => 'antiga1234'])->assertSessionHasErrors('email');
        $this->post('/entrar', ['email' => strtoupper($this->atendente->email), 'password' => $senha]);
        $this->get('/painel')->assertRedirect(route('senha-provisoria.edit'));

        $this->put('/criar-nova-senha', ['password' => 'novaSenha77', 'password_confirmation' => 'novaSenha77'])->assertRedirect(route('painel.inicio'));
        $this->get('/painel')->assertOk();
    }
}
