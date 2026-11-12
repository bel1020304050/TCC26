<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\FornecedorRequest;
use App\Models\Fornecedor;
use App\Support\Auditoria;
use App\Support\Documento;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Cadastro de Fornecedores no painel: listar, cadastrar, editar, inativar e reativar.
 */
class FornecedorController extends Controller
{
    // Lista com pesquisa por nome ou CPF/CNPJ (aceita o documento com ou sem pontuação)
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Fornecedor::class);

        $busca = trim((string) $request->query('busca'));
        $status = $request->query('status');
        $documento = Documento::limpar($busca); // sem pontuação; CNPJ alfanumérico em maiúsculas

        $fornecedores = Fornecedor::query()
            ->withCount(['produtos as produtos_ativos_count' => fn ($q) => $q->ativos()])
            ->when($busca !== '', function ($q) use ($busca, $documento) {
                $q->where(function ($q) use ($busca, $documento) {
                    $q->where('nome', 'like', "%{$busca}%");
                    if (strlen($documento) >= 3) {
                        $q->orWhere('documento', 'like', "%{$documento}%");
                    }
                });
            })
            ->when(in_array($status, Status::OPCOES, true), fn ($q) => $q->where('status', $status))
            ->orderBy('nome')
            ->paginate(10)
            ->withQueryString();

        return view('painel.fornecedores.index', compact('fornecedores', 'busca', 'status'));
    }

    // Formulário de cadastro
    public function create(): View
    {
        Gate::authorize('create', Fornecedor::class);

        return view('painel.fornecedores.form', ['fornecedor' => new Fornecedor]);
    }

    public function store(FornecedorRequest $request): RedirectResponse
    {
        $fornecedor = new Fornecedor($request->safe()->except('status'));
        $fornecedor->status = $request->validated('status');
        $fornecedor->save();

        Auditoria::registrar('criou', $fornecedor);

        return redirect()->route('painel.fornecedores.index')
            ->with('sucesso', "Fornecedor \"{$fornecedor->nome}\" cadastrado com sucesso.");
    }

    // Formulário de edição
    public function edit(Fornecedor $fornecedor): View
    {
        Gate::authorize('update', $fornecedor);

        $fornecedor->loadCount('produtos');

        return view('painel.fornecedores.form', compact('fornecedor'));
    }

    public function update(FornecedorRequest $request, Fornecedor $fornecedor): RedirectResponse
    {
        $campos = ['nome', 'tipo_pessoa', 'documento', 'telefone', 'email', 'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'];
        $antes = $fornecedor->only($campos);
        $fornecedor->update($request->validated()); // os produtos continuam vinculados

        Auditoria::registrar('editou', $fornecedor, ['antes' => $antes, 'depois' => $fornecedor->only($campos)]);

        return redirect()->route('painel.fornecedores.index')
            ->with('sucesso', "Fornecedor \"{$fornecedor->nome}\" atualizado.");
    }

    // Inativar = exclusão lógica. Regra: fornecedor com produtos ativos não pode ser inativado.
    public function inativar(Request $request, Fornecedor $fornecedor): RedirectResponse
    {
        Gate::authorize('alterarStatus', $fornecedor);
        $motivo = $this->motivo($request);

        if ($fornecedor->possuiProdutosAtivos()) {
            $qtd = $fornecedor->produtos()->ativos()->count();

            return back()->with('erro', "Não é possível inativar \"{$fornecedor->nome}\": há {$qtd} produto(s) ativo(s) vinculado(s). Troque o fornecedor desses produtos ou inative-os antes de continuar.");
        }

        $fornecedor->status = Status::INATIVO; // status não é "fillable": muda só por aqui
        $fornecedor->save();
        Auditoria::registrar('inativou', $fornecedor, motivo: $motivo);

        return back()->with('sucesso', "Fornecedor \"{$fornecedor->nome}\" inativado. Ele não aparece mais como opção em novos produtos.");
    }

    public function reativar(Request $request, Fornecedor $fornecedor): RedirectResponse
    {
        Gate::authorize('alterarStatus', $fornecedor);
        $motivo = $this->motivo($request);

        $fornecedor->status = Status::ATIVO; // status não é "fillable": muda só por aqui
        $fornecedor->save();
        Auditoria::registrar('reativou', $fornecedor, motivo: $motivo);

        return back()->with('sucesso', "Fornecedor \"{$fornecedor->nome}\" reativado.");
    }
}
