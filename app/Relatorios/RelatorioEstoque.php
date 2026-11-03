<?php

namespace App\Relatorios;

use App\Models\Produto;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Story 3.33 – Relatório de Produtos / Estoque (Administrador e Funcionário).
 * Estoque baixo = 10 unidades ou menos.
 */
class RelatorioEstoque extends Relatorio
{
    public const LIMITE_BAIXO = 10;

    public function titulo(): string
    {
        return 'Relatório de produtos e estoque';
    }

    public function permissao(): string
    {
        return 'relatorio-estoque';
    }

    protected function lerFiltros(Request $request): array
    {
        $status = (string) $request->query('status');

        return [
            'categoria' => $request->integer('categoria') ?: null,
            'fornecedor' => $request->integer('fornecedor') ?: null,
            'status' => in_array($status, Status::OPCOES, true) ? $status : '',
            'tamanho' => trim((string) $request->query('tamanho')),
            'cor' => trim((string) $request->query('cor')),
            'baixo' => $request->boolean('baixo'),
        ];
    }

    protected function consulta(): Builder
    {
        $f = $this->filtros;

        return Produto::query()
            ->with(['categoria:id,nome', 'fornecedor:id,nome'])
            ->when($f['categoria'], fn ($q) => $q->where('categoria_id', $f['categoria']))
            ->when($f['fornecedor'], fn ($q) => $q->where('fornecedor_id', $f['fornecedor']))
            ->when($f['status'], fn ($q) => $q->where('status', $f['status']))
            ->when($f['tamanho'] !== '', fn ($q) => $q->where('tamanho', $f['tamanho']))
            ->when($f['cor'] !== '', fn ($q) => $q->where('cor', $f['cor']))
            ->when($f['baixo'], fn ($q) => $q->where('estoque', '<=', self::LIMITE_BAIXO))
            ->orderBy('estoque')->orderBy('nome')->orderBy('tamanho');
    }

    public function colunas(): array
    {
        return ['Produto', 'Categoria', 'Fornecedor', 'Tamanho', 'Cor', 'Preço', 'Estoque', 'Status'];
    }

    public function linha(Model $produto): array
    {
        return [
            $produto->nome,
            $produto->categoria->nome,
            $produto->fornecedor?->nome ?? '—',
            $produto->tamanho,
            $produto->cor,
            $produto->precoFormatado(),
            (string) $produto->estoque,
            $produto->statusLabel(),
        ];
    }

    public function destaque(Model $produto): bool
    {
        return $produto->estoque <= self::LIMITE_BAIXO;
    }

    public function indicadores(): array
    {
        return [
            'Produtos listados' => (string) $this->consulta()->count(),
            'Unidades em estoque' => (string) (int) $this->consulta()->sum('estoque'),
            'Com estoque baixo (≤ 10)' => (string) $this->consulta()->where('estoque', '<=', self::LIMITE_BAIXO)->count(),
        ];
    }

    public function descricaoFiltros(): string
    {
        $f = $this->filtros;
        $partes = [
            'Categoria: '.($f['categoria'] ? \App\Models\Categoria::find($f['categoria'])?->nome : 'Todas'),
            'Fornecedor: '.($f['fornecedor'] ? \App\Models\Fornecedor::find($f['fornecedor'])?->nome : 'Todos'),
            'Status: '.($f['status'] ? Status::label($f['status']) : 'Todos'),
        ];
        if ($f['tamanho'] !== '') $partes[] = 'Tamanho: '.$f['tamanho'];
        if ($f['cor'] !== '') $partes[] = 'Cor: '.$f['cor'];
        if ($f['baixo']) $partes[] = 'Só estoque baixo';

        return implode(' · ', $partes);
    }

    public function rodape(): string
    {
        return 'Em destaque: estoque baixo (10 unidades ou menos). O estoque é o salvo no banco após vendas, cancelamentos e reposições.';
    }
}
