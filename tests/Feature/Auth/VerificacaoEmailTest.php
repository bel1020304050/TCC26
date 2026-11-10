<?php

namespace Tests\Feature\Auth;

use App\Models\Carrinho;
use App\Models\Produto;
use App\Models\User;
use App\Notifications\VerificarEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Confirmação do e-mail do cliente: sem confirmar, monta o carrinho mas não finaliza a compra.
 */
class VerificacaoEmailTest extends TestCase
{
    use RefreshDatabase;

    private function linkDe(User $cliente, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $cliente->id, 'hash' => sha1($email ?? $cliente->email),
        ]);
    }

    public function test_criar_conta_envia_o_email_de_confirmacao(): void
    {
        Notification::fake();

        $this->post('/criar-conta', [
            'name' => 'Bia Lima', 'email' => 'Bia@Email.com', 'cpf' => '123.456.789-09', 'telefone' => '',
            'password' => 'segredo123', 'password_confirmation' => 'segredo123',
            'cep' => '87501-000', 'rua' => 'Avenida Brasil', 'numero' => '10', 'complemento' => '',
            'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
        ])->assertSessionHas('sucesso');

        $bia = User::where('email', 'bia@email.com')->sole();
        $this->assertNull($bia->email_verified_at);
        Notification::assertSentTo($bia, VerificarEmail::class);
    }

    // Regra: sem confirmar, o cliente monta o carrinho, mas não finaliza a compra
    public function test_sem_confirmar_monta_o_carrinho_mas_nao_finaliza(): void
    {
        $cliente = User::factory()->naoVerificado()->create();
        $produto = Produto::factory()->create(['estoque' => 5]);

        $this->actingAs($cliente)->post('/carrinho/itens', ['produto_id' => $produto->id])->assertSessionHasNoErrors();
        $this->assertSame(1, Carrinho::doCliente($cliente)->itens()->count());

        $this->actingAs($cliente)->get('/carrinho')->assertSee('Confirme seu e-mail para finalizar a compra');
        $this->actingAs($cliente)->get('/pedido/confirmar')->assertRedirect(route('verification.notice'));
        $this->actingAs($cliente)->post('/pedidos')->assertRedirect(route('verification.notice'));

        $this->actingAs($cliente)->get('/confirmar-email')->assertOk()->assertSee($cliente->email)->assertSee('Reenviar');
    }

    public function test_link_do_email_confirma_e_libera_a_compra(): void
    {
        $cliente = User::factory()->naoVerificado()->create();

        $this->actingAs($cliente)->get($this->linkDe($cliente))
            ->assertRedirect(route('loja.carrinho'))->assertSessionHas('sucesso');

        $this->assertNotNull($cliente->fresh()->email_verified_at);
        $this->assertDatabaseHas('auditorias', ['acao' => 'confirmou_email', 'entidade_id' => $cliente->id]);
        $this->actingAs($cliente->fresh())->get('/pedido/confirmar')->assertRedirect('/carrinho'); // carrinho vazio, mas já passou da trava
    }

    // Segurança: link adulterado, vencido ou de outra conta não confirma nada
    public function test_link_invalido_nao_confirma(): void
    {
        $cliente = User::factory()->naoVerificado()->create();
        $outro = User::factory()->naoVerificado()->create();

        $this->actingAs($cliente)->get($this->linkDe($cliente).'x')->assertForbidden();
        $this->actingAs($cliente)->get($this->linkDe($outro))->assertForbidden();

        $vencido = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $cliente->id, 'hash' => sha1($cliente->email)]);
        $this->actingAs($cliente)->get($vencido)->assertForbidden();

        $this->assertNull($cliente->fresh()->email_verified_at);
    }

    public function test_reenviar_manda_outro_link(): void
    {
        Notification::fake();
        $cliente = User::factory()->naoVerificado()->create();

        $this->actingAs($cliente)->from('/carrinho')->post('/confirmar-email/reenviar')->assertSessionHas('sucesso');
        Notification::assertSentTo($cliente, VerificarEmail::class);
    }

    public function test_trocar_o_email_pede_nova_confirmacao(): void
    {
        Notification::fake();
        $cliente = User::factory()->create(['cpf' => '12345678909']);

        $this->actingAs($cliente)->put('/minha-conta', [
            'name' => $cliente->name, 'email' => 'novo@email.com', 'cpf' => '12345678909', 'telefone' => '',
            'cep' => $cliente->cep, 'rua' => $cliente->rua, 'numero' => $cliente->numero, 'complemento' => '',
            'bairro' => $cliente->bairro, 'cidade' => $cliente->cidade, 'uf' => $cliente->uf,
        ])->assertSessionHasNoErrors();

        $this->assertNull($cliente->fresh()->email_verified_at);
        Notification::assertSentTo($cliente->fresh(), VerificarEmail::class);
        $this->actingAs($cliente->fresh())->get('/minha-conta')->assertSee('Não confirmado');
    }

    // Se o servidor de e-mail estiver fora (ou com usuário/senha errados), a tela não quebra
    public function test_falha_no_envio_do_email_nao_quebra_o_cadastro(): void
    {
        // Simula um servidor SMTP que não responde (porta fechada)
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->post('/criar-conta', [
            'name' => 'Bia Lima', 'email' => 'bia@email.com', 'cpf' => '123.456.789-09', 'telefone' => '',
            'password' => 'segredo123', 'password_confirmation' => 'segredo123',
            'cep' => '87501-000', 'rua' => 'Avenida Brasil', 'numero' => '10', 'complemento' => '',
            'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
        ])->assertRedirect(route('loja.inicio'))->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'Não conseguimos enviar o e-mail'));

        $bia = User::where('email', 'bia@email.com')->sole();
        $this->assertAuthenticatedAs($bia); // a conta foi criada e a pessoa já está logada

        $this->actingAs($bia)->from('/carrinho')->post('/confirmar-email/reenviar')->assertSessionHas('erro');
        auth()->logout();
        $this->post('/esqueci-senha', ['email' => 'bia@email.com'])->assertSessionHas('erro');
    }

    // A equipe usa o e-mail da loja e não passa por confirmação
    public function test_equipe_nao_precisa_confirmar(): void
    {
        $atendente = User::factory()->atendente()->naoVerificado()->create();

        $this->assertTrue($atendente->hasVerifiedEmail());
        $this->actingAs($atendente)->get('/painel')->assertOk();
        $this->actingAs($atendente)->get('/confirmar-email')->assertForbidden();
    }

    // Segurança: o domínio da loja é reservado à equipe
    public function test_cliente_nao_usa_email_da_loja(): void
    {
        $this->post('/criar-conta', ['email' => 'ana@marymodas.com.br'])
            ->assertSessionHasErrors(['email' => 'Esse domínio é reservado à equipe da loja. Use o seu e-mail pessoal.']);
    }
}
