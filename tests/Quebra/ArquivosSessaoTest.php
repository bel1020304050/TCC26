<?php

namespace Tests\Quebra;

use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Baterias 7 e 8: arquivos enviados e sessão/login.
 */
class ArquivosSessaoTest extends TestCase
{
    use RefreshDatabase;

    private function dadosProduto(array $troca = []): array
    {
        $p = Produto::factory()->make();

        return array_merge(['nome' => 'Blusa Teste', 'categoria_id' => $p->categoria_id, 'fornecedor_id' => $p->fornecedor_id, 'tamanho' => 'M',
            'cor' => 'Azul', 'preco' => '10', 'estoque' => '1', 'descricao' => 'x', 'status' => 'ativo'], $troca);
    }

    // ---------- Bateria 7: upload ----------

    public function test_upload_malicioso_nao_vira_arquivo_executavel(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $casos = [
            'php_renomeado_jpg' => UploadedFile::fake()->createWithContent('foto.jpg', '<?php system($_GET["c"]); ?>'),
            'svg_com_script' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'png_chamado_php' => UploadedFile::fake()->createWithContent('shell.php', $png),
            'png_com_php_no_fim' => UploadedFile::fake()->createWithContent('foto.png', $png.'<?php system("id"); ?>'),
            'gigante_5mb' => UploadedFile::fake()->image('grande.jpg')->size(5000),
            'html' => UploadedFile::fake()->createWithContent('pagina.html', '<html><script>alert(1)</script></html>'),
        ];

        // Obs.: o arquivo falso do Laravel descobre o tipo pelo nome; o envio real (pela internet) também foi testado
        foreach ($casos as $nome => $arquivo) {
            $r = $this->actingAs($admin)->post('/painel/produtos', $this->dadosProduto(['nome' => "Produto {$nome}", 'imagem' => $arquivo]));
            $produto = Produto::where('nome', "Produto {$nome}")->first();
            $this->assertLessThan(500, $r->getStatusCode(), $nome);
            if ($produto?->imagem) {
                $this->assertMatchesRegularExpression('/\.(jpg|jpeg|png)$/', $produto->imagem, "{$nome} salvo com extensão perigosa");
            }
        }
    }

    // ---------- Bateria 8: login e sessão ----------

    public function test_limite_de_tentativas_de_login(): void
    {
        $ana = User::factory()->create(['email' => 'ana@email.com', 'password' => 'certa12345']);
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/entrar', ['email' => 'ana@email.com', 'password' => "errada{$i}"]);
        }
        $this->post('/entrar', ['email' => 'ANA@email.com', 'password' => 'certa12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/entrar', ['email' => 'ana@email.com', 'password' => 'certa12345']);
        $this->assertAuthenticatedAs($ana);
    }

    // Os limites de tentativas de rotas diferentes somam no mesmo contador?
    public function test_reenviar_email_nao_e_bloqueado_por_outras_rotas(): void
    {
        $c = User::factory()->naoVerificado()->create();
        $vencido = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $c->id, 'hash' => sha1($c->email)]);
        // Cliente clica 3 vezes num link vencido (recebe 403 "link inválido")...
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($c)->get($vencido);
        }
        // ...e depois clica em "Reenviar" pela primeira vez
        $r = $this->actingAs($c)->from('/carrinho')->post('/confirmar-email/reenviar');
        $this->assertNotSame(429, $r->getStatusCode(), 'Primeiro "Reenviar" bloqueado (429) por causa de cliques em outra rota');
    }

    public function test_usuario_inativado_enquanto_logado_cai_fora(): void
    {
        $at = User::factory()->atendente()->create();
        $this->actingAs($at)->get('/painel')->assertOk();
        $at->forceFill(['status' => 'inativo'])->save();
        $this->actingAs($at->fresh())->get('/painel')->assertRedirect('/entrar');
        $this->assertGuest();
    }

    public function test_login_troca_o_id_da_sessao(): void
    {
        User::factory()->create(['email' => 'ana@email.com', 'password' => 'certa12345']);
        $this->get('/entrar');
        $antes = session()->getId();
        $this->post('/entrar', ['email' => 'ana@email.com', 'password' => 'certa12345']);
        $this->assertNotSame($antes, session()->getId());
    }

    public function test_paginas_logadas_nao_ficam_em_cache(): void
    {
        $c = User::factory()->create();
        $cache = $this->actingAs($c)->get('/minha-conta')->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', (string) $cache, 'Depois do logout, o botão Voltar pode mostrar a página da conta');
    }

    // Trocar a senha em "Minha conta" derruba as sessões abertas em outros aparelhos?
    public function test_trocar_senha_derruba_outros_aparelhos(): void
    {
        foreach (['cliente' => [User::factory()->create(['password' => 'antiga1234']), '/minha-conta/senha'],
            'equipe' => [User::factory()->atendente()->create(['password' => 'antiga1234']), '/painel/minha-conta/senha']] as $quem => [$u, $url]) {
            DB::table('sessions')->insert(['id' => "outro-aparelho-{$quem}", 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);
            $this->actingAs($u)->put($url, ['senha_atual' => 'antiga1234', 'password' => 'nova123456', 'password_confirmation' => 'nova123456'])
                ->assertSessionHasNoErrors('senha');
            $this->assertDatabaseMissing('sessions', ['id' => "outro-aparelho-{$quem}"]);
        }
    }

    // Logout por GET (link) não pode funcionar: só POST com token
    public function test_logout_so_por_post(): void
    {
        $c = User::factory()->create();
        $this->actingAs($c)->get('/sair')->assertStatus(405);
    }
}
