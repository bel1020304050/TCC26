<?php

namespace Tests\Feature\Painel;

use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\User;
use App\Support\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Testes do CRUD de Produtos — stories 3.1, 3.3 e 3.4.
 */
class ProdutoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $funcionario;
    private Categoria $vestidos;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
        $this->funcionario = User::factory()->funcionario()->create();
        $this->vestidos = Categoria::factory()->create(['nome' => 'Vestidos']);
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'nome' => 'Vestido Floral',
            'descricao' => 'Vestido midi floral em viscose',
            'preco' => '129,90',
            'estoque' => 15,
            'categoria_id' => $this->vestidos->id,
            'fornecedor_id' => null,
            'tamanho' => 'G',
            'cor' => 'Preto',
            'status' => 'ativo',
        ], $troca);
    }

    // CT-3-1-01 (com imagem JPG)
    public function test_funcionario_cadastra_produto_com_imagem(): void
    {
        $imagem = UploadedFile::fake()->image('vestido.jpg', 600, 600)->size(300);

        $this->actingAs($this->funcionario)
            ->post('/painel/produtos', $this->dados(['imagem' => $imagem]))
            ->assertRedirect(route('painel.produtos.index'))
            ->assertSessionHas('sucesso');

        $produto = Produto::sole();
        $this->assertSame('129.90', $produto->preco); // "129,90" convertido
        $this->assertSame(15, $produto->estoque);
        $this->assertTrue($produto->isAtivo());
        Storage::disk('public')->assertExists($produto->imagem);
        $this->assertDatabaseHas('auditorias', ['acao' => 'criou', 'entidade' => 'produtos', 'user_id' => $this->funcionario->id]);
    }

    // CT-3-1-02
    public function test_preco_zero_e_recusado(): void
    {
        $this->actingAs($this->admin)->post('/painel/produtos', $this->dados(['preco' => '0,00']))
            ->assertSessionHasErrors(['preco' => 'O preço deve ser maior que zero.']);

        $this->assertSame(0, Produto::count());
    }

    public function test_estoque_negativo_e_campos_obrigatorios(): void
    {
        $this->actingAs($this->admin)
            ->post('/painel/produtos', $this->dados(['estoque' => -1, 'nome' => '', 'tamanho' => '', 'cor' => '', 'categoria_id' => '']))
            ->assertSessionHasErrors(['estoque', 'nome', 'tamanho', 'cor', 'categoria_id']);
    }

    public function test_descricao_fornecedor_e_imagem_sao_opcionais(): void
    {
        $this->actingAs($this->admin)->post('/painel/produtos', $this->dados(['descricao' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Produto::sole()->imagem);
    }

    public function test_mesma_variacao_nao_pode_repetir(): void
    {
        Produto::factory()->create(['nome' => 'Vestido Floral', 'categoria_id' => $this->vestidos->id, 'tamanho' => 'G', 'cor' => 'Preto']);

        $this->actingAs($this->admin)->post('/painel/produtos', $this->dados(['nome' => 'vestido floral']))
            ->assertSessionHasErrors(['nome' => 'Já existe um produto com este nome, categoria, tamanho e cor.']);

        // Outro tamanho é outra variação: pode
        $this->actingAs($this->admin)->post('/painel/produtos', $this->dados(['tamanho' => 'M']))
            ->assertSessionHasNoErrors();
    }

    public function test_so_aceita_categoria_e_fornecedor_ativos(): void
    {
        $inativa = Categoria::factory()->inativa()->create();
        $fornecedorInativo = Fornecedor::factory()->inativo()->create();

        $this->actingAs($this->admin)
            ->post('/painel/produtos', $this->dados(['categoria_id' => $inativa->id, 'fornecedor_id' => $fornecedorInativo->id]))
            ->assertSessionHasErrors(['categoria_id', 'fornecedor_id']);
    }

    public function test_tela_de_cadastro_nao_lista_categoria_inativa(): void
    {
        Categoria::factory()->inativa()->create(['nome' => 'Jaquetas']);

        $this->actingAs($this->admin)->get('/painel/produtos/novo')
            ->assertOk()
            ->assertSee('Vestidos')
            ->assertDontSee('Jaquetas');
    }

    public function test_imagem_invalida_ou_grande_e_recusada(): void
    {
        $this->actingAs($this->admin)
            ->post('/painel/produtos', $this->dados(['imagem' => UploadedFile::fake()->create('arquivo.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('imagem');

        $this->actingAs($this->admin)
            ->post('/painel/produtos', $this->dados(['imagem' => UploadedFile::fake()->image('grande.jpg')->size(3000)]))
            ->assertSessionHasErrors(['imagem' => 'A imagem deve ter no máximo 2 MB.']);
    }

    // CT-3-3-01: repor estoque
    public function test_funcionario_repoe_estoque(): void
    {
        $produto = Produto::factory()->create(['categoria_id' => $this->vestidos->id, 'estoque' => 8]);

        $this->actingAs($this->funcionario)
            ->put("/painel/produtos/{$produto->id}", $this->dados([
                'nome' => $produto->nome, 'tamanho' => $produto->tamanho, 'cor' => $produto->cor,
                'preco' => (string) $produto->preco, 'estoque' => 20,
            ]))
            ->assertRedirect(route('painel.produtos.index'));

        $produto->refresh();
        $this->assertSame(20, $produto->estoque);
        $this->assertSame(8, \App\Models\Auditoria::where('acao', 'editou')->sole()->detalhes['antes']['estoque']);
    }

    // CT-3-3-02
    public function test_editar_com_estoque_negativo_e_recusado(): void
    {
        $produto = Produto::factory()->create(['categoria_id' => $this->vestidos->id, 'estoque' => 8]);

        $this->actingAs($this->admin)
            ->put("/painel/produtos/{$produto->id}", $this->dados(['estoque' => -1]))
            ->assertSessionHasErrors(['estoque' => 'O estoque não pode ser negativo.']);

        $this->assertSame(8, $produto->fresh()->estoque);
    }

    public function test_editar_nao_muda_status_e_troca_imagem(): void
    {
        $antiga = UploadedFile::fake()->image('antiga.png')->store('produtos', 'public');
        $produto = Produto::factory()->create(['categoria_id' => $this->vestidos->id, 'imagem' => $antiga]);

        $this->actingAs($this->admin)
            ->put("/painel/produtos/{$produto->id}", $this->dados([
                'status' => 'inativo', 'imagem' => UploadedFile::fake()->image('nova.jpg'),
            ]))
            ->assertSessionHasNoErrors();

        $produto->refresh();
        $this->assertTrue($produto->isAtivo());
        Storage::disk('public')->assertMissing($antiga);   // arquivo antigo apagado
        Storage::disk('public')->assertExists($produto->imagem);
    }

    public function test_remover_imagem(): void
    {
        $imagem = UploadedFile::fake()->image('foto.png')->store('produtos', 'public');
        $produto = Produto::factory()->create(['categoria_id' => $this->vestidos->id, 'imagem' => $imagem]);

        $this->actingAs($this->admin)
            ->put("/painel/produtos/{$produto->id}", $this->dados(['remover_imagem' => '1']));

        $this->assertNull($produto->fresh()->imagem);
        Storage::disk('public')->assertMissing($imagem);
    }

    public function test_edicao_aceita_categoria_atual_mesmo_inativa(): void
    {
        $produto = Produto::factory()->inativo()->create(['categoria_id' => $this->vestidos->id]);
        $this->vestidos->forceFill(['status' => Status::INATIVO])->save();

        $this->actingAs($this->admin)
            ->put("/painel/produtos/{$produto->id}", $this->dados(['nome' => $produto->nome, 'estoque' => 3]))
            ->assertSessionHasNoErrors();
    }

    // CT-3-4-01
    public function test_admin_inativa_e_reativa(): void
    {
        $produto = Produto::factory()->create(['categoria_id' => $this->vestidos->id]);

        $this->actingAs($this->admin)->patch("/painel/produtos/{$produto->id}/inativar")->assertSessionHas('sucesso');
        $this->assertSame(Status::INATIVO, $produto->fresh()->status);

        $this->actingAs($this->admin)->patch("/painel/produtos/{$produto->id}/reativar")->assertSessionHas('sucesso');
        $this->assertTrue($produto->fresh()->isAtivo());

        $this->assertDatabaseHas('auditorias', ['acao' => 'inativou', 'entidade' => 'produtos', 'entidade_id' => $produto->id]);
    }

    // CT-3-4-02: funcionário não inativa, nem pela URL, e não vê o botão
    public function test_funcionario_nao_inativa_produto(): void
    {
        $produto = Produto::factory()->create(['categoria_id' => $this->vestidos->id]);

        $this->actingAs($this->funcionario)->patch("/painel/produtos/{$produto->id}/inativar")->assertForbidden();
        $this->assertTrue($produto->fresh()->isAtivo());

        $this->actingAs($this->funcionario)->get('/painel/produtos')
            ->assertOk()
            ->assertSee('Novo produto')
            ->assertDontSee(route('painel.produtos.inativar', $produto));
    }

    public function test_cliente_nao_acessa_produtos_do_painel(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel/produtos')->assertForbidden();
    }

    public function test_listagem_filtra_por_nome_categoria_e_status(): void
    {
        $saias = Categoria::factory()->create(['nome' => 'Saias']);
        Produto::factory()->create(['nome' => 'Vestido Floral', 'categoria_id' => $this->vestidos->id]);
        Produto::factory()->inativo()->create(['nome' => 'Saia Midi', 'categoria_id' => $saias->id]);

        $this->actingAs($this->admin)->get('/painel/produtos?busca=floral')->assertSee('Vestido Floral')->assertDontSee('Saia Midi');
        $this->actingAs($this->admin)->get("/painel/produtos?categoria={$saias->id}")->assertSee('Saia Midi')->assertDontSee('Vestido Floral');
        $this->actingAs($this->admin)->get('/painel/produtos?status=inativo')->assertSee('Saia Midi')->assertDontSee('Vestido Floral');
    }
}
