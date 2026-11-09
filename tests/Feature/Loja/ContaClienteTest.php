<?php

namespace Tests\Feature\Loja;

use App\Models\Carrinho;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "Minha conta" do cliente: consultar, editar e encerrar.
 */
class ContaClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ana = User::factory()->create([
            'name' => 'Ana Souza', 'email' => 'ana@email.com', 'telefone' => '(44) 99999-0000', 'password' => 'cliente123',
            'cep' => '87501-000', 'rua' => 'Avenida Brasil', 'numero' => '120', 'complemento' => 'Apto 32',
            'bairro' => 'Centro', 'cidade' => 'Umuarama', 'uf' => 'PR',
        ]);
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'name' => 'Ana Souza', 'email' => 'ana@email.com', 'telefone' => '(44) 99999-0000',
            'cep' => '87502-000', 'rua' => 'Rua Piauí', 'numero' => '45', 'complemento' => '',
            'bairro' => 'Zona I', 'cidade' => 'Umuarama', 'uf' => 'PR',
        ], $troca);
    }

    private function pedido(string $status): Pedido
    {
        $pedido = new Pedido;
        $pedido->user_id = $this->ana->id;
        $pedido->status = $status;
        $pedido->subtotal = $pedido->total = 100;
        foreach (['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $this->ana->{$campo};
        }
        $pedido->save();

        return $pedido;
    }

    // CT-3-10-01
    public function test_consulta_os_proprios_dados_sem_mostrar_a_senha(): void
    {
        $this->actingAs($this->ana)->get('/minha-conta')
            ->assertOk()
            ->assertSee('Ana Souza')->assertSee('ana@email.com')->assertSee('(44) 99999-0000')
            ->assertSee('Avenida Brasil, 120 – Apto 32')->assertSee('Centro · Umuarama/PR')->assertSee('87501-000')
            ->assertSee('Cliente desde '.$this->ana->created_at->format('d/m/Y'))
            ->assertSee('Encerrar minha conta')
            ->assertDontSee($this->ana->password);
    }

    // CT-3-10-02: não existe rota com id; os dados vêm sempre da sessão
    public function test_nao_consulta_dados_de_outro_cliente(): void
    {
        $bia = User::factory()->create(['name' => 'Bia Lima', 'email' => 'bia@email.com']);

        $this->actingAs($this->ana)->get("/minha-conta/{$bia->id}")->assertNotFound();
        $this->actingAs($this->ana)->get("/minha-conta?id={$bia->id}")->assertDontSee('Bia Lima')->assertDontSee('bia@email.com');
    }

    public function test_equipe_e_visitante_nao_usam_a_conta_do_cliente(): void
    {
        $this->get('/minha-conta')->assertRedirect('/entrar');
        $this->actingAs(User::factory()->admin()->create())->get('/minha-conta')->assertForbidden();
    }

    // CT-3-11-01
    public function test_atualiza_endereco_e_proximos_pedidos_usam_o_novo(): void
    {
        $antigo = $this->pedido(Pedido::ENTREGUE);

        $this->actingAs($this->ana)->put('/minha-conta', $this->dados(['cep' => '87502000']))
            ->assertRedirect('/minha-conta')
            ->assertSessionHas('sucesso');

        $this->ana->refresh();
        $this->assertSame('87502-000', $this->ana->cep);
        $this->assertSame('Rua Piauí', $this->ana->rua);
        $this->assertSame('Avenida Brasil', $antigo->fresh()->rua); // pedido antigo intacto
        $this->assertDatabaseHas('auditorias', ['acao' => 'editou', 'entidade' => 'users', 'entidade_id' => $this->ana->id]);

        // Próximo pedido sai com o endereço novo
        $produto = Produto::factory()->create(['estoque' => 5]);
        Carrinho::doCliente($this->ana)->itens()->create(['produto_id' => $produto->id, 'quantidade' => 1]);
        $this->actingAs($this->ana)->post('/pedidos');
        $this->assertSame('Rua Piauí', Pedido::latest('id')->first()->rua);
    }

    public function test_mesmas_regras_do_cadastro_e_email_unico(): void
    {
        User::factory()->create(['email' => 'bia@email.com']);

        $this->actingAs($this->ana)->put('/minha-conta', $this->dados(['email' => 'BIA@email.com', 'cep' => '123', 'uf' => 'XX', 'rua' => '']))
            ->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.', 'cep', 'uf', 'rua']);

        // Manter o próprio e-mail é permitido
        $this->actingAs($this->ana)->put('/minha-conta', $this->dados())->assertSessionHasNoErrors();
    }

    public function test_nao_altera_perfil_status_nem_outra_conta_pelo_formulario(): void
    {
        $bia = User::factory()->create(['name' => 'Bia Lima']);

        $this->actingAs($this->ana)->put('/minha-conta', $this->dados(['perfil' => 'admin', 'status' => 'inativo', 'id' => $bia->id, 'name' => 'Ana S.']));

        $this->assertTrue($this->ana->fresh()->isCliente());
        $this->assertTrue($this->ana->fresh()->isAtivo());
        $this->assertSame('Ana S.', $this->ana->fresh()->name);
        $this->assertSame('Bia Lima', $bia->fresh()->name);
    }

    public function test_altera_senha_com_a_senha_atual(): void
    {
        $this->actingAs($this->ana)->put('/minha-conta/senha', ['senha_atual' => 'cliente123', 'password' => 'novaSenha1', 'password_confirmation' => 'novaSenha1'])
            ->assertRedirect('/minha-conta')->assertSessionHas('sucesso');

        $this->assertTrue(Hash::check('novaSenha1', $this->ana->fresh()->password));
    }

    // CT-3-11-02
    public function test_senha_atual_errada_impede_a_troca(): void
    {
        $this->actingAs($this->ana)->put('/minha-conta/senha', ['senha_atual' => 'errada000', 'password' => 'novaSenha1', 'password_confirmation' => 'novaSenha1'])
            ->assertSessionHasErrorsIn('senha', ['senha_atual' => 'A senha atual está incorreta.']);
        $this->actingAs($this->ana)->put('/minha-conta/senha', ['senha_atual' => 'cliente123', 'password' => '123', 'password_confirmation' => '123'])
            ->assertSessionHasErrorsIn('senha', 'password');

        $this->assertTrue(Hash::check('cliente123', $this->ana->fresh()->password));
    }

    // CT-3-12-01
    public function test_encerra_conta_anonimiza_e_bloqueia_login(): void
    {
        $antigo = $this->pedido(Pedido::ENTREGUE);
        $this->pedido(Pedido::CANCELADO);
        DB::table('sessions')->insert(['id' => 'outra-sessao', 'user_id' => $this->ana->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->ana)->delete('/minha-conta', ['senha_encerrar' => 'cliente123'])
            ->assertRedirect('/')
            ->assertSessionHas('sucesso');

        $this->assertGuest();
        $ana = $this->ana->fresh();
        $this->assertSame(User::STATUS_ENCERRADO, $ana->status);
        $this->assertNotNull($ana->encerrada_em);
        $this->assertSame('Cliente removido', $ana->name);
        $this->assertNotSame('ana@email.com', $ana->email);
        $this->assertNull($ana->telefone);
        $this->assertNull($ana->rua);
        $this->assertNull($ana->cep);
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->ana->id]);
        $this->assertDatabaseHas('auditorias', ['acao' => 'encerrou_conta', 'entidade_id' => $this->ana->id]);

        // Pedidos antigos continuam, com o endereço copiado
        $this->assertSame('Avenida Brasil', $antigo->fresh()->rua);
        $this->assertSame(2, Pedido::where('user_id', $this->ana->id)->count());

        // Login com o e-mail antigo é negado
        $this->post('/entrar', ['email' => 'ana@email.com', 'password' => 'cliente123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_email_fica_livre_para_conta_nova(): void
    {
        $this->actingAs($this->ana)->delete('/minha-conta', ['senha_encerrar' => 'cliente123']);

        $this->post('/criar-conta', $this->dados(['password' => 'outraSenha1', 'password_confirmation' => 'outraSenha1']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, User::clientes()->count());
        $this->assertTrue(User::where('email', 'ana@email.com')->sole()->isAtivo());
    }

    // CT-3-12-02
    public function test_nao_encerra_com_pedido_em_andamento(): void
    {
        $pedido = $this->pedido(Pedido::AGUARDANDO_PAGAMENTO);

        $this->actingAs($this->ana)->from('/minha-conta')->delete('/minha-conta', ['senha_encerrar' => 'cliente123'])
            ->assertRedirect('/minha-conta')
            ->assertSessionHas('erro', "Você tem pedido(s) em andamento: {$pedido->numero()} (Aguardando Pagamento). Aguarde a entrega ou cancele o pedido antes de encerrar a conta.");

        $this->assertAuthenticatedAs($this->ana);
        $this->assertTrue($this->ana->fresh()->isAtivo());
        $this->assertSame('Ana Souza', $this->ana->fresh()->name);
    }

    public function test_senha_errada_nao_encerra(): void
    {
        $this->actingAs($this->ana)->from('/minha-conta')->delete('/minha-conta', ['senha_encerrar' => 'errada000'])
            ->assertSessionHasErrorsIn('encerrar', ['senha_encerrar' => 'A senha atual está incorreta.']);

        $this->assertTrue($this->ana->fresh()->isAtivo());
    }

    // LGPD: depois de encerrada, os dados antigos não aparecem em nenhuma tela da equipe
    public function test_painel_mostra_cliente_removido_no_pedido(): void
    {
        $pedido = $this->pedido(Pedido::ENTREGUE);
        $this->actingAs($this->ana)->delete('/minha-conta', ['senha_encerrar' => 'cliente123']);

        $this->actingAs(User::factory()->admin()->create())->get('/painel/pedidos')
            ->assertSee('Cliente removido')->assertSee('Conta encerrada em')
            ->assertDontSee('Ana Souza')->assertDontSee('ana@email.com')->assertDontSee('conta-encerrada.invalid');
        $this->actingAs(User::factory()->admin()->create())->get("/painel/pedidos/{$pedido->id}")
            ->assertSee('Cliente removido')->assertDontSee('(44) 99999-0000');
    }
}
