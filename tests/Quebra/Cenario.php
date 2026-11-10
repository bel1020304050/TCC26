<?php

namespace Tests\Quebra;

use App\Models\Carrinho;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;

/**
 * Monta uma loja com dados de todos os tipos, usando os próprios fluxos do sistema,
 * para os testes de "tentar quebrar" terem algo de verdade para atacar.
 */
trait Cenario
{
    protected array $u = [];   // usuários por tipo
    protected array $m = [];   // registros (categoria, produto, pedidos...)

    protected function montarCenario(): void
    {
        $this->u = [
            'admin' => User::factory()->admin()->create(),
            'gerente' => User::factory()->gerente()->create(),
            'atendente' => User::factory()->atendente()->create(),
            'provisoria' => User::factory()->atendente()->comSenhaProvisoria()->create(),
            'cliente' => User::factory()->create(),
            'cliente_b' => User::factory()->create(),
            'nao_verificado' => User::factory()->naoVerificado()->create(),
        ];

        $categoria = Categoria::factory()->create();
        $fornecedor = Fornecedor::factory()->create();
        $produto = Produto::factory()->create(['categoria_id' => $categoria->id, 'fornecedor_id' => $fornecedor->id, 'estoque' => 50, 'preco' => 100]);
        $promocao = Promocao::factory()->create();

        // Pedido 1 do cliente: pago no PIX
        Carrinho::doCliente($this->u['cliente'])->itens()->create(['produto_id' => $produto->id, 'quantidade' => 1]);
        $this->actingAs($this->u['cliente'])->post('/pedidos');
        $pago = Pedido::latest('id')->first();
        $this->actingAs($this->u['cliente'])->post("/meus-pedidos/{$pago->id}/pagamento", ['forma' => 'pix']);
        $pix = Pagamento::where('pedido_id', $pago->id)->latest('id')->first();
        $this->actingAs($this->u['cliente'])->post("/meus-pedidos/{$pago->id}/pagamento/{$pix->id}/simular-pix");

        // Pedido 2 do cliente: boleto pendente
        Carrinho::doCliente($this->u['cliente'])->itens()->create(['produto_id' => $produto->id, 'quantidade' => 1]);
        $this->actingAs($this->u['cliente'])->post('/pedidos');
        $pendente = Pedido::latest('id')->first();
        $this->actingAs($this->u['cliente'])->post("/meus-pedidos/{$pendente->id}/pagamento", ['forma' => 'boleto']);
        $boleto = Pagamento::where('pedido_id', $pendente->id)->latest('id')->first();

        // Cliente B com carrinho aberto
        $item = Carrinho::doCliente($this->u['cliente_b'])->itens()->create(['produto_id' => $produto->id, 'quantidade' => 1]);

        auth()->logout();
        $this->m = compact('categoria', 'fornecedor', 'produto', 'promocao', 'pago', 'pendente', 'pix', 'boleto', 'item');
    }

    // Troca {parametro} da rota por um registro real do cenário
    protected function urlDe(\Illuminate\Routing\Route $rota): ?string
    {
        $troca = [
            'categoria' => $this->m['categoria']->id, 'fornecedor' => $this->m['fornecedor']->id,
            'produto' => $this->m['produto']->id, 'promocao' => $this->m['promocao']->id,
            'pedido' => $this->m['pendente']->id, 'pagamento' => $this->m['boleto']->id,
            'item' => $this->m['item']->id, 'usuario' => $this->u['atendente']->id,
            'token' => 'token-falso', 'id' => $this->u['nao_verificado']->id, 'hash' => sha1('x'),
        ];
        $uri = '/'.ltrim($rota->uri(), '/');
        foreach ($rota->parameterNames() as $nome) {
            if (! isset($troca[$nome])) {
                return null;
            }
            $uri = str_replace('{'.$nome.'}', (string) $troca[$nome], $uri);
        }

        return $uri;
    }
}
