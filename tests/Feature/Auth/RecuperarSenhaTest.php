<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\RecuperarSenhaController;
use App\Models\User;
use App\Notifications\RecuperarSenha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * "Esqueci minha senha" do cliente.
 */
class RecuperarSenhaTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ana = User::factory()->create(['email' => 'ana@email.com', 'password' => 'antiga1234']);
    }

    public function test_login_tem_link_e_a_tela_abre(): void
    {
        $this->get('/entrar')->assertSee(route('password.request'));
        $this->get('/esqueci-senha')->assertOk()->assertSee('Esqueci minha senha');
    }

    public function test_envia_o_link_para_cliente_ativo(): void
    {
        Notification::fake();

        $this->post('/esqueci-senha', ['email' => 'ANA@email.com '])
            ->assertSessionHas('sucesso', RecuperarSenhaController::MENSAGEM_ENVIO);

        Notification::assertSentTo($this->ana, RecuperarSenha::class);
    }

    // Segurança: a mensagem é a mesma se o e-mail não existir; equipe e conta encerrada não recebem link
    public function test_mensagem_neutra_e_sem_envio_para_quem_nao_pode(): void
    {
        Notification::fake();
        $atendente = User::factory()->atendente()->create();
        $encerrada = User::factory()->encerrado()->create();

        foreach (['ninguem@email.com', $atendente->email, $encerrada->email] as $email) {
            $this->post('/esqueci-senha', ['email' => $email])
                ->assertSessionHas('sucesso', RecuperarSenhaController::MENSAGEM_ENVIO);
        }

        Notification::assertNothingSent();
    }

    public function test_link_valido_troca_a_senha_e_encerra_sessoes(): void
    {
        $token = Password::createToken($this->ana);
        DB::table('sessions')->insert(['id' => 'sessao-antiga', 'user_id' => $this->ana->id, 'payload' => '', 'last_activity' => time()]);

        $this->get("/redefinir-senha/{$token}?email=ana@email.com")->assertOk()->assertSee('Criar nova senha');

        $this->post('/redefinir-senha', [
            'token' => $token, 'email' => 'ana@email.com', 'password' => 'novaSenha1', 'password_confirmation' => 'novaSenha1',
        ])->assertRedirect(route('login'))->assertSessionHas('sucesso');

        $this->assertTrue(Hash::check('novaSenha1', $this->ana->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->ana->id]);
        $this->assertDatabaseHas('auditorias', ['acao' => 'recuperou_senha', 'entidade_id' => $this->ana->id]);

        // Entra com a senha nova
        $this->post('/entrar', ['email' => 'ana@email.com', 'password' => 'novaSenha1'])->assertRedirect(route('loja.inicio'));
    }

    // Segurança: o link só pode ser usado uma vez e vence em 60 minutos
    public function test_link_usado_ou_vencido_nao_funciona(): void
    {
        $token = Password::createToken($this->ana);
        $dados = ['token' => $token, 'email' => 'ana@email.com', 'password' => 'novaSenha1', 'password_confirmation' => 'novaSenha1'];

        $this->post('/redefinir-senha', $dados)->assertSessionHasNoErrors();
        $this->post('/redefinir-senha', ['password' => 'outra12345', 'password_confirmation' => 'outra12345'] + $dados)
            ->assertSessionHasErrors(['email' => 'Este link é inválido ou já expirou. Peça um novo em "Esqueci minha senha".']);

        $token2 = Password::createToken($this->ana->fresh());
        $this->travel(61)->minutes();
        $this->post('/redefinir-senha', ['token' => $token2] + $dados)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('novaSenha1', $this->ana->fresh()->password));
    }

    public function test_token_de_outra_conta_nao_serve(): void
    {
        $bia = User::factory()->create(['email' => 'bia@email.com']);
        $token = Password::createToken($bia);

        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'ana@email.com', 'password' => 'novaSenha1', 'password_confirmation' => 'novaSenha1'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('antiga1234', $this->ana->fresh()->password));
    }
}
