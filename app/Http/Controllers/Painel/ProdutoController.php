<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProdutoRequest;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Support\Auditoria;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * CRUD de Produtos — stories 3.1 (cadastrar), 3.3 (editar) e 3.4 (inativar).
 * A consulta pública é a vitrine (3.2), em Loja\VitrineController.
 */
class ProdutoController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Produto::class);

        $busca = trim((string) $request->query('busca'));
        $status = $request->query('status');
        $categoriaId = $request->integer('categoria') ?: null;

        $produtos = Produto::query()
            ->with(['categoria:id,nome', 'fornecedor:id,nome'])
            ->when($busca !== '', fn ($q) => $q->where('nome', 'like', "%{$busca}%"))
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->when(in_array($status, Status::OPCOES, true), fn ($q) => $q->where('status', $status))
            ->orderBy('nome')->orderBy('tamanho')->orderBy('cor')
            ->paginate(15)
            ->withQueryString();

        $categorias = Categoria::orderBy('nome')->get(['id', 'nome']);

        return view('painel.produtos.index', compact('produtos', 'busca', 'status', 'categoriaId', 'categorias'));
    }

    public function create(): View
    {
        Gate::authorize('create', Produto::class);

        return view('painel.produtos.form', ['produto' => new Produto] + $this->opcoes());
    }

    public function store(ProdutoRequest $request): RedirectResponse
    {
        $produto = new Produto($request->safe()->except(['imagem', 'remover_imagem', 'status']));
        $produto->status = $request->validated('status');

        if ($request->hasFile('imagem')) {
            $produto->imagem = $request->file('imagem')->store('produtos', 'public');
        }

        $produto->save();
        Auditoria::registrar('criou', $produto);

        return redirect()->route('painel.produtos.index')
            ->with('sucesso', "Produto \"{$produto->nome} – {$produto->variacao()}\" cadastrado com sucesso.");
    }

    public function edit(Produto $produto): View
    {
        Gate::authorize('update', $produto);

        return view('painel.produtos.form', compact('produto') + $this->opcoes($produto));
    }

    public function update(ProdutoRequest $request, Produto $produto): RedirectResponse
    {
        $campos = ['nome', 'descricao', 'categoria_id', 'fornecedor_id', 'preco', 'estoque', 'tamanho', 'cor'];
        $antes = $produto->only($campos);

        $produto->fill($request->safe()->only($campos));

        // Troca ou remove a imagem, apagando o arquivo antigo
        if ($request->hasFile('imagem') || $request->boolean('remover_imagem')) {
            if ($produto->imagem) {
                Storage::disk('public')->delete($produto->imagem);
            }
            $produto->imagem = $request->hasFile('imagem')
                ? $request->file('imagem')->store('produtos', 'public')
                : null;
        }

        $produto->save(); // mantém o mesmo id: pedidos antigos não mudam
        Auditoria::registrar('editou', $produto, ['antes' => $antes, 'depois' => $produto->only($campos)]);

        return redirect()->route('painel.produtos.index')
            ->with('sucesso', "Produto \"{$produto->nome} – {$produto->variacao()}\" atualizado.");
    }

    // 3.4 – Inativar: sai da vitrine, mas continua no banco e no histórico
    public function inativar(Produto $produto): RedirectResponse
    {
        Gate::authorize('alterarStatus', $produto);

        $produto->status = Status::INATIVO;
        $produto->save();
        Auditoria::registrar('inativou', $produto);

        return back()->with('sucesso', "Produto \"{$produto->nome} – {$produto->variacao()}\" inativado. Ele não aparece mais na vitrine.");
    }

    public function reativar(Produto $produto): RedirectResponse
    {
        Gate::authorize('alterarStatus', $produto);

        $produto->status = Status::ATIVO;
        $produto->save();
        Auditoria::registrar('reativou', $produto);

        return back()->with('sucesso', "Produto \"{$produto->nome} – {$produto->variacao()}\" reativado.");
    }

    /**
     * Opções dos selects: só categorias/fornecedores ativos.
     * Na edição, o vínculo atual aparece mesmo se tiver sido inativado depois.
     */
    private function opcoes(?Produto $produto = null): array
    {
        $categorias = Categoria::query()
            ->where(fn ($q) => $q->ativos()->when($produto, fn ($q) => $q->orWhere('id', $produto->categoria_id)))
            ->orderBy('nome')->get(['id', 'nome', 'status']);

        $fornecedores = Fornecedor::query()
            ->where(fn ($q) => $q->ativos()->when($produto?->fornecedor_id, fn ($q) => $q->orWhere('id', $produto->fornecedor_id)))
            ->orderBy('nome')->get(['id', 'nome', 'status']);

        return compact('categorias', 'fornecedores');
    }
}
