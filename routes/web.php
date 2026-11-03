<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Loja\CarrinhoController;
use App\Http\Controllers\Loja\ContaController;
use App\Http\Controllers\Loja\PagamentoController;
use App\Http\Controllers\Loja\PedidoController;
use App\Http\Controllers\Loja\VitrineController;
use App\Http\Controllers\Painel\CategoriaController;
use App\Http\Controllers\Painel\FornecedorController;
use App\Http\Controllers\Painel\InicioController;
use App\Http\Controllers\Painel\MinhaContaController;
use App\Http\Controllers\Painel\PagamentoController as PainelPagamentoController;
use App\Http\Controllers\Painel\PedidoController as PainelPedidoController;
use App\Http\Controllers\Painel\ProdutoController;
use App\Http\Controllers\Painel\PromocaoController;
use App\Http\Controllers\Painel\RelatorioController;
use App\Http\Controllers\Painel\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Loja (área pública e do cliente)
|--------------------------------------------------------------------------
*/
// Vitrine — story 3.2 (aberta para visitantes)
Route::get('/', VitrineController::class)->name('loja.inicio');

// Área do cliente: carrinho (3.30) e pedidos (3.18, 3.19). Só clientes logados.
Route::name('loja.')->middleware(['auth', 'perfil:cliente'])->group(function () {
    // Minha conta do cliente: consultar (3.10), editar (3.11) e encerrar (3.12). Sem id na URL: sempre a conta da sessão.
    Route::get('/minha-conta', [ContaController::class, 'show'])->name('conta');
    Route::get('/minha-conta/editar', [ContaController::class, 'edit'])->name('conta.edit');
    Route::put('/minha-conta', [ContaController::class, 'update'])->name('conta.update');
    Route::put('/minha-conta/senha', [ContaController::class, 'senha'])->name('conta.senha');
    Route::delete('/minha-conta', [ContaController::class, 'destroy'])->name('conta.destroy');

    Route::get('/carrinho', [CarrinhoController::class, 'index'])->name('carrinho');
    Route::post('/carrinho/itens', [CarrinhoController::class, 'adicionar'])->name('carrinho.adicionar');
    Route::patch('/carrinho/itens/{item}', [CarrinhoController::class, 'atualizar'])->name('carrinho.atualizar');
    Route::delete('/carrinho/itens/{item}', [CarrinhoController::class, 'remover'])->name('carrinho.remover');
    Route::post('/carrinho/cupom', [CarrinhoController::class, 'aplicarCupom'])->name('carrinho.cupom');
    Route::delete('/carrinho/cupom', [CarrinhoController::class, 'removerCupom'])->name('carrinho.cupom.remover');

    Route::get('/pedido/confirmar', [PedidoController::class, 'confirmar'])->name('pedidos.confirmar');
    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    Route::get('/meus-pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
    Route::get('/meus-pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
    Route::patch('/meus-pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->name('pedidos.cancelar');

    // Processo 3.31 — Finalizar Compra / Pagamento (provedor simulado no MVP)
    Route::get('/meus-pedidos/{pedido}/pagamento', [PagamentoController::class, 'create'])->name('pagamento.create');
    Route::post('/meus-pedidos/{pedido}/pagamento', [PagamentoController::class, 'store'])->name('pagamento.store');
    Route::post('/meus-pedidos/{pedido}/pagamento/{pagamento}/simular-pix', [PagamentoController::class, 'simularPix'])->name('pagamento.simular-pix');
    Route::get('/meus-pedidos/{pedido}/concluido', [PagamentoController::class, 'concluido'])->name('pagamento.concluido');
});

/*
|--------------------------------------------------------------------------
| Autenticação — story 3.13 (login/logout) e 3.9 (criar conta)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->name('login.store');
    Route::get('/criar-conta', [RegisterController::class, 'create'])->name('register');
    Route::post('/criar-conta', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/sair', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Painel administrativo — só equipe interna (seção 2.2)
|--------------------------------------------------------------------------
*/
Route::prefix('painel')->name('painel.')->middleware(['auth', 'perfil:admin,funcionario'])->group(function () {
    Route::get('/', InicioController::class)->name('inicio');

    // Fase 3 — Produtos (3.1, 3.3, 3.4). Funcionário cadastra e edita; só o Administrador inativa.
    Route::resource('produtos', ProdutoController::class)
        ->except(['show', 'destroy'])
        ->parameters(['produtos' => 'produto']);
    Route::patch('produtos/{produto}/inativar', [ProdutoController::class, 'inativar'])->name('produtos.inativar');
    Route::patch('produtos/{produto}/reativar', [ProdutoController::class, 'reativar'])->name('produtos.reativar');

    // Fase 5 — Pedidos: consulta com filtros (3.19).
    Route::get('pedidos', [PainelPedidoController::class, 'index'])->name('pedidos.index');
    Route::get('pedidos/{pedido}', [PainelPedidoController::class, 'show'])->name('pedidos.show');
    // Fase 7 — Atualizar status (3.20) e cancelar (3.21)
    Route::patch('pedidos/{pedido}/status', [PainelPedidoController::class, 'atualizarStatus'])->name('pedidos.status');
    Route::patch('pedidos/{pedido}/cancelar', [PainelPedidoController::class, 'cancelar'])->name('pedidos.cancelar');
    // Fase 6 — Boleto simulado: o Administrador confirma a compensação
    Route::post('pagamentos/{pagamento}/confirmar-boleto', [PainelPagamentoController::class, 'confirmarBoleto'])->name('pagamentos.confirmar-boleto');

    // Fase 8 — Promoções (3.22–3.25). Funcionário só consulta; o resto é do Administrador.
    Route::resource('promocoes', PromocaoController::class)
        ->except(['show', 'destroy'])
        ->parameters(['promocoes' => 'promocao']);
    Route::patch('promocoes/{promocao}/inativar', [PromocaoController::class, 'inativar'])->name('promocoes.inativar');
    Route::patch('promocoes/{promocao}/reativar', [PromocaoController::class, 'reativar'])->name('promocoes.reativar');

    // Fase 10 — Relatórios (3.32–3.34). ?exportar=csv ou ?exportar=pdf
    Route::get('relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
    Route::get('relatorios/vendas', [RelatorioController::class, 'vendas'])->name('relatorios.vendas');
    Route::get('relatorios/estoque', [RelatorioController::class, 'estoque'])->name('relatorios.estoque');
    Route::get('relatorios/clientes', [RelatorioController::class, 'clientes'])->name('relatorios.clientes');

    // Fase 2 — Categorias (3.14–3.17) e Fornecedores (3.26–3.29).
    // Funcionário só consulta; cadastrar, editar e inativar são do Administrador (Policies).
    Route::resource('categorias', CategoriaController::class)
        ->except(['show', 'destroy'])
        ->parameters(['categorias' => 'categoria']);
    Route::patch('categorias/{categoria}/inativar', [CategoriaController::class, 'inativar'])->name('categorias.inativar');
    Route::patch('categorias/{categoria}/reativar', [CategoriaController::class, 'reativar'])->name('categorias.reativar');

    Route::resource('fornecedores', FornecedorController::class)
        ->except(['show', 'destroy'])
        ->parameters(['fornecedores' => 'fornecedor']);
    Route::patch('fornecedores/{fornecedor}/inativar', [FornecedorController::class, 'inativar'])->name('fornecedores.inativar');
    Route::patch('fornecedores/{fornecedor}/reativar', [FornecedorController::class, 'reativar'])->name('fornecedores.reativar');

    // Minha conta da equipe (nome, telefone e senha)
    Route::get('minha-conta', [MinhaContaController::class, 'edit'])->name('minha-conta');
    Route::put('minha-conta', [MinhaContaController::class, 'update'])->name('minha-conta.update');
    Route::put('minha-conta/senha', [MinhaContaController::class, 'senha'])->name('minha-conta.senha');

    // Fase 4 — Usuários internos (3.5–3.8). Só o Administrador (UserPolicy).
    Route::resource('usuarios', UsuarioController::class)
        ->except(['show', 'destroy'])
        ->parameters(['usuarios' => 'usuario']);
    Route::patch('usuarios/{usuario}/inativar', [UsuarioController::class, 'inativar'])->name('usuarios.inativar');
    Route::patch('usuarios/{usuario}/reativar', [UsuarioController::class, 'reativar'])->name('usuarios.reativar');
});
