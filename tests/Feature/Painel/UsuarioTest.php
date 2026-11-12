<?php

namespace Tests\Feature\Painel;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cadastro da equipe (usuários do painel).
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
            'perfil' => 'atendente',
            'status' => 'ativo',
        ], $troca);
    }

    // CT-3-5-01: o sistema gera a senha provisória, mostrada uma vez, e a pessoa cria a própria no primeiro login
    public function test_admin_cadastra_atendente_que_consegue_entrar(): void
    {
        $resposta = $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados())
            ->assertRedirect(route('painel.usuarios.index'))
            ->assertSessionHas('sucesso');

        $senha = $resposta->getSession()->get('senha_gerada')['senha'];
        $this->assertSame(10, strlen($senha));

        $rafael = User::where('email', 'rafael@marymodas.com.br')->sole();
        $this->assertTrue($rafael->isAtendente());
        $this->assertTrue($rafael->senha_provisoria);
        $this->assertTrue(Hash::check($senha, $rafael->password));
        $this->assertDatabaseHas('auditorias', ['acao' => 'criou', 'entidade' => 'users', 'entidade_id' => $rafael->id]);

        // A senha aparece na lista logo depois do cadastro, e só dessa vez
        $this->actingAs($this->admin)->withSession(['senha_gerada' => $resposta->getSession()->get('senha_gerada')])
            ->get('/painel/usuarios')->assertSee($senha)->assertSee('não será mostrada de novo');
        $this->actingAs($this->admin)->get('/painel/usuarios')->assertDontSee($senha);

        // Entra com a senha provisória e é levado para criar a própria senha
        auth()->logout();
        $this->post('/entrar', ['email' => 'rafael@marymodas.com.br', 'password' => $senha]);
        $this->get('/painel')->assertRedirect(route('senha-provisoria.edit'));
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

    // Regra: a equipe usa o e-mail da loja (o servidor passa para minúsculas antes de conferir)
    public function test_email_da_equipe_precisa_ser_da_loja(): void
    {
        $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados(['email' => 'rafael@gmail.com']))
            ->assertSessionHasErrors(['email' => 'Use o e-mail da loja (terminado em @marymodas.com.br).']);

        $this->actingAs($this->admin)->post('/painel/usuarios', $this->dados(['email' => ' Rafael@MaryModas.com.br ']))
            ->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'rafael@marymodas.com.br')->exists());
    }

    // Ninguém digita a senha de outra pessoa: o formulário não tem campo de senha
    public function test_formulario_nao_tem_campo_de_senha(): void
    {
        $this->actingAs($this->admin)->get('/painel/usuarios/novo')
            ->assertDontSee('name="password"', false)->assertSee('senha provisória');

        $func = User::factory()->atendente()->create(['password' => 'antiga1234']);
        $this->actingAs($this->admin)->get("/painel/usuarios/{$func->id}/editar")
            ->assertDontSee('name="password"', false)->assertSee('Redefinir senha');

        // Mesmo se alguém enviar uma senha pelo navegador, ela é ignorada
        $this->actingAs($this->admin)->put("/painel/usuarios/{$func->id}", $this->dados(['name' => $func->name, 'email' => $func->email, 'password' => 'invasor123', 'password_confirmation' => 'invasor123']));
        $this->assertTrue(Hash::check('antiga1234', $func->fresh()->password));
    }

    // CT-3-6-01: pesquisa
    public function test_lista_so_equipe_e_pesquisa_por_nome(): void
    {
        User::factory()->atendente()->create(['name' => 'Rafael Martins']);
        User::factory()->atendente()->create(['name' => 'Juliana Lima', 'email' => 'juliana@teste.com']);
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
        User::factory()->atendente()->inativo()->create(['name' => 'Juliana Lima']);
        User::factory()->admin()->create(['name' => 'Bruno Costa']);

        $this->actingAs($this->admin)->get('/painel/usuarios?status=inativo')->assertSee('Juliana Lima')->assertDontSee('Bruno Costa');
        $this->actingAs($this->admin)->get('/painel/usuarios?perfil=admin')->assertSee('Bruno Costa')->assertDontSee('Juliana Lima');
    }

    // CT-3-6-02
    public function test_atendente_nao_acessa_usuarios(): void
    {
        $func = User::factory()->atendente()->create();

        $this->actingAs($func)->get('/painel/usuarios')->assertForbidden();
        $this->actingAs($func)->get('/painel/usuarios/novo')->assertForbidden();
        $this->actingAs($func)->post('/painel/usuarios', $this->dados())->assertForbidden();
    }

    public function test_senha_nunca_aparece_na_listagem(): void
    {
        $func = User::factory()->atendente()->create();

        $this->actingAs($this->admin)->get('/painel/usuarios')->assertDontSee($func->password);
    }

    // CT-3-7-01: promover funcionário a administrador
    public function test_promove_atendente_a_administrador_com_auditoria(): void
    {
        $func = User::factory()->atendente()->create();

        $this->actingAs($this->admin)
            ->put("/painel/usuarios/{$func->id}", $this->dados(['name' => $func->name, 'email' => $func->email, 'perfil' => 'admin']))
            ->assertRedirect(route('painel.usuarios.index'));

        $this->assertTrue($func->fresh()->isAdmin());
        $detalhes = Auditoria::where('acao', 'editou')->sole()->detalhes;
        $this->assertSame('atendente', $detalhes['antes']['perfil']);
        $this->assertSame('admin', $detalhes['depois']['perfil']);
    }

    // CT-3-7-02
    public function test_admin_nao_altera_o_proprio_perfil(): void
    {
        $this->actingAs($this->admin)
            ->put("/painel/usuarios/{$this->admin->id}", $this->dados(['name' => $this->admin->name, 'email' => $this->admin->email, 'perfil' => 'atendente']))
            ->assertSessionHasErrors(['perfil' => 'Você não pode alterar o seu próprio perfil.']);

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }


    public function test_nao_edita_cliente_pelo_painel(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($this->admin)->get("/painel/usuarios/{$cliente->id}/editar")->assertForbidden();
    }

    // CT-3-8-01: inativar derruba o acesso
    public function test_inativa_atendente_e_bloqueia_login(): void
    {
        $func = User::factory()->atendente()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$func->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');

        $this->assertSame(User::STATUS_INATIVO, $func->fresh()->status);
        $this->assertDatabaseHas('auditorias', ['acao' => 'inativou', 'entidade' => 'users', 'entidade_id' => $func->id]);

        auth()->logout();
        $this->post('/entrar', ['email' => $func->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reativa_atendente(): void
    {
        $func = User::factory()->atendente()->inativo()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$func->id}/reativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');

        $this->assertTrue($func->fresh()->isAtivo());
    }

    // CT-3-8-02
    public function test_admin_nao_inativa_a_propria_conta(): void
    {
        $this->actingAs($this->admin)->patch("/painel/usuarios/{$this->admin->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();

        $this->assertTrue($this->admin->fresh()->isAtivo());
        $this->actingAs($this->admin)->get('/painel/usuarios')->assertDontSee(route('painel.usuarios.inativar', $this->admin));
    }

    public function test_admin_pode_inativar_outro_admin(): void
    {
        $bruno = User::factory()->admin()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$bruno->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');

        $this->assertSame(User::STATUS_INATIVO, $bruno->fresh()->status);
    }

    public function test_sempre_existe_pelo_menos_um_admin_ativo(): void
    {
        // Cenário forçado: o único outro admin ativo seria rebaixado por um admin que já está inativo no banco
        $bruno = User::factory()->admin()->create();
        $this->admin->forceFill(['status' => User::STATUS_INATIVO])->save();
        $this->actingAs($this->admin->fresh()); // simulação direta do controller, ignorando o middleware de sessão

        $resposta = $this->withoutMiddleware(\App\Http\Middleware\EncerraSessaoInativa::class)
            ->patch("/painel/usuarios/{$bruno->id}/inativar", ['motivo' => 'Motivo registrado no teste']);

        $resposta->assertSessionHas('erro', 'O sistema precisa de pelo menos um Administrador ativo.');
        $this->assertTrue($bruno->fresh()->isAtivo());
    }

    public function test_cliente_nao_e_inativado_por_esta_tela(): void
    {
        $cliente = User::factory()->create();

        $this->actingAs($this->admin)->patch("/painel/usuarios/{$cliente->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();
        $this->assertTrue($cliente->fresh()->isAtivo());
    }
}
