<?php

namespace Tests\Quebra;

use App\Models\Carrinho;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Garantias das correções feitas depois dos testes de quebra.
 */
class CorrecoesTest extends TestCase
{
    use RefreshDatabase;

    // Campo em lista vira vazio: a validação responde normalmente
    public function test_campo_em_lista_vira_mensagem_de_validacao(): void
    {
        $this->post('/criar-conta', ['email' => ['a@b.com'], 'name' => ['x']])
            ->assertSessionHasErrors(['email' => 'O campo e-mail é obrigatório.']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/painel/produtos?busca[]=x&status[]=ativo')->assertOk();
    }

    // Célula de CSV que começa com fórmula recebe um apóstrofo; número negativo continua número
    public function test_csv_escapa_formulas(): void
    {
        $admin = User::factory()->admin()->create();
        Produto::factory()->create(['nome' => '=HYPERLINK("http://x.com")']);
        $csv = $this->actingAs($admin)->get('/painel/relatorios/estoque?exportar=csv')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    // Trocar a senha mantém o aparelho atual conectado e desconecta os outros
    public function test_trocar_senha_mantem_o_aparelho_atual(): void
    {
        $c = User::factory()->create(['password' => 'antiga1234']);
        DB::table('sessions')->insert(['id' => 'celular', 'user_id' => $c->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($c)->put('/minha-conta/senha', ['senha_atual' => 'antiga1234', 'password' => 'nova123456', 'password_confirmation' => 'nova123456'])
            ->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('sessions', ['id' => 'celular']);
        $this->assertAuthenticatedAs($c);
        $this->actingAs($c)->get('/minha-conta')->assertOk();
    }

    public function test_paginas_logadas_tem_no_store_e_publicas_nao(): void
    {
        $this->assertStringContainsString('no-store', $this->actingAs(User::factory()->create())->get('/minha-conta')->headers->get('Cache-Control'));
        auth()->logout();
        $this->assertStringNotContainsString('no-store', (string) $this->get('/entrar')->headers->get('Cache-Control'));
    }

    // Cada rota tem o seu limite: o "Reenviar" continua limitado a 3 por minuto
    public function test_reenviar_continua_com_limite_proprio(): void
    {
        Notification::fake();
        $c = User::factory()->naoVerificado()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($c)->from('/carrinho')->post('/confirmar-email/reenviar')->assertRedirect('/carrinho');
        }
        $this->actingAs($c)->from('/carrinho')->post('/confirmar-email/reenviar')->assertStatus(429);
    }

    // Cliente sem carrinho: adicionar cria um só carrinho e respeita o estoque
    public function test_adicionar_cria_um_carrinho_so(): void
    {
        $c = User::factory()->create();
        $p = Produto::factory()->create(['estoque' => 2]);
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($c)->post('/carrinho/itens', ['produto_id' => $p->id]);
        }
        $this->assertSame(1, Carrinho::where('user_id', $c->id)->count());
        $this->assertSame(2, (int) Carrinho::doCliente($c)->itens()->sum('quantidade'));
    }
}
