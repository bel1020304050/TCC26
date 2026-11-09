<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromocaoRequest;
use App\Models\Categoria;
use App\Models\Produto;
use App\Models\Promocao;
use App\Support\Auditoria;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Cadastro de Promoções e cupons no painel: listar, cadastrar, editar, inativar e reativar.
 */
class PromocaoController extends Controller
{
    private const CAMPOS = ['nome', 'modo', 'codigo', 'tipo', 'valor', 'data_inicio', 'data_fim', 'aplicacao', 'produto_id', 'categoria_id'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Promocao::class);

        $busca = trim((string) $request->query('busca'));
        $situacao = array_key_exists((string) $request->query('situacao'), Promocao::SITUACOES) ? $request->query('situacao') : null;

        $promocoes = Promocao::query()
            ->with(['produto:id,nome,tamanho,cor', 'categoria:id,nome'])
            ->when($busca !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('codigo', 'like', '%'.mb_strtoupper($busca).'%')))
            ->when($situacao, fn ($q) => $q->situacao($situacao))
            ->orderByDesc('data_inicio')->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return view('painel.promocoes.index', compact('promocoes', 'busca', 'situacao'));
    }

    public function create(): View
    {
        Gate::authorize('create', Promocao::class);

        return view('painel.promocoes.form', ['promocao' => new Promocao] + $this->opcoes());
    }

    public function store(PromocaoRequest $request): RedirectResponse
    {
        $promocao = new Promocao($request->safe()->only(self::CAMPOS));
        $promocao->status = $request->validated('status');
        $promocao->save();
        Auditoria::registrar('criou', $promocao);

        return redirect()->route('painel.promocoes.index')
            ->with('sucesso', "Promoção \"{$promocao->nome}\" cadastrada · situação: {$promocao->situacaoLabel()}.");
    }

    public function edit(Promocao $promocao): View
    {
        Gate::authorize('update', $promocao);

        return view('painel.promocoes.form', ['promocao' => $promocao, 'usada' => $promocao->foiUsada()] + $this->opcoes($promocao));
    }

    public function update(PromocaoRequest $request, Promocao $promocao): RedirectResponse
    {
        $antes = $this->valores($promocao);

        $promocao->fill($request->safe()->only(self::CAMPOS));
        $promocao->save(); // pedidos antigos guardaram o próprio desconto: não mudam

        // Auditoria: guarda os valores de antes e de depois da edição
        Auditoria::registrar('editou', $promocao, ['antes' => $antes, 'depois' => $this->valores($promocao)]);

        return redirect()->route('painel.promocoes.index')->with('sucesso', "Promoção \"{$promocao->nome}\" atualizada.");
    }

    // Regra: a promoção inativada deixa de valer em novos carrinhos; pedidos já feitos mantêm o desconto
    public function inativar(Promocao $promocao): RedirectResponse
    {
        Gate::authorize('alterarStatus', $promocao);

        $promocao->status = Status::INATIVO;
        $promocao->save();
        Auditoria::registrar('inativou', $promocao);

        return back()->with('sucesso', "Promoção \"{$promocao->nome}\" inativada. Ela não é mais aplicada em novas compras.");
    }

    public function reativar(Promocao $promocao): RedirectResponse
    {
        Gate::authorize('alterarStatus', $promocao);

        $promocao->status = Status::ATIVO;
        $promocao->save();
        Auditoria::registrar('reativou', $promocao);

        return back()->with('sucesso', "Promoção \"{$promocao->nome}\" reativada · situação: {$promocao->situacaoLabel()}.");
    }

    private function valores(Promocao $promocao): array
    {
        $valores = $promocao->only(self::CAMPOS);
        $valores['data_inicio'] = $promocao->data_inicio?->toDateString();
        $valores['data_fim'] = $promocao->data_fim?->toDateString();

        return $valores;
    }

    // Só produtos/categorias ativos (o vínculo atual aparece mesmo se tiver sido inativado)
    private function opcoes(?Promocao $promocao = null): array
    {
        return [
            'produtos' => Produto::query()
                ->where(fn ($q) => $q->ativos()->when($promocao?->produto_id, fn ($q) => $q->orWhere('id', $promocao->produto_id)))
                ->orderBy('nome')->orderBy('tamanho')->get(['id', 'nome', 'tamanho', 'cor']),
            'categorias' => Categoria::query()
                ->where(fn ($q) => $q->ativos()->when($promocao?->categoria_id, fn ($q) => $q->orWhere('id', $promocao->categoria_id)))
                ->orderBy('nome')->get(['id', 'nome']),
        ];
    }
}
