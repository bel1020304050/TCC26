<?php

namespace Tests\Feature\Painel;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Testes do CRUD de Usuários internos — stories 3.5 a 3.8.
 */
class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Mary Oliveira']);
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'name' => 'Rafael Martins',
            'email' => 'rafael@marymodas.com.br',
            'telefone' => '(44) 99876-1020',
            'cargo' => 'Subgerente',
            'perfil' => 'funcionario',
            'status' => 'ativo',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ], $troca);
    }

    // CT-3-5-01
    public function test_admin_cadastra_funcionario_que_consegue_entrar(): void
    {
        $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados())
            ->assertRedirect(route('painel.usuarios.index'))
            ->assertSessionHas('sucesso');

        $rafael = User::where('email', 'rafael@marymodas.com.br')->sole();
        $this->assertTrue($rafael->isFuncionario());
        $this->assertSame('Subgerente', $rafael->cargo);
        $this->assertTrue(Hash::check('senha1234', $rafael->password));
        $this->assertDatabaseHas('auditorias', ['acao' => 'criou', 'entidade' => 'users', 'entidade_id' => $rafael->id]);

        // Consegue fazer login
        auth()->logout();
        $this->post('/entrar', ['email' => 'rafael@marymodas.com.br', 'password' => 'senha1234'])
            ->assertRedirect(route('painel.inicio'));
    }

    // CT-3-5-02: e-mail único em todo o sistema (inclusive clientes)
    public function test_email_ja_usado_por_cliente_e_recusado(): void
    {
        User::factory()->create(['email' => 'rafael@marymodas.com.br']);

        $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados(['email' => 'RAFAEL@marymodas.com.br']))
            ->assertSessionHasErrors('email');
    }

    public function test_nao_cria_cliente_pelo_painel(): void
    {
        $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados(['perfil' => 'cliente']))
            ->assertSessionHasErrors('perfil');
    }

    public function test_senha_curta_ou_sem_confirmacao(): void
    {
        $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados(['password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados(['password_confirmation' => 'outra1234']))
            ->assertSessionHasErrors('password');
    }

    // CT-3-6-01: pesquisa
    public function test_lista_so_equipe_e_pesquisa_por_nome(): void
    {
        User::factory()->funcionario()->create(['name' => 'Rafael Martins']);
        User::factory()->funcionario()->create(['name' => 'Juliana Lima']);
        User::factory()->create(['name' => 'Ana Cliente']);

        $this->actingAs($this->admin)->get('/painel/usuarios')
            ->assertOk()
            ->assertSee('Rafael Martins')
            ->assertDontSee('Ana Cliente');

        $this->actingAs($this->admin)->get('/painel/usuarios?busca=rafael')
            ->assertSee('Rafael Martins')
            ->assertDontSee('Juliana Lima');
    }

    public function test_filtros_por_perfil_e_status(): void
    {
        User::factory()->funcionario()->inativo()->create(['name' => 'Juliana Lima']);
        User::factory()->admin()->create(['name' => 'Bruno Costa']);

        $this->actingAs($this->admin)->get('/painel/usuarios?status=inativo')->assertSee('Juliana Lima')->assertDontSee('Bruno Costa');
        $this->actingAs($this->admin)->get('/painel/usuarios?perfil=admin')->assertSee('Bruno Costa')->assertDontSee('Juliana Lima');
    }

    // CT-3-6-02
    public function test_funcionario_nao_acessa_usuarios(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->actingAs($func)->get('/painel/usuarios')->assertForbidden();
        $this->actingAs($func)->get('/painel/usuarios/novo')->assertForbidden();
        $this->actingAs($func)->post('/painel/usuarios', $this->dados())->assertForbidden();
    }

    public function test_senha_nunca_aparece_na_listagem(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->actingAs($this->admin)->get('/painel/usuarios')->assertDontSee($func->password);
    }

    // CT-3-7-01: promover funcionário a administrador
    public function test_promove_funcionario_a_administrador_com_auditoria(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->actingAs($this->admin)
            ->put("/painel/usuarios/{$func->id}", $this->dados(['name' => $func->name, 'email' => $func->email, 'perfil' => 'admin', 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect(route('painel.usuarios.index'));

        $this->assertTrue($func->fresh()->isAdmin());
        $detalhes = Auditoria::where('acao', 'editou')->sole()->detalhes;
        $this->assertSame('funcionario', $detalhes['antes']['perfil']);
        $this->assertSame('admin', $detalhes['depois']['perfil']);
        $this->assertFalse($detalhes['senha_alterada']);
    }

    // CT-3-7-02
    public function test_admin_nao_altera_o_proprio_perfil(): void
    {
        $this->actingAs($this->admin)
            ->put("/painel/usuarios/{$this->admin->id}", $this->dados(['name' => $this->admin->name, 'email' => $this->admin->email, 'perfil' => 'funcionario', 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors(['perfil' => 'Você não pode alterar o seu próprio perfil.']);

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_senha_em_branco_mantem_a_atual_e_nova_senha_troca(): void
    {
        $func = User::factory()->funcionario()->create(['password' => 'antiga1234']);
        $base = ['name' => $func->name, 'email' => $func->email];

        $this->actingAs($this->admin)->put("/painel/usuarios/{$func->id}", $this->dados($base + ['password' => '', 'password_confirmation' => '']));
        $this->assertTrue(Hash::check('antiga1234', $func->fresh()->password));

        $this->actingAs($this->admin)->put("/painel/usuarios/{$func->id}", $this->dados($base + ['password' => 'nova12345', 'password_confirmation' => 'nova12345']));
        $this->assertTrue(Hash::check('nova12345', $func->fresh()->password));
    }

    public function test_nao_edita_cliente_pelo_painel(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($this->admin)->get("/painel/usuarios/{$cliente->id}/editar")->assertForbidden();
    }

    // CT-3-8-01: inativar derruba o acesso
    public function test_inativa_funcionario_e_bloqueia_login(): void
    {
        $func = User::factory()->funcionario()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$func->id}/inativar")->assertSessionHas('sucesso');

        $this->assertSame(User::STATUS_INATIVO, $func->fresh()->status);
        $this->assertDatabaseHas('auditorias', ['acao' => 'inativou', 'entidade' => 'users', 'entidade_id' => $func->id]);

        auth()->logout();
        $this->post('/entrar', ['email' => $func->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reativa_funcionario(): void
    {
        $func = User::factory()->funcionario()->inativo()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$func->id}/reativar")->assertSessionHas('sucesso');

        $this->assertTrue($func->fresh()->isAtivo());
    }

    // CT-3-8-02
    public function test_admin_nao_inativa_a_propria_conta(): void
    {
        $this->actingAs($this->admin)->patch("/painel/usuarios/{$this->admin->id}/inativar")->assertForbidden();

        $this->assertTrue($this->admin->fresh()->isAtivo());
        $this->actingAs($this->admin)->get('/painel/usuarios')->assertDontSee(route('painel.usuarios.inativar', $this->admin));
    }

    public function test_admin_pode_inativar_outro_admin(): void
    {
        $bruno = User::factory()->admin()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$bruno->id}/inativar")->assertSessionHas('sucesso');

        $this->assertSame(User::STATUS_INATIVO, $bruno->fresh()->status);
    }

    public function test_sempre_existe_pelo_menos_um_admin_ativo(): void
    {
        // Cenário forçado: o único outro admin ativo seria rebaixado por um admin que já está inativo no banco
        $bruno = User::factory()->admin()->create();
        $this->admin->forceFill(['status' => User::STATUS_INATIVO])->save();
        $this->actingAs($this->admin->fresh()); // simulação direta do controller, ignorando o middleware de sessão

        $resposta = $this->withoutMiddleware(\App\Http\Middleware\EncerraSessaoInativa::class)
            ->patch("/painel/usuarios/{$bruno->id}/inativar");

        $resposta->assertSessionHas('erro', 'O sistema precisa de pelo menos um Administrador ativo.');
        $this->assertTrue($bruno->fresh()->isAtivo());
    }

    public function test_cliente_nao_e_inativado_por_esta_tela(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$cliente->id}/inativar")->assertForbidden();
        $this->assertTrue($cliente->fresh()->isAtivo());
    }
}
