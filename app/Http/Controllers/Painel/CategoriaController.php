<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriaRequest;
use App\Models\Categoria;
use App\Support\Auditoria;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CRUD de Categorias — stories 3.14 (cadastrar), 3.15 (consultar), 3.16 (editar) e 3.17 (inativar).
 */
class CategoriaController extends Controller
{
    // 3.15 – Consultar: lista ativas e inativas, com pesquisa por nome
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Categoria::class);

        $busca = trim((string) $request->query('busca'));
        $status = $request->query('status');

        $categorias = Categoria::query()
            ->withCount([
                'produtos as produtos_vinculados_count',
                'produtos as produtos_ativos_count' => fn ($q) => $q->ativos(),
            ])
            ->when($busca !== '', fn ($q) => $q->where('nome', 'like', "%{$busca}%"))
            ->when(in_array($status, Status::OPCOES, true), fn ($q) => $q->where('status', $status))
            ->orderBy('nome')
            ->paginate(10)
            ->withQueryString();

        return view('painel.categorias.index', compact('categorias', 'busca', 'status'));
    }

    // 3.14 – Cadastrar
    public function create(): View
    {
        Gate::authorize('create', Categoria::class);

        return view('painel.categorias.form', ['categoria' => new Categoria]);
    }

    public function store(CategoriaRequest $request): RedirectResponse
    {
        $categoria = new Categoria($request->safe()->only(['nome', 'descricao']));
        $categoria->status = $request->validated('status');
        $categoria->save();

        Auditoria::registrar('criou', $categoria);

        return redirect()->route('painel.categorias.index')
            ->with('sucesso', "Categoria \"{$categoria->nome}\" cadastrada com sucesso.");
    }

    // 3.16 – Editar
    public function edit(Categoria $categoria): View
    {
        Gate::authorize('update', $categoria);

        $categoria->loadCount('produtos');

        return view('painel.categorias.form', compact('categoria'));
    }

    public function update(CategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $antes = $categoria->only(['nome', 'descricao']);
        $categoria->update($request->validated()); // os produtos continuam vinculados

        Auditoria::registrar('editou', $categoria, ['antes' => $antes, 'depois' => $categoria->only(['nome', 'descricao'])]);

        return redirect()->route('painel.categorias.index')
            ->with('sucesso', "Categoria \"{$categoria->nome}\" atualizada.");
    }

    // 3.17 – Inativar (exclusão lógica)
    public function inativar(Categoria $categoria): RedirectResponse
    {
        Gate::authorize('alterarStatus', $categoria);

        // Regra: bloqueia se houver produtos ativos (verificado no servidor, no momento da confirmação)
        if ($categoria->possuiProdutosAtivos()) {
            $qtd = $categoria->produtos()->ativos()->count();

            return back()->with('erro', "Não é possível inativar \"{$categoria->nome}\": a categoria tem {$qtd} produto(s) ativo(s). Mova os produtos para outra categoria ou inative-os antes de continuar.");
        }

        $categoria->status = Status::INATIVO; // status não é "fillable": muda só por aqui
        $categoria->save();
        Auditoria::registrar('inativou', $categoria);

        return back()->with('sucesso', "Categoria \"{$categoria->nome}\" inativada. Ela não aparece mais na vitrine nem no cadastro de produtos.");
    }

    public function reativar(Categoria $categoria): RedirectResponse
    {
        Gate::authorize('alterarStatus', $categoria);

        $categoria->status = Status::ATIVO; // status não é "fillable": muda só por aqui
        $categoria->save();
        Auditoria::registrar('reativou', $categoria);

        return back()->with('sucesso', "Categoria \"{$categoria->nome}\" reativada.");
    }
}
