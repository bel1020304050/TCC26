<?php

namespace App\Relatorios;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Relatório de Clientes (toda a equipe).
 * Minimização de dados (LGPD): sem senha, telefone ou endereço.
 * Conta encerrada aparece como "Cliente removido", sem e-mail, e suas compras continuam somando.
 */
class RelatorioClientes extends Relatorio
{
    public function titulo(): string
    {
        return 'Relatório de clientes';
    }

    public function permissao(): string
    {
        return 'relatorio-clientes';
    }

    protected function lerFiltros(Request $request): array
    {
        [$de, $ate] = $this->periodo($request);
        $compras = (string) $request->query('compras');

        return [
            'de' => $de, 'ate' => $ate,
            'compras' => in_array($compras, ['com', 'sem'], true) ? $compras : '',
            'ordem' => $request->query('ordem') === 'compras' ? 'compras' : 'cadastro',
        ];
    }

    protected function consulta(): Builder
    {
        $f = $this->filtros;
        $pagos = fn ($q) => $q->whereIn('status', Pedido::VENDA_PAGA);

        return User::query()
            ->clientes()
            ->select(['id', 'name', 'email', 'status', 'created_at'])
            ->withCount(['pedidos as pedidos_pagos' => $pagos])
            ->withSum(['pedidos as total_comprado' => $pagos], 'total')
            ->whereBetween('created_at', [$f['de'], $f['ate']])
            ->when($f['compras'] === 'com', fn ($q) => $q->whereHas('pedidos', $pagos))
            ->when($f['compras'] === 'sem', fn ($q) => $q->whereDoesntHave('pedidos', $pagos))
            ->when($f['ordem'] === 'compras',
                fn ($q) => $q->orderByDesc('total_comprado')->orderBy('name'),
                fn ($q) => $q->orderBy('created_at')->orderBy('id'));
    }

    public function colunas(): array
    {
        return ['Cliente', 'E-mail', 'Cadastro', 'Pedidos pagos', 'Total comprado'];
    }

    public function linha(Model $cliente): array
    {
        return [
            $cliente->isEncerrado() ? 'Cliente removido' : $cliente->name,
            $cliente->isEncerrado() ? '—' : $cliente->email,
            $cliente->created_at->format('d/m/Y'),
            (string) $cliente->pedidos_pagos,
            self::dinheiro($cliente->total_comprado ?? 0),
        ];
    }

    public function indicadores(): array
    {
        $clientes = $this->registros();

        return [
            'Clientes cadastrados no período' => (string) $clientes->count(),
            'Clientes com compras pagas' => (string) $clientes->where('pedidos_pagos', '>', 0)->count(),
            'Valor total comprado' => self::dinheiro($clientes->sum(fn ($c) => (float) $c->total_comprado)),
        ];
    }

    public function descricaoFiltros(): string
    {
        $f = $this->filtros;

        return 'Cadastro: '.$f['de']->format('d/m/Y').' a '.$f['ate']->format('d/m/Y')
            .' · Compras: '.['com' => 'Com compras pagas', 'sem' => 'Sem compras pagas', '' => 'Todos os clientes'][$f['compras']]
            .' · Ordem: '.($f['ordem'] === 'compras' ? 'Volume de compras' : 'Data de cadastro');
    }

    public function rodape(): string
    {
        return 'Conta apenas pedidos pagos. Contas encerradas aparecem como "Cliente removido". Sem telefone e endereço (LGPD).';
    }
}
