<?php

namespace Tests\Feature\Painel;

use App\Models\Carrinho;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permissões dos 3 perfis da equipe: Administrador, Gerente e Atendente/Vendedor.
 */
class PerfisEquipeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $gerente;

    private User $atendente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->gerente = User::factory()->gerente()->create(['name' => 'Bruno Costa']);
        $this->atendente = User::factory()->atendente()->create(['name' => 'Rafael Martins']);
    }

    public function test_rotulos_dos_perfis(): void
    {
        $this->assertSame('Administrador', $this->admin->perfilLabel());
        $this->assertSame('Gerente', $this->gerente->perfilLabel());
        $this->assertSame('Atendente/Vendedor', $this->atendente->perfilLabel());
        $this->assertTrue($this->gerente->isEquipe() && $this->gerente->isGestao());
        $this->assertTrue($this->atendente->isEquipe() && ! $this->atendente->isGestao());
    }

    public function test_gerente_e_atendente_entram_no_painel(): void
    {
        $this->post('/entrar', ['email' => $this->gerente->email, 'password' => 'password'])->assertRedirect(route('painel.inicio'));
        $this->actingAs($this->gerente)->get('/painel')->assertOk()->assertSee('Gerente');
        $this->actingAs($this->atendente)->get('/painel')->assertOk()->assertSee('Atendente/Vendedor');
    }

    // O Gerente vê a lista da equipe (para redefinir senhas), mas só o Administrador cadastra, edita e inativa
    public function test_so_o_admin_gerencia_a_equipe(): void
    {
        $this->actingAs($this->atendente)->get('/painel/usuarios')->assertForbidden();
        $this->actingAs($this->atendente)->get('/painel')->assertDontSee(route('painel.usuarios.index'));

        $this->actingAs($this->gerente)->get('/painel/usuarios')->assertOk()->assertDontSee('Novo usuário');
        $this->actingAs($this->gerente)->get('/painel/usuarios/novo')->assertForbidden();
        $this->actingAs($this->gerente)->get("/painel/usuarios/{$this->atendente->id}/editar")->assertForbidden();
        $this->actingAs($this->gerente)->patch("/painel/usuarios/{$this->atendente->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();

        $this->actingAs($this->admin)->get('/painel')->assertSee(route('painel.usuarios.index'));
    }

    public function test_gerente_cadastra_edita_e_inativa_categoria_e_fornecedor(): void
    {
        $this->actingAs($this->gerente)->post('/painel/categorias', ['nome' => 'Shorts', 'status' => 'ativo'])->assertSessionHasNoErrors();
        $categoria = Categoria::where('nome', 'Shorts')->sole();
        $this->actingAs($this->gerente)->patch("/painel/categorias/{$categoria->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');
        $this->assertSame('inativo', $categoria->fresh()->status);

        $fornecedor = Fornecedor::factory()->create();
        $this->actingAs($this->gerente)->get("/painel/fornecedores/{$fornecedor->id}/editar")->assertOk();
        $this->actingAs($this->gerente)->patch("/painel/fornecedores/{$fornecedor->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');
    }

    public function test_atendente_so_consulta_categoria_fornecedor_e_promocao(): void
    {
        $categoria = Categoria::factory()->create();
        $promocao = Promocao::factory()->create();

        $this->actingAs($this->atendente)->get('/painel/categorias')->assertOk();
        $this->actingAs($this->atendente)->get('/painel/categorias/novo')->assertForbidden();
        $this->actingAs($this->atendente)->patch("/painel/categorias/{$categoria->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();
        $this->actingAs($this->atendente)->get('/painel/fornecedores/novo')->assertForbidden();
        $this->actingAs($this->atendente)->get('/painel/promocoes')->assertOk();
        $this->actingAs($this->atendente)->get("/painel/promocoes/{$promocao->id}/editar")->assertForbidden();
    }

    public function test_gerente_inativa_produto_e_gerencia_promocao(): void
    {
        $produto = Produto::factory()->create();
        $this->actingAs($this->gerente)->patch("/painel/produtos/{$produto->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');
        $this->assertSame('inativo', $produto->fresh()->status);

        $promocao = Promocao::factory()->create();
        $this->actingAs($this->gerente)->get("/painel/promocoes/{$promocao->id}/editar")->assertOk();
        $this->actingAs($this->gerente)->patch("/painel/promocoes/{$promocao->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertSessionHas('sucesso');
    }

    public function test_atendente_cadastra_e_edita_produto_mas_nao_inativa(): void
    {
        $produto = Produto::factory()->create();

        $this->actingAs($this->atendente)->get("/painel/produtos/{$produto->id}/editar")->assertOk();
        $this->actingAs($this->atendente)->patch("/painel/produtos/{$produto->id}/inativar", ['motivo' => 'Motivo registrado no teste'])->assertForbidden();
    }

    public function test_gerente_cancela_pedido_pago_com_estorno_e_confirma_boleto(): void
    {
        $cliente = User::factory()->create();
        $produto = Produto::factory()->create(['estoque' => 5, 'preco' => 50]);

        // Pedido pago com cartão
        Carrinho::doCliente($cliente)->itens()->create(['produto_id' => $produto->id, 'quantidade' => 1]);
        $this->actingAs($cliente)->post('/pedidos');
        $pago = Pedido::latest('id')->first();
        $this->actingAs($cliente)->post("/meus-pedidos/{$pago->id}/pagamento", [
            'forma' => 'cartao', 'cartao_numero' => '4111111111111111', 'cartao_nome' => 'ANA',
            'cartao_validade' => '12/'.now()->addYears(2)->format('y'), 'cartao_cvv' => '123',
        ]);
        $this->assertSame(4, $produto->fresh()->estoque);

        $this->actingAs($this->gerente)->from("/painel/pedidos/{$pago->id}")
            ->patch("/painel/pedidos/{$pago->id}/cancelar", ['motivo' => 'Cliente desistiu'])->assertSessionHas('sucesso');
        $this->assertSame(Pedido::CANCELADO, $pago->fresh()->status);
        $this->assertSame(5, $produto->fresh()->estoque);

        // Pedido com boleto pendente (carrinho novo, já que o anterior foi finalizado)
        Carrinho::doCliente($cliente)->itens()->create(['produto_id' => $produto->id, 'quantidade' => 1]);
        $this->actingAs($cliente)->post('/pedidos');
        $boletoPedido = Pedido::latest('id')->first();
        $this->actingAs($cliente)->post("/meus-pedidos/{$boletoPedido->id}/pagamento", ['forma' => 'boleto']);
        $boleto = Pagamento::where('pedido_id', $boletoPedido->id)->sole();

        $this->actingAs($this->atendente)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto")->assertForbidden();
        $this->actingAs($this->gerente)->post("/painel/pagamentos/{$boleto->id}/confirmar-boleto")->assertSessionHas('sucesso');
        $this->assertSame(Pedido::PAGO, $boletoPedido->fresh()->status);
    }

    public function test_admin_cadastra_gerente_e_perfil_antigo_nao_e_aceito(): void
    {
        $dados = [
            'name' => 'Carla Dias', 'email' => 'carla@marymodas.com.br', 'telefone' => '',
            'perfil' => 'gerente', 'status' => 'ativo', 'password' => 'senha1234', 'password_confirmation' => 'senha1234',
        ];

        $this->actingAs($this->admin)->post('/painel/usuarios', $dados)->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'carla@marymodas.com.br')->sole()->isGerente());

        $this->actingAs($this->admin)->post('/painel/usuarios', ['email' => 'outro@marymodas.com.br', 'perfil' => 'funcionario'] + $dados)
            ->assertSessionHasErrors('perfil');
    }

    public function test_formulario_mostra_os_tres_perfis_e_nao_tem_cargo(): void
    {
        $this->actingAs($this->admin)->get('/painel/usuarios/novo')
            ->assertSee('<option value="atendente"', false)
            ->assertSee('<option value="gerente"', false)
            ->assertSee('<option value="admin"', false)
            ->assertDontSee('Cargo');
    }
}
